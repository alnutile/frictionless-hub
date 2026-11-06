<?php

namespace App\Models;

use App\Enums\InterviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notes from one listening-tour conversation. Contents identify a person, so only
 * people who can manage the initiative see them.
 */
class Interview extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'participant_id',
        'interviewer_id',
        'conducted_at',
        'status',
        'responsibilities',
        'tools',
        'transcript',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conducted_at' => 'datetime',
            'status' => InterviewStatus::class,
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
     * @return BelongsTo<Participant, $this>
     */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    /**
     * @return HasMany<InterviewNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(InterviewNote::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Alias of notes(), used by scoped route binding for {interviewNote}.
     *
     * @return HasMany<InterviewNote, $this>
     */
    public function interviewNotes(): HasMany
    {
        return $this->notes();
    }
}
