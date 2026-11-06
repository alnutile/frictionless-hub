<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable message with {{merge_fields}}. Invites for now; the Sell phase will reuse it.
 */
class MessageTemplate extends Model
{
    public const PURPOSE_INVITE = 'invite';

    /**
     * Fields that can appear in a template body or subject.
     *
     * @var list<string>
     */
    public const MERGE_FIELDS = ['first_name', 'name', 'role', 'reason', 'facilitator', 'initiative'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purpose',
        'name',
        'channel',
        'subject',
        'body',
        'position',
    ];

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }
}
