<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * A workspace-wide key/value setting. Values are always encrypted at rest.
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'updated_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
        ];
    }

    /**
     * A value encrypted under a different APP_KEY can't be read; treat it as unset
     * (and log it) rather than breaking every page.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            return static::where('key', $key)->first()?->value ?? $default;
        } catch (DecryptException) {
            Log::warning("Setting [{$key}] could not be decrypted; was APP_KEY changed? Re-enter it in the admin settings.");

            return $default;
        }
    }

    public static function put(string $key, ?string $value, ?int $userId = null): void
    {
        if ($value === null) {
            static::where('key', $key)->delete();

            return;
        }

        static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
    }
}
