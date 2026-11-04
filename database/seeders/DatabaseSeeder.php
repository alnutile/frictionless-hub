<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed login accounts plus the Larkspur benchmark scenario.
     * Scenario content is original and fictional; no book or workbook material belongs here.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        User::factory()->facilitator()->create([
            'name' => 'Facilitator User',
            'email' => 'facilitator@example.com',
        ]);

        User::factory()->create([
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
        ]);

        $this->call(LarkspurScenarioSeeder::class);
    }
}
