<?php

namespace App\Console\Commands;

use App\Services\WorkspaceResumableUploadService;
use Illuminate\Console\Command;

class CleanupWorkspaceUploads extends Command
{
    protected $signature = 'workspace:uploads-cleanup';
    protected $description = 'Clean expired/cancelled/completed private upload staging and old result tombstones';

    public function handle(WorkspaceResumableUploadService $uploads): int
    {
        $this->info('Upload sessions checked: '.$uploads->cleanup());
        return self::SUCCESS;
    }
}
