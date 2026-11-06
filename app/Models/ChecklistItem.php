<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pre-interview preparation step, checked off per participant.
 */
class ChecklistItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'position',
    ];

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }
}
