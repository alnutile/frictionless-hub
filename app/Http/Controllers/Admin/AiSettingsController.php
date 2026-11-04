<?php

namespace App\Http\Controllers\Admin;

use App\Ai\Agents\GuideAssistant;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\AiSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/ai', [
            'maskedKey' => AiSettings::maskedKey(),
            'model' => AiSettings::model(),
            'defaultModel' => AiSettings::DEFAULT_MODEL,
            'systemPrompt' => AiSettings::systemPrompt(),
            'defaultPrompt' => AiSettings::defaultPrompt(),
            'promptCustomized' => AiSettings::promptIsCustomized(),
        ]);
    }

    /**
     * A blank key keeps the saved one; remove_key clears it. A blank prompt restores the default.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'api_key' => ['nullable', 'string', 'max:500'],
            'remove_key' => ['boolean'],
            'model' => ['required', 'string', 'max:200'],
            'system_prompt' => ['nullable', 'string', 'max:20000'],
        ]);

        $userId = $request->user()->id;

        if ($request->boolean('remove_key')) {
            Setting::put(AiSettings::KEY, null);
        } elseif (filled($validated['api_key'] ?? null)) {
            Setting::put(AiSettings::KEY, trim($validated['api_key']), $userId);
        }

        Setting::put(AiSettings::MODEL, trim($validated['model']), $userId);

        $prompt = trim((string) ($validated['system_prompt'] ?? ''));
        Setting::put(AiSettings::PROMPT, $prompt === '' || $prompt === trim(AiSettings::defaultPrompt()) ? null : $prompt, $userId);

        return back()->with('success', 'AI settings saved.');
    }

    /**
     * Send a tiny prompt with the saved settings to confirm the key and model work.
     */
    public function test(): RedirectResponse
    {
        if (! AiSettings::isConfigured()) {
            return back()->withErrors(['api_key' => 'Save an OpenRouter API key first.']);
        }

        AiSettings::applyToRuntime();

        try {
            $response = (new GuideAssistant('This is a connection test.'))
                ->prompt('Reply with the single word OK.', provider: 'openrouter', model: AiSettings::model(), timeout: 30);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['test' => 'The connection test failed: '.str($exception->getMessage())->limit(200)]);
        }

        return back()->with('success', 'Connection works. The model replied: '.str($response->text)->limit(80));
    }
}
