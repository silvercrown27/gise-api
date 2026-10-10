<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * How many rows of a list are in each status, for the count badges on its tabs. Call it on the
 * list's query after the role scoping but before the search / status filters, so the numbers
 * stay the same while a person searches or switches tab.
 */
class StatusCounts
{
    /**
     * @param  list<string>  $statuses  the statuses to report (each defaults to 0)
     * @return array<string,int>  the statuses, plus "total"
     */
    public static function of(Builder $query, string $column, array $statuses): array
    {
        $rows = (clone $query)->toBase()->cloneWithout(['columns', 'orders'])
            ->selectRaw("{$column} as status, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'status');

        $counts = ['total' => (int) $rows->sum()];
        foreach ($statuses as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }
}
