<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\User;
use App\Services\AssistantContext;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_person_walkthrough_guide_data_and_notes(): void
    {
        $this->seed(LarkspurScenarioSeeder::class);
        $initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);
        $initiative->activityNotes()->create(['activity_key' => 'friction', 'body' => 'Raise CI with Dana first.']);
        $viewer = User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN);

        $context = AssistantContext::build($viewer, $initiative, 'story-map');

        $this->assertStringContainsString('- Role: Viewer', $context);
        $this->assertStringContainsString('- Can edit this initiative: no', $context);
        $this->assertStringContainsString('3. [Discover] Story map ← current screen', $context);
        $this->assertStringContainsString('6. [Discover] Interviews (not built yet)', $context);
        $this->assertStringContainsString('## Current screen: Story map', $context);
        $this->assertStringContainsString('- Coding [AI-assisted]: VS Code, GitHub, Cursor (AI), Copilot (AI), Local Docker stack', $context);
        $this->assertStringContainsString('Hedgehog CI suite takes ~38 minutes | Building | Multiple times a day | 5 |', $context);
        $this->assertStringContainsString('- Ship it', $context);
        $this->assertStringContainsString('Laptop setup to first commit: average 3 from 5 people (spread 3)', $context);
        $this->assertStringContainsString('- Log friction: Raise CI with Dana first.', $context);
    }

    public function test_works_without_an_initiative_and_stays_within_the_size_limit(): void
    {
        $context = AssistantContext::build(User::factory()->create(), null, 'start');

        $this->assertStringContainsString('## Current screen: How Frictionless Hub works', $context);
        $this->assertStringNotContainsString('## Initiative:', $context);
        $this->assertLessThanOrEqual(AssistantContext::MAX_CHARACTERS + 30, mb_strlen($context));
    }
}
