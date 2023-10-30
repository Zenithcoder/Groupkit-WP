<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PasswordResets represents user requests to change the password
 * @package App
 */
class PasswordResets extends Model
{
    use HasFactory;

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'password_resets';

    /**
     * @var boolean with timestamps fields (created_at and updated_at) or not
     */
    public $timestamps = false;

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = [
        'email',
        'token',
        'created_at',
    ];
}
