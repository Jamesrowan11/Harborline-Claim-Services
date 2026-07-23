<?php

namespace App\Console\Commands;

use App\Services\ScheduledScanService;
use Illuminate\Console\Command;

class RunDailyScans extends Command
{
    protected $signature = 'hcs:daily-scans';
    protected $description = 'Scan for approaching deadlines, overdue tasks, stale sources, inactive cases, and expired verifications, and fire automation events';

    public function handle(ScheduledScanService $scans): int
    {
        $counts = $scans->run();
        $this->info('Scan complete: '.json_encode($counts));

        return self::SUCCESS;
    }
}
