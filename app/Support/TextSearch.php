<?php

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Fast text search for the lists and the public catalogue.
 *
 * `LIKE '%word%'` can never use an index: the database reads every row on every keystroke.
 * On MySQL / MariaDB this uses a FULLTEXT index instead (word and word-prefix matching, so
 * "procure" finds "Procurement"), plus an indexed prefix match for codes, slugs and emails.
 * Anywhere FULLTEXT is unavailable (SQLite in tests) or the term is too short for it, it falls
 * back to a plain contains-match, which gives the same results, only slower.
 *
 * The FULLTEXT column list passed in must be exactly the columns of one FULLTEXT index
 * (see the 2025_01_01_000081_add_search_indexes migration).
 */
class TextSearch
{
    /** InnoDB ignores words shorter than this when it builds the index (innodb_ft_min_token_size). */
    private const MIN_WORD = 3;

    /** More words than this is a pasted sentence, not a search: use the simple path. */
    private const MAX_WORDS = 8;

    /** InnoDB's built-in stopwords are never indexed, so a query containing one must not use FULLTEXT. */
    private const STOPWORDS = [
        'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www',
    ];

    /** Trim, collapse whitespace and cap the length of what a visitor typed. */
    public static function clean(string $term, int $max = 100): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $term)), 0, $max);
    }

    /** Escapes a term for `LIKE ... ESCAPE '!'` (the same on MySQL and SQLite). */
    public static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /** The words of a term, lower-cased, with everything that is not a letter or digit removed. */
    public static function words(string $term): array
    {
        return array_values(array_filter(
            preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($term)) ?: [],
            fn ($w) => $w !== ''
        ));
    }

    public static function supportsFulltext(BuilderContract $query): bool
    {
        $connection = method_exists($query, 'getConnection') ? $query->getConnection() : $query->getQuery()->getConnection();

        return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /** True when every word is long enough to be in the FULLTEXT index and none is a stopword. */
    public static function canUseFulltext(array $words): bool
    {
        if ($words === [] || count($words) > self::MAX_WORDS) {
            return false;
        }

        foreach ($words as $word) {
            if (mb_strlen($word) < self::MIN_WORD || in_array($word, self::STOPWORDS, true)) {
                return false;
            }
        }

        return true;
    }

    /** `+procure* +supply*`: every word must be present, each as a prefix. Words are letters/digits only, so no operators can sneak in. */
    public static function booleanQuery(array $words): string
    {
        return implode(' ', array_map(fn ($w) => "+{$w}*", $words));
    }

    /**
     * Adds "the term is found in these columns" to the query as one grouped condition.
     *
     * @param  array<int,string>  $fulltext  columns of one FULLTEXT index, e.g. ['courses.title', 'courses.code']
     * @param  array<int,string>  $prefix    indexed columns matched from the start, e.g. ['courses.slug']
     */
    public static function apply(BuilderContract $query, string $term, array $fulltext, array $prefix = []): void
    {
        $term = self::clean($term);
        if ($term === '') {
            return;
        }

        $escaped = self::escape($term);
        $words = self::words($term);

        $query->where(function ($group) use ($fulltext, $prefix, $escaped, $words, $query) {
            if (self::supportsFulltext($query) && self::canUseFulltext($words)) {
                $group->whereRaw('match(' . implode(', ', $fulltext) . ') against (? in boolean mode)', [self::booleanQuery($words)]);

                foreach ($prefix as $column) {
                    $group->orWhereRaw("{$column} like ? escape '!'", [$escaped . '%']);
                }

                return;
            }

            foreach (array_merge($fulltext, $prefix) as $column) {
                $group->orWhereRaw("{$column} like ? escape '!'", ['%' . $escaped . '%']);
            }
        });
    }
}
