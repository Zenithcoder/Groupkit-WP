<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerToTeamMember extends Model
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
    protected $fillable = [
        'owner_id',
        'team_member_id',
    ];

    /**
     * One to one relationship between `users` table and `owner_to_team_members` pivot table
     *
     * @return BelongsTo returns owner user that this pivot model belongs to
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
