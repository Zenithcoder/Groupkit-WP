<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PrimaryGroup represents that users having one primary group while they are planning to downgrade their plan
 * @package App
 */
class PrimaryGroup extends Model
{
    use HasFactory;

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'primary_group';

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = ['user_id', 'facebook_group_id', 'job_id'];
}
