<?php

namespace Database\Seeders;

use App\Enums\Frequency;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed local demo data. Content is neutral placeholder text only;
     * no book or workbook material belongs here (see docs/SPEC.md).
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
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

        $initiative = $admin->initiatives()->create([
            'name' => 'Demo initiative',
            'scope' => 'Sample engineering org',
            'description' => 'Placeholder initiative for local development.',
        ]);
        $initiative->seedDefaults();

        $stages = $initiative->stages()->pluck('id', 'name');
        $dimensions = $initiative->dimensions()->pluck('id')->all();

        $stage = $initiative->stages()->firstWhere('name', 'Coding');
        $stage->tools()->createMany([
            ['name' => 'Sample editor'],
            ['name' => 'Sample code assistant', 'is_ai' => true],
        ]);

        $samples = [
            ['Sample friction A', 'Building', Frequency::Daily, 5, [0, 1]],
            ['Sample friction B', 'Testing', Frequency::Daily, 4, [1]],
            ['Sample friction C', 'Reviewing', Frequency::Weekly, 3, [0]],
            ['Sample friction D', 'Coding', Frequency::Daily, 2, [2]],
        ];

        foreach ($samples as [$title, $stageName, $frequency, $pain, $dimensionIndexes]) {
            $point = $initiative->frictionPoints()->create([
                'title' => $title,
                'workflow_stage_id' => $stages[$stageName],
                'frequency' => $frequency,
                'pain' => $pain,
                'affected' => 'Sample team',
            ]);
            $point->dimensions()->sync(array_map(fn (int $i) => $dimensions[$i], $dimensionIndexes));
        }
    }
}
