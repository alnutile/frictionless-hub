<?php

namespace App\Models;

use Database\Factories\WorkflowStageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStage extends Model
{
    /** @use HasFactory<WorkflowStageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
        'notes',
        'ai_assisted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_assisted' => 'boolean',
            'position' => 'integer',
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
     * @return HasMany<StageTool, $this>
     */
    public function tools(): HasMany
    {
        return $this->hasMany(StageTool::class);
    }

    /**
     * @return HasMany<FrictionPoint, $this>
     */
    public function frictionPoints(): HasMany
    {
        return $this->hasMany(FrictionPoint::class);
    }
}
