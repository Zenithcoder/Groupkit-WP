<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class TeamMemberGroupAccess represents users that have access to specific group
 * @package App
 */
class TeamMemberGroupAccess extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'team_member_group_access';

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = ['user_id', 'facebook_group_id'];
}
