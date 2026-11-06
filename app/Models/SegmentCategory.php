<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A way of grouping people for interview coverage, e.g. work area or experience.
 * Values within one category are mutually exclusive.
 */
class SegmentCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_area',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_area' => 'boolean',
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
     * @return HasMany<SegmentValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(SegmentValue::class)->orderBy('position');
    }
}
