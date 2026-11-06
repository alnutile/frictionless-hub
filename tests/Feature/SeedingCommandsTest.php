<?php

namespace Tests\Feature;

use App\Models\Initiative;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiSettings;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeedingCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_if_empty_seeds_a_new_database(): void
    {
        $this->artisan('app:seed-if-empty')->assertSuccessful();

        $this->assertTrue(User::where('email', 'admin@example.com')->exists());
        $this->assertTrue(Initiative::where('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->exists());
    }

    public function test_seed_if_empty_leaves_existing_data_alone(): void
    {
        $initiative = Initiative::factory()->create(['name' => 'My real work']);

        $this->artisan('app:seed-if-empty')->assertSuccessful();

        $this->assertModelExists($initiative);
        $this->assertFalse(Initiative::where('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->exists());
    }

    public function test_refresh_scenario_rebuilds_larkspur_but_keeps_other_initiatives_and_settings(): void
    {
        $this->seed(LarkspurScenarioSeeder::class);
        $mine = Initiative::factory()->create(['name' => 'My real work']);
        Setting::put(AiSettings::KEY, 'sk-or-keep-me');
        Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->update(['description' => 'edited during QA']);

        $this->artisan('app:refresh-scenario', ['--force' => true])->assertSuccessful();

        $this->assertModelExists($mine);
        $this->assertSame('sk-or-keep-me', AiSettings::apiKey());
        $this->assertSame(1, Initiative::where('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->count());
        $this->assertNotSame('edited during QA', Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME)->description);
    }

    public function test_a_setting_encrypted_with_another_key_reads_as_unset_instead_of_crashing(): void
    {
        DB::table('settings')->insert(['key' => AiSettings::KEY, 'value' => 'not-decryptable', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertFalse(AiSettings::isConfigured());

        $this->actingAs(User::factory()->create())->get(route('initiatives.index'))->assertOk();
    }
}
