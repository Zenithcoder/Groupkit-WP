<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;

/**
 * Class GroupkitMailingListCredential represents stored oAuth credentials for mailing list
 * @package App
 */
class GroupkitMailingListCredential extends Model
{
    use HasFactory;

    /**
     * @var string[] The attributes that are mass assignable.
     */
    protected $fillable = [
        'client_id',
        'account_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    /**
     * Overrides the default behavior of setting 'update_at' column on each eloquent update
     * This model doesn't have the 'updated_at' column, so we are overriding the base method
     * @see \Illuminate\Database\Eloquent\Concerns\HasTimestamps::setUpdatedAt
     *
     * @param mixed $value current time, instance of {@see Date::now}
     *
     * @return GroupkitMailingListCredential $this
     */
    public function setUpdatedAt($value)
    {
        return $this;
    }
}
