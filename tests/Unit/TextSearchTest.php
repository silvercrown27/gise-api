<?php

namespace Tests\Unit;

use App\Support\TextSearch;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TextSearchTest extends TestCase
{
    private const COLUMNS = ['courses.title', 'courses.code', 'courses.tagline', 'courses.short_description'];

    public function test_words_are_lowercased_and_stripped_of_symbols(): void
    {
        $this->assertSame(['supply', 'chain', 'p091'], TextSearch::words('  Supply-Chain, P091!! '));
        $this->assertSame([], TextSearch::words('  +-*()  '));
    }

    public function test_boolean_queries_make_every_word_a_required_prefix_and_cannot_inject_operators(): void
    {
        $this->assertSame('+procure* +supply*', TextSearch::booleanQuery(TextSearch::words('procure supply')));
        // Operators typed by a visitor are dropped by words(), so they never reach MATCH ... AGAINST.
        $this->assertSame('+evil* +word* +x* +y* +z*', TextSearch::booleanQuery(TextSearch::words('+evil -word* "x" (y) @z')));
        $this->assertStringNotContainsString('"', TextSearch::booleanQuery(TextSearch::words('"quoted" <angle> ~tilde')));
    }

    public function test_fulltext_is_only_used_when_it_can_find_every_word(): void
    {
        $this->assertTrue(TextSearch::canUseFulltext(['procurement']));
        $this->assertTrue(TextSearch::canUseFulltext(['p091', 'supply']));
        $this->assertFalse(TextSearch::canUseFulltext([]), 'nothing to search for');
        $this->assertFalse(TextSearch::canUseFulltext(['ab']), 'shorter than the index minimum word length');
        $this->assertFalse(TextSearch::canUseFulltext(['supply', 'for']), 'a stopword is never indexed, so it would match nothing');
        $this->assertFalse(TextSearch::canUseFulltext(array_fill(0, 9, 'word')), 'a pasted sentence is not a search');
    }

    public function test_it_escapes_like_wildcards(): void
    {
        $this->assertSame('100!% sure!_ yes!!', TextSearch::escape('100% sure_ yes!'));
    }

    public function test_mysql_gets_a_fulltext_match_plus_an_indexed_prefix(): void
    {
        $query = DB::connection('mysql')->table('courses');
        TextSearch::apply($query, 'Procure supply', self::COLUMNS, ['courses.slug']);

        $this->assertSame(
            "select * from `courses` where (match(courses.title, courses.code, courses.tagline, courses.short_description) against (? in boolean mode) or courses.slug like ? escape '!')",
            $query->toSql()
        );
        $this->assertSame(['+procure* +supply*', 'Procure supply%'], $query->getBindings());
    }

    public function test_mysql_falls_back_to_contains_for_short_or_stopword_terms(): void
    {
        $short = DB::connection('mysql')->table('courses');
        TextSearch::apply($short, 'ab', ['courses.title', 'courses.code'], ['courses.slug']);
        $this->assertStringNotContainsStringIgnoringCase('match(', $short->toSql());
        $this->assertSame(['%ab%', '%ab%', '%ab%'], $short->getBindings());

        // Every word must be present, in any order; a stopword is just another word here.
        $stop = DB::connection('mysql')->table('courses');
        TextSearch::apply($stop, 'supply for', ['courses.title', 'courses.code']);
        $this->assertStringNotContainsStringIgnoringCase('match(', $stop->toSql());
        $this->assertSame(['%supply%', '%supply%', '%for%', '%for%'], $stop->getBindings());
        $this->assertSame("select * from `courses` where ((courses.title like ? escape '!' or courses.code like ? escape '!') and (courses.title like ? escape '!' or courses.code like ? escape '!'))", $stop->toSql());
    }

    public function test_sqlite_always_uses_the_contains_fallback(): void
    {
        $query = DB::connection('sqlite')->table('courses');
        TextSearch::apply($query, 'Procurement', self::COLUMNS);

        $this->assertStringNotContainsStringIgnoringCase('match(', $query->toSql());
        $this->assertSame(array_fill(0, 4, '%procurement%'), $query->getBindings());
    }

    public function test_a_term_of_only_symbols_is_searched_as_typed_with_wildcards_escaped(): void
    {
        $query = DB::connection('sqlite')->table('courses');
        TextSearch::apply($query, '%*', ['courses.title']);

        $this->assertSame(['%!%*%'], $query->getBindings());
    }

    public function test_an_empty_search_adds_no_condition(): void
    {
        $query = DB::connection('mysql')->table('courses');
        TextSearch::apply($query, '   ', self::COLUMNS);

        $this->assertSame('select * from `courses`', $query->toSql());
    }
}
