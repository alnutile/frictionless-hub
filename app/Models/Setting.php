<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->first()?->value ?? $default;
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
