<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The public course catalogue: one set of filter rules shared by the course
 * list and the facet counts, so a count never promises courses the list
 * won't show. Built to stay fast with thousands of courses - list queries
 * skip heavy columns and compute prices/dates as indexed subqueries.
 */
class CourseCatalogue
{
    public const FILTERS = [
        'q', 'classification', 'category', 'mode', 'country', 'city', 'tool', 'licences',
        'certification_type', 'certification_level',
    ];

    private const LIST_COLUMNS = [
        'id', 'code', 'slug', 'title', 'tagline', 'short_description', 'classification', 'category_id',
        'pace_id', 'certificate_kind', 'recognized_body', 'level', 'mode', 'price', 'original_price',
        'currency', 'duration_weeks', 'thumbnail_url', 'spine', 'tag', 'status', 'admin_approval_status',
        'published_at', 'created_at',
    ];

    public function __construct(private array $filters)
    {
        $this->filters = array_map(
            fn ($value) => is_string($value) ? trim($value) : $value,
            array_intersect_key($filters, array_flip(self::FILTERS))
        );
    }

    /**
     * Published, approved courses matching every filter except those named
     * in $except (a facet ignores its own dimension).
     */
    public function query(array $except = []): Builder
    {
        $f = array_diff_key($this->filters, array_flip($except));
        $query = Course::query()
            ->where('courses.status', 'published')
            ->where('courses.admin_approval_status', 'approved');

        if (!empty($f['q'])) {
            $term = '%' . $f['q'] . '%';
            $query->where(fn ($q) => $q->where('courses.title', 'like', $term)
                ->orWhere('courses.code', 'like', $term)
                ->orWhere('courses.tagline', 'like', $term));
        }

        if (!empty($f['classification'])) {
            $query->where('courses.classification', $f['classification']);
        }

        if (!empty($f['category'])) {
            $query->whereIn('courses.category_id', Category::where('slug', $f['category'])->select('id'));
        }

        if (!empty($f['certification_level'])) {
            $query->whereHas('pace.certificationLevel', fn ($q) => $q->where('slug', $f['certification_level']));
        }

        if (!empty($f['certification_type'])) {
            $query->whereHas('pace.certificationLevel.certificationType', fn ($q) => $q->where('slug', $f['certification_type']));
        }

        if (!empty($f['mode']) || !empty($f['country']) || !empty($f['city'])) {
            $query->whereHas('cohorts', function ($cohorts) use ($f) {
                self::upcoming($cohorts);
                if (!empty($f['mode'])) {
                    $cohorts->where('mode', $f['mode']);
                }
                if (!empty($f['country'])) {
                    $cohorts->where('location_country', $f['country']);
                }
                if (!empty($f['city'])) {
                    $cohorts->where('location_city', $f['city']);
                }
            });
        }

        if (!empty($f['tool'])) {
            $query->whereHas('tools', fn ($tools) => $tools->where('tools.slug', $f['tool']));
        }

        if (($f['licences'] ?? '') === 'with') {
            $query->has('tools');
        } elseif (($f['licences'] ?? '') === 'without') {
            $query->doesntHave('tools');
        }

        return $query;
    }

    /** Cohorts a learner could still join or see as upcoming. */
    public static function upcoming($cohorts)
    {
        return $cohorts->whereDate('start_date', '>=', now()->toDateString())
            ->whereNotIn('status', ['completed', 'closed']);
    }

    /**
     * Titles only - for the browse menu, where a subject expands into its
     * list of courses. Much lighter than the card listing.
     */
    public function compact(int $perPage)
    {
        return $this->query()
            ->select('courses.id', 'courses.code', 'courses.title')
            ->orderBy('courses.title')
            ->orderBy('courses.id')
            ->paginate($perPage);
    }

    public function paginate(string $sort, int $perPage)
    {
        $today = now()->toDateString();
        $upcoming = fn () => Cohort::query()
            ->whereColumn('cohorts.course_id', 'courses.id')
            ->whereNull('cohorts.deleted_at')
            ->whereDate('cohorts.start_date', '>=', $today)
            ->whereNotIn('cohorts.status', ['completed', 'closed']);

        $query = $this->query()
            ->select(array_map(fn ($c) => "courses.$c", self::LIST_COLUMNS))
            // "From" price: the cheapest upcoming cohort, else the course price.
            ->selectSub($upcoming()->selectRaw('min(coalesce(cohorts.price, courses.price))'), 'from_price')
            ->selectSub($upcoming()->selectRaw('min(cohorts.start_date)'), 'next_start_date')
            ->selectSub($upcoming()->where('cohorts.mode', 'physical')->selectRaw('count(*)'), 'physical_cohorts_count')
            ->selectSub($upcoming()->where('cohorts.mode', 'virtual')->selectRaw('count(*)'), 'virtual_cohorts_count')
            ->selectSub(
                DB::table('course_tools')
                    ->join('tools', 'tools.id', '=', 'course_tools.tool_id')
                    ->whereColumn('course_tools.course_id', 'courses.id')
                    ->whereNull('tools.deleted_at')
                    ->selectRaw('coalesce(sum(coalesce(course_tools.licence_price, tools.licence_price)), 0)'),
                'licence_total'
            )
            ->with(['category:id,name,slug,classification', 'tools:tools.id,tools.name,tools.slug']);

        match ($sort) {
            'price_asc' => $query->orderByRaw('coalesce(from_price, courses.price) asc'),
            'price_desc' => $query->orderByRaw('coalesce(from_price, courses.price) desc'),
            'newest' => $query->orderByDesc('courses.published_at'),
            // Courses with no upcoming cohort go last.
            'starting_soon' => $query->orderByRaw('next_start_date is null, next_start_date asc'),
            default => $query->orderBy('courses.title'),
        };

        return $query->orderBy('courses.id')->paginate($perPage);
    }

    /**
     * Counts for every filter group, each computed without its own selection
     * so options stay switchable.
     */
    public function facets(): array
    {
        $classifications = $this->query(['classification', 'category'])
            ->select('courses.classification', DB::raw('count(*) as total'))
            ->groupBy('courses.classification')
            ->pluck('total', 'classification');

        $categoryCounts = $this->query(['category'])
            ->whereNotNull('courses.category_id')
            ->select('courses.category_id', DB::raw('count(*) as total'))
            ->groupBy('courses.category_id')
            ->pluck('total', 'category_id');

        $categoriesQuery = Category::query()->orderBy('name');
        if (!empty($this->filters['classification'])) {
            $categoriesQuery->where('classification', $this->filters['classification']);
        }
        $categories = $categoriesQuery->get(['id', 'name', 'slug', 'classification'])
            ->map(fn ($category) => [
                'slug' => $category->slug,
                'name' => $category->name,
                'classification' => $category->classification,
                'count' => (int) ($categoryCounts[$category->id] ?? 0),
            ])
            ->filter(fn ($category) => $category['count'] > 0 || $category['slug'] === ($this->filters['category'] ?? null))
            ->values();

        $cohortsFor = fn (array $except) => self::upcoming(
            Cohort::query()->whereIn('course_id', $this->query($except)->select('courses.id'))
        );

        $modes = $cohortsFor(['mode'])
            ->select('mode', DB::raw('count(distinct course_id) as total'))
            ->groupBy('mode')
            ->pluck('total', 'mode');

        $locations = $cohortsFor(['country', 'city'])
            ->where('mode', 'physical')
            ->whereNotNull('location_country')
            ->select('location_country', 'location_city', DB::raw('count(distinct course_id) as total'))
            ->groupBy('location_country', 'location_city')
            ->orderBy('location_country')
            ->orderBy('location_city')
            ->get()
            ->groupBy('location_country')
            ->map(fn ($rows, $country) => [
                'country' => $country,
                'cities' => $rows->filter(fn ($row) => $row->location_city)
                    ->map(fn ($row) => ['city' => $row->location_city, 'count' => (int) $row->total])
                    ->values(),
            ])
            ->values();

        $toolCounts = DB::table('course_tools')
            ->whereIn('course_id', $this->query(['tool'])->select('courses.id'))
            ->select('tool_id', DB::raw('count(*) as total'))
            ->groupBy('tool_id')
            ->pluck('total', 'tool_id');

        $tools = Tool::whereIn('id', $toolCounts->keys())
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn ($tool) => ['slug' => $tool->slug, 'name' => $tool->name, 'count' => (int) $toolCounts[$tool->id]])
            ->values();

        $withLicences = $this->query(['licences', 'tool'])->has('tools')->count();

        return [
            'classifications' => [
                'o_level' => (int) ($classifications['o_level'] ?? 0),
                'a_level' => (int) ($classifications['a_level'] ?? 0),
                'skills_professional' => (int) ($classifications['skills_professional'] ?? 0),
            ],
            'categories' => $categories,
            'modes' => [
                'physical' => (int) ($modes['physical'] ?? 0),
                'virtual' => (int) ($modes['virtual'] ?? 0),
            ],
            'locations' => $locations,
            'tools' => $tools,
            'licences' => [
                'with' => $withLicences,
                'without' => $this->query(['licences', 'tool'])->count() - $withLicences,
            ],
        ];
    }
}
