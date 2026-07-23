<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            PipelineStageSeeder::class,
            DocumentTemplateSeeder::class,
            AutomationSeeder::class,
        ]);

        // Fictional demonstration data — safe for demos, never for production.
        if (app()->environment(['local', 'testing', 'staging']) || env('SEED_DEMO_DATA')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
