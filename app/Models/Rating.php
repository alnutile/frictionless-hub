<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'score',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RatingItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(RatingItem::class, 'rating_item_id');
    }
}
