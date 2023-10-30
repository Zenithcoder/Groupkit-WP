<?php

namespace App\Http\Middleware;

use App\Plan;
use Closure;
use Illuminate\Http\Request;

class User
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
        $currentUser = $request->user();

        /* Exclude specific routes from the active plan check */
        if (in_array($request->route()->getName(), Plan::EXCLUDED_PLAN_CHECK_ROUTES)) {
            return $next($request);
        }
        if ($currentUser->activePlan() === false) {
            return redirect('plans');
        }
        return $next($request);
    }
}
