<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\File;

/**
 * Admin-managed configuration for the assistant. The API key never leaves the server.
 */
class AiSettings
{
    public const KEY = 'ai.openrouter_key';

    public const MODEL = 'ai.model';

    public const PROMPT = 'ai.system_prompt';

    public const DEFAULT_MODEL = 'openrouter/auto';

    public static function apiKey(): ?string
    {
        return Setting::get(self::KEY);
    }

    public static function isConfigured(): bool
    {
        return filled(self::apiKey());
    }

    public static function model(): string
    {
        return Setting::get(self::MODEL) ?: self::DEFAULT_MODEL;
    }

    public static function defaultPrompt(): string
    {
        return File::get(resource_path('prompts/assistant.md'));
    }

    public static function systemPrompt(): string
    {
        return Setting::get(self::PROMPT) ?: self::defaultPrompt();
    }

    public static function promptIsCustomized(): bool
    {
        return filled(Setting::get(self::PROMPT));
    }

    /**
     * The last four characters, so admins can tell which key is saved without exposing it.
     */
    public static function maskedKey(): ?string
    {
        $key = self::apiKey();

        return $key ? '••••'.substr($key, -4) : null;
    }

    /**
     * Point the AI SDK's OpenRouter provider at the stored key for this request.
     */
    public static function applyToRuntime(): void
    {
        config(['ai.providers.openrouter.key' => self::apiKey()]);
    }
}
