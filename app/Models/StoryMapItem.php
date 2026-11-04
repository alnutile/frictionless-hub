<?php

namespace App\Models;

use App\Enums\StoryMapKind;
use Database\Factories\StoryMapItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryMapItem extends Model
{
    /** @use HasFactory<StoryMapItemFactory> */
    use HasFactory;

    public const LEVEL_ACTIVITY = 'activity';

    public const LEVEL_TASK = 'task';

    public const LEVEL_CARD = 'card';

    /**
     * The level a child of each level must have.
     *
     * @var array<string, string>
     */
    public const CHILD_LEVEL = [
        self::LEVEL_ACTIVITY => self::LEVEL_TASK,
        self::LEVEL_TASK => self::LEVEL_CARD,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'level',
        'kind',
        'title',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => StoryMapKind::class,
            'position' => 'integer',
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
     * @return BelongsTo<StoryMapItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(StoryMapItem::class, 'parent_id');
    }

    /**
     * @return HasMany<StoryMapItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(StoryMapItem::class, 'parent_id')->orderBy('position');
    }
}
