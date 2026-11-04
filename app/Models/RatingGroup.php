<?php

namespace App\Models;

use Database\Factories\RatingGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatingGroup extends Model
{
    /** @use HasFactory<RatingGroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
    ];

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    /**
     * @return HasMany<RatingItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RatingItem::class)->orderBy('position');
    }
}
