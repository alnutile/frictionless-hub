<?php

namespace App\Models;

use App\Enums\ParticipantStatus;
use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Someone on the listening tour. Identifying details are only shown to people who can manage the initiative.
 */
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'role',
        'status',
        'scheduled_at',
        'invite_reason',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ParticipantStatus::class,
            'scheduled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    /**
     * @return BelongsToMany<SegmentValue, $this>
     */
    public function segmentValues(): BelongsToMany
    {
        return $this->belongsToMany(SegmentValue::class);
    }

    /**
     * @return BelongsToMany<ChecklistItem, $this>
     */
    public function completedChecklistItems(): BelongsToMany
    {
        return $this->belongsToMany(ChecklistItem::class)->withPivot('completed_at');
    }
}
