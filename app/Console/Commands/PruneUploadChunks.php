<?php

namespace App\Console\Commands;

use App\Services\ChunkedUploads;
use Illuminate\Console\Command;

class PruneUploadChunks extends Command
{
    protected $signature = 'uploads:prune-chunks {--hours=24 : Remove unfinished uploads older than this}';

    protected $description = 'Delete chunk folders left behind by abandoned uploads';

    public function handle(): int
    {
        $removed = ChunkedUploads::prune((int) $this->option('hours'));
        $this->info("Removed {$removed} abandoned upload(s).");

        return self::SUCCESS;
    }
}
