<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    public const VALID_FOR_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'role',
        'invited_by',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Issue a new token (replacing any previous one) and return the plain token. It's only available now.
     */
    public function issueToken(): string
    {
        $token = Str::random(48);

        $this->forceFill([
            'token_hash' => self::hashToken($token),
            'expires_at' => now()->addDays(self::VALID_FOR_DAYS),
        ])->save();

        return $token;
    }

    public static function findUsable(string $token): ?self
    {
        return static::where('token_hash', self::hashToken($token))->pending()->first();
    }

    /**
     * Not yet accepted and not expired.
     *
     * @param  Builder<Invitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }
}
