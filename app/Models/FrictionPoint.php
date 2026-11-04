<?php

namespace App\Models;

use App\Enums\Frequency;
use Database\Factories\FrictionPointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FrictionPoint extends Model
{
    /** @use HasFactory<FrictionPointFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workflow_stage_id',
        'title',
        'description',
        'frequency',
        'pain',
        'affected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => Frequency::class,
            'pain' => 'integer',
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
     * @return BelongsTo<WorkflowStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    /**
     * @return BelongsToMany<Dimension, $this>
     */
    public function dimensions(): BelongsToMany
    {
        return $this->belongsToMany(Dimension::class);
    }
}
