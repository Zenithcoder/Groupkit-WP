<?php

namespace App\Rules;

use App\FacebookGroups;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class FacebookGroupsBelongsToOwner represents validation rule for provided facebook_group_ids
 * @package App\Rules
 */
class FacebookGroupsBelongsToOwner implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param string $facebookGroupIdsLabel represents name of the validated property
     * @param array $facebookGroupIds is the value of the validated property
     *                                it should come as an array of the facebook group id strings
     * @return bool true if the current user is the owner of the group specified by the $facebookGroupId,
     *              otherwise false
     */
    public function passes($facebookGroupIdsLabel, $facebookGroupIds): bool
    {
        // If the Facebook IDs array to check is empty, just return true,
        // otherwise let's check them against the owners groups in the DB.
        if (empty($facebookGroupIds)) {
            return true;
        }

        $ownedGroupIds = FacebookGroups::where('user_id', auth()->user()->id)->pluck('id')->toArray();
        $facebookGroupIds = array_map('intval', $facebookGroupIds);

        return empty(array_diff($facebookGroupIds, $ownedGroupIds));
    }

    /**
     * Get the validation error message.
     *
     * @return string containing validation message
     */
    public function message(): string
    {
        return __('One of the selected groups is not owned by you');
    }
}
