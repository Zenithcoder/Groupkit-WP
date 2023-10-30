<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Class Tag represents tags that customers use as categories for {@see GroupMembers}
 *
 * @package App
 */
class Tag extends Model
{
    use HasFactory;

    /**
     * @var bool sets timestamps (created_at, updated_at) to false
     * to prevent inserting timestamps when do mass assignment with Model
     */
    public $timestamps = false;

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = ['label', 'group_id', 'is_recommended'];

    /**
     * Tag belongs to many group members.
     *
     * @return BelongsToMany
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(
            GroupMembers::class,
            'group_members_tags',
            'tag_id',
            'group_member_id'
        );
    }
}
