<?php

namespace App\Http\Middleware;

use App\Plan;
use App\Subscriptions;
use Closure;
use Illuminate\Http\Request;
use App\User;

class TeamMember
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
        $user = auth()->user();

        $subscription = $user && $user->hasStripeId() ? User::getCustomerSubscription($user->stripeId()) : null;

        if (
            !$subscription
            || !$subscription->plan
            || !Plan::getPlan($subscription->plan->id, ['product'])->product->metadata->moderator_limit
        ) {
            abort(404);
        } elseif (
            $subscription->pause_collection
            && $subscription->pause_collection->behavior
            === Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE']
        ) {
            return redirect('subscriptionOptions');
        }

        return $next($request);
    }
}
