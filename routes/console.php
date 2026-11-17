<?php

use App\Actions\DecideLeadTransfer;
use App\Actions\DistributeLeads;
use App\Actions\SendFollowUpNotices;
use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('leads:distribute {--force : Run even when automatic distribution is switched off}', function () {
    if (! $this->option('force') && ! SystemSetting::enabled('distribution_engine_enabled')) {
        $this->comment('Automatic distribution is switched off. Nothing was assigned.');

        return 0;
    }

    $result = app(DistributeLeads::class)->run();
    $this->info("Distributed {$result['leads']} leads in {$result['batches']} batches.");

    return 0;
})->purpose('Run one lead distribution cycle');

Artisan::command('transfers:auto-approve', function () {
    $approved = app(DecideLeadTransfer::class)->approveOverdue();
    $this->info("Approved {$approved} transfers that passed the waiting period.");

    return 0;
})->purpose('Approve pending lead transfers that have waited the configured number of days');

// Proves the scheduler is running: the Central Queue warns when this stops ticking.
Schedule::call(fn () => Cache::put('scheduler.heartbeat', now()->timestamp, 3600))->everyMinute()->name('scheduler-heartbeat');
Artisan::command('notifications:followups', function () {
    $result = app(SendFollowUpNotices::class)->run();
    $this->info("Reminders {$result['reminders']}, overdue {$result['overdue']}, repeats {$result['repeats']}, lazy alerts {$result['lazy']}, cleared {$result['cleared']}.");

    return 0;
})->purpose('Send follow-up reminders, overdue notices and Lazy alerts');

Schedule::command('notifications:followups')->everyMinute()->withoutOverlapping();
Schedule::command('leads:distribute')->everyMinute()->withoutOverlapping();
Schedule::command('transfers:auto-approve')->hourly()->withoutOverlapping();
