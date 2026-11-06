<?php

namespace App\Models;

use App\Enums\Level;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A finding from the interviews: a named group of codes that point at the same underlying problem.
 */
class Theme extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'summary',
        'impact',
        'effort',
        'solutions',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'impact' => Level::class,
            'effort' => Level::class,
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
     * @return HasMany<Code, $this>
     */
    public function codes(): HasMany
    {
        return $this->hasMany(Code::class)->orderBy('name');
    }

    /**
     * @return BelongsTo<FrictionPoint, $this>
     */
    public function frictionPoint(): BelongsTo
    {
        return $this->belongsTo(FrictionPoint::class);
    }
}
