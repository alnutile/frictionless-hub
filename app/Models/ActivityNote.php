<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An initiative's own notes and reflections for one walkthrough activity.
 */
class ActivityNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_key',
        'body',
        'updated_by',
    ];

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }
}
