<?php

namespace App\Http\Controllers\API;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API end-point for maintaining disabled Facebook group
 *
 * @package App\Http\Controllers\API
 */
class DisabledGroupController extends AbstractApiController
{
    /**
     * Gets facebook id of user's disabled groups
     *
     * @return HttpResponse including all disabled facebook groups ids for the current user
     */
    public function index(): HttpResponse
    {
        return response(['facebook_groups_ids' => User::getDisabledGroups(Auth::id())]);
    }

    /**
     * Store provided Facebook group in the `disabled_groups` table
     *
     * @param Request $request containing the Facebook id of the group.
     *
     * @return HttpResponse with status code and message according to the request result.
     */
    public function store(Request $request): HttpResponse
    {
        $groupIsAlreadyDisabled = (bool)DB::table('disabled_groups')
            ->where('user_id', $this->currentUser->id)
            ->where('facebook_group_fb_id', $request->facebook_group_id)
            ->first();

        if ($groupIsAlreadyDisabled) {
            return response( #todo: move this to rule @link https://laravel.com/docs/8.x/validation#using-rule-objects
                ['message' => __('This Facebook group already has the disabled status')],
                Response::HTTP_FOUND
            );
        }

        DB::table('disabled_groups')->insert([
            'user_id' => $this->currentUser->id,
            'facebook_group_fb_id' => $request->facebook_group_id,
        ]);

        return response(['message' => __('Group disabled')]);
    }

    /**
     * Removes provided group from `disabled_groups` table for the current user
     *
     * @param Request $request containing the Facebook id of the group.
     *
     * @return HttpResponse with status code and message according to the request result.
     */
    public function destroy(Request $request): HttpResponse
    {
        $group = DB::table('disabled_groups')->where('user_id', $this->currentUser->id)
            ->where('facebook_group_fb_id', $request->facebook_group_id);

        if (!$group->first()) {
            return response( #todo: move this to rule @link https://laravel.com/docs/8.x/validation#using-rule-objects
                ['message' => __('This Facebook group does not have the disabled status')],
                Response::HTTP_NOT_FOUND
            );
        }

        $group->delete();

        return response(['message' => __('Group enabled')]);
    }
}
