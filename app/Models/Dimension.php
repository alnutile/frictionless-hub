<?php

namespace App\Models;

use Database\Factories\DimensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Dimension extends Model
{
    /** @use HasFactory<DimensionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
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
     * @return BelongsToMany<FrictionPoint, $this>
     */
    public function frictionPoints(): BelongsToMany
    {
        return $this->belongsToMany(FrictionPoint::class);
    }
}
