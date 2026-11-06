<?php

namespace App\Models;

use App\Enums\NoteKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class InterviewNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'body',
        'workflow_stage_id',
        'owner',
        'done',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => NoteKind::class,
            'done' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    /**
     * @return BelongsTo<FrictionPoint, $this>
     */
    public function frictionPoint(): BelongsTo
    {
        return $this->belongsTo(FrictionPoint::class);
    }

    /**
     * @return BelongsToMany<Code, $this>
     */
    public function codes(): BelongsToMany
    {
        return $this->belongsToMany(Code::class)->orderBy('name');
    }
}
