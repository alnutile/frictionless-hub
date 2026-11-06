<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A short tag applied to interview notes, e.g. "slow-ci". Themes in step 7 are built from these.
 */
class Code extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * @return BelongsTo<Initiative, $this>
     */
    public function initiative(): BelongsTo
    {
        return $this->belongsTo(Initiative::class);
    }

    /**
     * @return BelongsToMany<InterviewNote, $this>
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(InterviewNote::class);
    }
}
