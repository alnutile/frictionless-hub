<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentValue extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'position',
    ];

    /**
     * @return BelongsTo<SegmentCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SegmentCategory::class, 'segment_category_id');
    }
}
