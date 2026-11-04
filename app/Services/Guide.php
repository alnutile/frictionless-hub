<?php

namespace App\Services;

use App\Models\GuideEntry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Guide text: an admin's saved version wins, otherwise the default file in resources/guide.
 */
class Guide
{
    public static function defaultBody(string $key): string
    {
        $path = resource_path("guide/{$key}.md");

        return File::exists($path) ? File::get($path) : '';
    }

    public static function body(string $key): string
    {
        return GuideEntry::where('key', $key)->value('body') ?? self::defaultBody($key);
    }

    public static function isCustomized(string $key): bool
    {
        return GuideEntry::where('key', $key)->exists();
    }

    /**
     * Render markdown with raw HTML stripped and unsafe links disabled.
     */
    public static function render(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}
