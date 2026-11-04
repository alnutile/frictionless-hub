<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'initiative_id',
        'role',
        'content',
        'activity_key',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The conversation a user has within one initiative (or on the initiatives list when null).
     *
     * @param  Builder<AssistantMessage>  $query
     */
    public function scopeConversation(Builder $query, User $user, ?Initiative $initiative): void
    {
        $query->where('user_id', $user->id)
            ->when(
                $initiative,
                fn (Builder $q) => $q->where('initiative_id', $initiative->id),
                fn (Builder $q) => $q->whereNull('initiative_id'),
            );
    }
}
