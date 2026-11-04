<?php

namespace App\Models;

use App\Enums\Phase;
use Database\Factories\InitiativeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Initiative extends Model
{
    /** @use HasFactory<InitiativeFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'scope',
        'description',
        'phase',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phase' => Phase::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<WorkflowStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(WorkflowStage::class)->orderBy('position');
    }

    /**
     * @return HasMany<Dimension, $this>
     */
    public function dimensions(): HasMany
    {
        return $this->hasMany(Dimension::class)->orderBy('position');
    }

    /**
     * @return HasMany<FrictionPoint, $this>
     */
    public function frictionPoints(): HasMany
    {
        return $this->hasMany(FrictionPoint::class);
    }

    /**
     * @return HasMany<StoryMapItem, $this>
     */
    public function storyMapItems(): HasMany
    {
        return $this->hasMany(StoryMapItem::class);
    }

    /**
     * @return HasMany<RatingGroup, $this>
     */
    public function ratingGroups(): HasMany
    {
        return $this->hasMany(RatingGroup::class)->orderBy('position');
    }

    /**
     * @return HasManyThrough<RatingItem, RatingGroup, $this>
     */
    public function ratingItems(): HasManyThrough
    {
        return $this->hasManyThrough(RatingItem::class, RatingGroup::class);
    }

    /**
     * @return HasMany<ActivityNote, $this>
     */
    public function activityNotes(): HasMany
    {
        return $this->hasMany(ActivityNote::class);
    }

    /**
     * Seed the configurable starting stages and dimensions for a new initiative.
     */
    public function seedDefaults(): void
    {
        foreach (config('frictionless.default_stages') as $position => $name) {
            $this->stages()->create(['name' => $name, 'position' => $position]);
        }

        foreach (config('frictionless.default_dimensions') as $position => $name) {
            $this->dimensions()->create(['name' => $name, 'position' => $position]);
        }
    }
}
