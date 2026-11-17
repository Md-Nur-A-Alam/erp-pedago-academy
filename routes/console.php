<?php

use App\Actions\DecideLeadTransfer;
use App\Actions\DistributeLeads;
use App\Models\SystemSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

Schedule::command('leads:distribute')->everyMinute()->withoutOverlapping();
Schedule::command('transfers:auto-approve')->hourly()->withoutOverlapping();
