<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Abandoned chunked uploads (closed tab, lost connection) would otherwise sit on disk forever.
Schedule::command('uploads:prune-chunks')->hourly()->withoutOverlapping();

// Tokens past their one-week life are already refused; this just removes the rows.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
