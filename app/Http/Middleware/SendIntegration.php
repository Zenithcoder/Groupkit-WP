<?php

namespace App\Http\Middleware;

use App\Services\MarketingAutomation\IntegrationService;
use Closure;
use Illuminate\Http\Request;

/**
 * Send group member info sent to the API
 *
 * @package App\Http\Middleware
 */
class SendIntegration
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request The current request made to the server
     * @param Closure $next    The next middleware handle function or the action called in the request
     *
     * @return mixed Returns passes the request to next handler
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Handles group members to email marketing services after the response has been sent to the web.
     *
     * @param Request $request The current request made to the server
     *
     * @return void
     */
    public function terminate(Request $request)
    {
        app(IntegrationService::class)->send($request->group_members_id ?? []);
    }
}
