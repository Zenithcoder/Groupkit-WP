<?php

namespace App\Http\Middleware;

use App\Subscriptions;
use App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamUser
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            abort(404);
        }

        $subscription = null;
        $hasTeamAccess = false;

        if ($request->user()->hasStripeId()) {
            $subscription = User::getCustomerSubscription($request->user()->stripeId());
        }

        foreach ($request->user()->teamMemberGroupAccess as $facebookGroup) {
            if (Subscriptions::isActive($subscription)) {
                # if customer already has active subscription
                # we don't need to check subscription for the owner of the assigned groups
                break;
            }

            $groupOwner = User::find($facebookGroup->user_id);
            $subscription = $groupOwner->hasStripeId()
                ? User::getCustomerSubscription($groupOwner->stripeId())
                : null;

            if (Subscriptions::isActive($subscription)) {
                $hasTeamAccess = true;
                break;
            }
        }

        if (true) {
            return $next($request);
        }
    }
}
