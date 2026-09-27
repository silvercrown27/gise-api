<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cohorts are either Physical (at a city/country venue) or Virtual, and each
 * sets its own fee. Courses record which kinds of cohort they offer.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Loosen to plain strings so values can be rewritten portably.
        Schema::table('cohorts', fn (Blueprint $table) => $table->string('mode', 20)->default('virtual')->change());
        Schema::table('courses', fn (Blueprint $table) => $table->string('mode', 20)->default('virtual')->change());

        DB::table('cohorts')->where('mode', 'online')->update(['mode' => 'virtual']);
        DB::table('cohorts')->whereIn('mode', ['in_person', 'hybrid'])->update(['mode' => 'physical']);
        DB::table('courses')->where('mode', 'online')->update(['mode' => 'virtual']);
        DB::table('courses')->where('mode', 'in_person')->update(['mode' => 'physical']);
        DB::table('courses')->where('mode', 'hybrid')->update(['mode' => 'both']);

        Schema::table('cohorts', fn (Blueprint $table) => $table->enum('mode', ['physical', 'virtual'])->default('virtual')->change());
        Schema::table('courses', fn (Blueprint $table) => $table->enum('mode', ['physical', 'virtual', 'both'])->default('virtual')->change());

        Schema::table('cohorts', function (Blueprint $table) {
            // Null fee means "use the course price".
            $table->unsignedInteger('price')->nullable()->after('mode');
            $table->string('location_city')->nullable()->after('location_country');
            $table->index(['course_id', 'mode']);
        });

        // Carry today's course price onto existing cohorts so nothing shows as free.
        DB::table('cohorts')->update([
            'price' => DB::raw('(select price from courses where courses.id = cohorts.course_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'mode']);
            $table->dropColumn(['price', 'location_city']);
        });

        Schema::table('cohorts', fn (Blueprint $table) => $table->string('mode', 20)->default('online')->change());
        Schema::table('courses', fn (Blueprint $table) => $table->string('mode', 20)->default('online')->change());
        DB::table('cohorts')->where('mode', 'virtual')->update(['mode' => 'online']);
        DB::table('cohorts')->where('mode', 'physical')->update(['mode' => 'in_person']);
        DB::table('courses')->where('mode', 'virtual')->update(['mode' => 'online']);
        DB::table('courses')->where('mode', 'physical')->update(['mode' => 'in_person']);
        DB::table('courses')->where('mode', 'both')->update(['mode' => 'hybrid']);
        Schema::table('cohorts', fn (Blueprint $table) => $table->enum('mode', ['online', 'in_person', 'hybrid'])->default('online')->change());
        Schema::table('courses', fn (Blueprint $table) => $table->enum('mode', ['online', 'in_person', 'hybrid'])->default('online')->change());
    }
};
