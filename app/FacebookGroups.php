<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class FacebookGroups class represents a Facebook group and the associated meta data.
 * @package App
 */
class FacebookGroups extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'facebook_groups';

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     * @todo review code to see if we can safely set user_id in a mutator and remove it from here
     */
    protected $fillable = ['fb_id', 'fb_name', 'img', 'user_id', 'questionOne', 'questionTwo', 'questionThree'];

    public function members()
    {
        return $this->hasMany('App\GroupMembers', 'group_id', 'id')
            ->orderBy('date_add_time', 'desc');
    }

    /**
     * Get count of the members for current group
     *
     * @return HasOne with the members count
     */
    public function membersCount(): HasOne
    {
        return $this->hasOne(GroupMembers::class, 'group_id')
            ->selectRaw('group_id, count(*) as members')
            ->groupBy('group_id');
    }

    public function responder()
    {
        return $this->hasMany(AutoResponder::class, 'group_id', 'id');
    }

    /**
     * Returns all autoresponder which type is GoogleSheet
     *
     * @return HasMany autoresponders connected to the group
     */
    public function googleSheetIntegration(): HasMany
    {
        return $this->hasMany(AutoResponder::class, 'group_id', 'id')
            ->whereNull('deleted_at')
            ->where('responder_type', 'GoogleSheet');
    }

    /**
     * Returns all autoresponder which type is ConvertKit
     *
     * @return HasMany autoresponders connected to the group
     */
    public function convertKitIntegration(): HasMany
    {
        return $this->hasMany(AutoResponder::class, 'group_id', 'id')
            ->whereNull('deleted_at')
            ->where('responder_type', 'ConvertKit');
    }

    /**
     * Gets all tags connected to the group
     *
     * @return HasMany tag that are connected to the group
     */
    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'group_id', 'id');
    }

    /**
     * Gets all recommended tags connected to the group
     *
     * @return HasMany recommended tags that are connected to the group
     */
    public function recommendedTags(): HasMany
    {
        return $this->hasMany(Tag::class, 'group_id', 'id')->where('is_recommended', true);
    }

    /**
     * Returns owner of the Facebook group
     *
     * @return BelongsTo owner of the group
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Set the user_id field to that of the current logged in user
     *
     * @param string $value
     * @return void
     *
     * @todo review code to see if we can safely set user_id in this mutator
     *       and remove it from {@see FacebookGroups::$fillable}.  Once confirmed,
     *       this method can be uncommented
     */
    /*
    public function setUserIdAttribute($value)
    {
        $this->attributes['user_id'] = Auth::id();
    }
    */
}
