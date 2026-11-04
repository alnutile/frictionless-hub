<?php

namespace App\Http\Controllers;

use App\Ai\Agents\GuideAssistant;
use App\Models\AssistantMessage;
use App\Models\Initiative;
use App\Services\AssistantContext;
use App\Support\AiSettings;
use App\Support\Walkthrough;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Ai\Streaming\Events\TextDelta;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AssistantController extends Controller
{
    /**
     * How many earlier messages are replayed to the model on each turn.
     */
    public const HISTORY_LIMIT = 20;

    /**
     * Stream the assistant's reply as server-sent events (one JSON event per `data:` line).
     */
    public function store(Request $request, ?Initiative $initiative = null): StreamedResponse|JsonResponse
    {
        if ($initiative) {
            $this->authorize('view', $initiative);
        }

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'activity_key' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! Walkthrough::isKnownKey($value)) {
                    $fail('Unknown activity.');
                }
            }],
        ]);

        if (! AiSettings::isConfigured()) {
            return response()->json(['message' => 'The assistant is not set up yet. An admin can add an OpenRouter key under Admin → AI assistant.'], 409);
        }

        $user = $request->user();
        $history = AssistantMessage::conversation($user, $initiative)->latest('id')->limit(self::HISTORY_LIMIT)->get()->reverse()->values();

        AssistantMessage::create([
            'user_id' => $user->id,
            'initiative_id' => $initiative?->id,
            'role' => AssistantMessage::ROLE_USER,
            'content' => $validated['message'],
            'activity_key' => $validated['activity_key'],
        ]);

        AiSettings::applyToRuntime();

        $agent = new GuideAssistant(AssistantContext::build($user, $initiative, $validated['activity_key']), $history);
        $stream = $agent->stream($validated['message'], provider: 'openrouter', model: AiSettings::model());

        return response()->stream(function () use ($stream, $user, $initiative, $validated) {
            $reply = '';

            try {
                foreach ($stream as $event) {
                    if ($event instanceof TextDelta) {
                        $reply .= $event->delta;
                    }

                    yield 'data: '.$event."\n\n";
                }
            } catch (Throwable $exception) {
                report($exception);

                yield 'data: '.json_encode(['type' => 'error', 'message' => self::friendlyError($exception)])."\n\n";
            }

            if ($reply !== '') {
                AssistantMessage::create([
                    'user_id' => $user->id,
                    'initiative_id' => $initiative?->id,
                    'role' => AssistantMessage::ROLE_ASSISTANT,
                    'content' => $reply,
                    'activity_key' => $validated['activity_key'],
                ]);
            }

            yield "data: [DONE]\n\n";
        }, headers: [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * Clear the user's own conversation for this initiative (or the initiatives list).
     */
    public function destroy(Request $request, ?Initiative $initiative = null): RedirectResponse
    {
        if ($initiative) {
            $this->authorize('view', $initiative);
        }

        AssistantMessage::conversation($request->user(), $initiative)->delete();

        return back();
    }

    private static function friendlyError(Throwable $exception): string
    {
        $message = Str::lower($exception->getMessage());

        return match (true) {
            str_contains($message, '401') || str_contains($message, 'unauthorized') || str_contains($message, 'api key') => 'The AI provider rejected the API key. An admin can check it under Admin → AI assistant.',
            str_contains($message, '404') || str_contains($message, 'model') => 'The configured model is not available. An admin can change it under Admin → AI assistant.',
            str_contains($message, '429') || str_contains($message, 'rate') => 'The AI provider is rate limiting requests. Try again in a moment.',
            default => 'The assistant could not reply just now. Try again in a moment.',
        };
    }
}
