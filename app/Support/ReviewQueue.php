<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared behaviour of the admin approval queues (modules, quizzes, exams): search by title or
 * course, a status filter, newest/oldest ordering, 10 per page, and a count for each status tab.
 */
class ReviewQueue
{
    /**
     * @param  Builder  $query   the model query, with its eager loads
     * @param  string   $course  relation path from the model to its course ("course" or "module.course")
     * @return array{data: \Illuminate\Contracts\Pagination\LengthAwarePaginator, counts: array<string,int>}
     */
    public static function run(Builder $query, Request $request, string $course): array
    {
        $table = $query->getModel()->getTable();

        $counts = $query->getModel()->newQuery()
            ->selectRaw("{$table}.admin_approval_status as status, count(*) as total")
            ->groupBy("{$table}.admin_approval_status")
            ->pluck('total', 'status');

        if ($term = TextSearch::clean((string) $request->input('q', ''))) {
            $like = '%' . TextSearch::escape($term) . '%';
            $query->where(function (Builder $search) use ($table, $like, $course) {
                $search->whereRaw("{$table}.title like ? escape '!'", [$like])
                    ->orWhereHas($course, fn (Builder $c) => $c->where(
                        fn (Builder $w) => $w->whereRaw("courses.title like ? escape '!'", [$like])
                            ->orWhereRaw("courses.code like ? escape '!'", [$like])
                    ));
            });
        }

        if ($status = trim((string) $request->input('admin_approval_status', ''))) {
            $query->where("{$table}.admin_approval_status", $status);
        }

        $query->orderBy("{$table}.created_at", $request->input('sort') === 'oldest' ? 'asc' : 'desc');

        return [
            'data' => $query->paginate(10),
            'counts' => [
                'total' => (int) $counts->sum(),
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
        ];
    }
}
