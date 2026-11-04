<?php

namespace Tests\Feature;

use App\Ai\Agents\GuideAssistant;
use App\Models\AssistantMessage;
use App\Models\Initiative;
use App\Models\Setting;
use App\Models\User;
use App\Support\AiSettings;
use Database\Seeders\LarkspurScenarioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private Initiative $initiative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LarkspurScenarioSeeder::class);
        $this->initiative = Initiative::firstWhere('name', LarkspurScenarioSeeder::INITIATIVE_NAME);
        Setting::put(AiSettings::KEY, 'sk-or-test');
    }

    private function ask(User $user, string $message = 'What hurts most?', string $key = 'friction')
    {
        return $this->actingAs($user)->post(route('initiatives.assistant.store', $this->initiative), [
            'message' => $message,
            'activity_key' => $key,
        ]);
    }

    public function test_streams_a_reply_and_saves_the_conversation(): void
    {
        GuideAssistant::fake(['Start with the Hedgehog CI suite.']);

        $response = $this->ask($this->initiative->owner);

        $response->assertOk();
        $this->assertStringStartsWith('text/event-stream', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringContainsString('"type":"text_delta"', $body);
        $this->assertStringEndsWith("data: [DONE]\n\n", $body);

        $this->assertSame(
            [['user', 'What hurts most?'], ['assistant', 'Start with the Hedgehog CI suite.']],
            AssistantMessage::orderBy('id')->get()->map(fn ($m) => [$m->role, $m->content])->all(),
        );
    }

    public function test_the_model_receives_the_screen_guide_and_initiative_data_as_context(): void
    {
        GuideAssistant::fake(['ok']);

        $this->ask($this->initiative->owner)->streamedContent();

        GuideAssistant::assertPrompted(function ($prompt) {
            $instructions = (string) $prompt->agent->instructions();

            return str_contains($instructions, 'You are the Frictionless Hub assistant')
                && str_contains($instructions, '## Current screen: Log friction')
                && str_contains($instructions, 'Reading the heatmap')
                && str_contains($instructions, 'Hedgehog CI suite takes ~38 minutes')
                && str_contains($instructions, 'Role: Facilitator');
        });
    }

    public function test_an_edited_system_prompt_is_used(): void
    {
        GuideAssistant::fake(['ok']);
        Setting::put(AiSettings::PROMPT, 'Always answer in haiku.');

        $this->ask($this->initiative->owner)->streamedContent();

        GuideAssistant::assertPrompted(fn ($prompt) => str_starts_with((string) $prompt->agent->instructions(), 'Always answer in haiku.'));
    }

    public function test_earlier_messages_are_replayed_on_the_next_turn(): void
    {
        GuideAssistant::fake(['first answer', 'second answer']);
        $owner = $this->initiative->owner;

        $this->ask($owner, 'First question')->streamedContent();
        $this->ask($owner, 'Second question')->streamedContent();

        GuideAssistant::assertPrompted(fn ($prompt) => $prompt->contains('Second question')
            && collect($prompt->agent->messages())->map->content->all() === ['First question', 'first answer']);
    }

    public function test_viewers_can_ask_and_conversations_are_private_to_each_person(): void
    {
        GuideAssistant::fake(['for the viewer']);
        $viewer = User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN);

        $this->ask($viewer)->assertOk()->streamedContent();

        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.friction', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page->where('guide.assistant.messages', []));

        $this->actingAs($viewer)
            ->get(route('initiatives.friction', $this->initiative))
            ->assertInertia(fn (Assert $page) => $page
                ->where('guide.assistant.messages.1.content', 'for the viewer')
                ->where('guide.assistant.messages.1.html', "<p>for the viewer</p>\n"));
    }

    public function test_conversations_are_kept_per_initiative(): void
    {
        GuideAssistant::fake(['larkspur answer']);
        $this->ask($this->initiative->owner)->streamedContent();

        $other = Initiative::factory()->for($this->initiative->owner, 'owner')->create();

        $this->actingAs($this->initiative->owner)
            ->get(route('initiatives.show', $other))
            ->assertInertia(fn (Assert $page) => $page->where('guide.assistant.messages', []));
    }

    public function test_returns_a_clear_message_when_the_assistant_is_not_set_up(): void
    {
        Setting::put(AiSettings::KEY, null);

        $this->ask($this->initiative->owner)
            ->assertStatus(409)
            ->assertJson(['message' => 'The assistant is not set up yet. An admin can add an OpenRouter key under Admin → AI assistant.']);

        $this->assertSame(0, AssistantMessage::count());
    }

    public function test_provider_failures_become_a_readable_error_event(): void
    {
        GuideAssistant::fake(fn () => throw new RuntimeException('HTTP 401 Unauthorized'));

        $body = $this->ask($this->initiative->owner)->streamedContent();

        $this->assertStringContainsString('The AI provider rejected the API key.', $body);
        $this->assertSame(['user'], AssistantMessage::pluck('role')->all());
    }

    public function test_validates_the_message_and_activity(): void
    {
        $this->actingAs($this->initiative->owner)
            ->postJson(route('initiatives.assistant.store', $this->initiative), ['message' => '', 'activity_key' => 'nope'])
            ->assertJsonValidationErrors(['message', 'activity_key']);
    }

    public function test_clearing_removes_only_your_own_conversation(): void
    {
        $owner = $this->initiative->owner;
        $viewer = User::firstWhere('email', 'dana@'.LarkspurScenarioSeeder::EMAIL_DOMAIN);
        foreach ([$owner, $viewer] as $user) {
            AssistantMessage::create(['user_id' => $user->id, 'initiative_id' => $this->initiative->id, 'role' => 'user', 'content' => 'hi']);
        }

        $this->actingAs($owner)->delete(route('initiatives.assistant.destroy', $this->initiative));

        $this->assertSame([$viewer->id], AssistantMessage::pluck('user_id')->all());
    }

    public function test_the_initiatives_list_has_its_own_conversation(): void
    {
        GuideAssistant::fake(['general answer']);

        $this->actingAs($this->initiative->owner)
            ->post(route('assistant.store'), ['message' => 'How does this work?', 'activity_key' => 'start'])
            ->assertOk()
            ->streamedContent();

        $this->assertSame([null, null], AssistantMessage::pluck('initiative_id')->all());
    }
}
