<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin's override of a default guide file in resources/guide.
 */
class GuideEntry extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'body',
        'updated_by',
    ];
}
