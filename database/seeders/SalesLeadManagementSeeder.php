<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SalesLeadManagementSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemSetting::DEFINITIONS as $key => $definition) {
            SystemSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $definition['default'], 'description' => $definition['description']],
            );
        }
    }
}
