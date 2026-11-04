<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SalesLeadManagementSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [
                'key' => 'lead_batch_size',
                'value' => '3',
                'description' => 'Default number of leads assigned in a batch.',
            ],
            [
                'key' => 'assignment_cutoff_minutes',
                'value' => '30',
                'description' => 'Minutes before shift end when new lead assignment stops.',
            ],
        ] as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value'], 'description' => $setting['description']],
            );
        }
    }
}
