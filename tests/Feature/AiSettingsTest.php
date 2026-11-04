<?php

namespace Tests\Feature;

use App\Ai\Agents\GuideAssistant;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-or-v1-test-secret-key-1234';

    private function save(User $admin, array $overrides = [])
    {
        return $this->actingAs($admin)->put(route('admin.ai.update'), [
            'api_key' => self::KEY,
            'model' => 'openrouter/auto',
            'system_prompt' => AiSettings::defaultPrompt(),
            ...$overrides,
        ]);
    }

    public function test_only_admins_can_see_or_change_ai_settings(): void
    {
        $facilitator = User::factory()->facilitator()->create();

        $this->actingAs($facilitator)->get(route('admin.ai.edit'))->assertForbidden();
        $this->actingAs($facilitator)->put(route('admin.ai.update'), ['model' => 'x'])->assertForbidden();
    }

    public function test_key_is_encrypted_at_rest_and_never_sent_to_the_browser(): void
    {
        $admin = User::factory()->admin()->create();

        $this->save($admin)->assertSessionHasNoErrors();

        $this->assertSame(self::KEY, AiSettings::apiKey());
        $this->assertStringNotContainsString(self::KEY, (string) DB::table('settings')->where('key', AiSettings::KEY)->value('value'));

        $response = $this->actingAs($admin)->get(route('admin.ai.edit'));
        $response->assertInertia(fn (Assert $page) => $page->where('maskedKey', '••••1234'));
        $this->assertStringNotContainsString(self::KEY, $response->getContent());
    }

    public function test_blank_key_keeps_the_saved_key_and_remove_key_clears_it(): void
    {
        $admin = User::factory()->admin()->create();
        $this->save($admin);

        $this->save($admin, ['api_key' => '']);
        $this->assertSame(self::KEY, AiSettings::apiKey());

        $this->save($admin, ['api_key' => '', 'remove_key' => true]);
        $this->assertFalse(AiSettings::isConfigured());
    }

    public function test_default_prompt_is_not_stored_but_an_edited_one_is(): void
    {
        $admin = User::factory()->admin()->create();

        $this->save($admin);
        $this->assertFalse(AiSettings::promptIsCustomized());

        $this->save($admin, ['system_prompt' => 'Be terse.', 'model' => 'anthropic/some-model']);
        $this->assertSame('Be terse.', AiSettings::systemPrompt());
        $this->assertSame('anthropic/some-model', AiSettings::model());

        $this->save($admin, ['system_prompt' => '']);
        $this->assertSame(AiSettings::defaultPrompt(), AiSettings::systemPrompt());
    }

    public function test_connection_test_reports_success_using_the_saved_settings(): void
    {
        GuideAssistant::fake(['OK']);
        $admin = User::factory()->admin()->create();
        Setting::put(AiSettings::KEY, self::KEY);

        $this->actingAs($admin)
            ->post(route('admin.ai.test'))
            ->assertSessionHas('success', 'Connection works. The model replied: OK');

        GuideAssistant::assertPrompted(fn ($prompt) => $prompt->contains('Reply with the single word OK.'));
    }

    public function test_connection_test_requires_a_key(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.ai.test'))
            ->assertSessionHasErrors(['api_key' => 'Save an OpenRouter API key first.']);
    }
}
