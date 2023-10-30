<?php

namespace App\Http\Middleware;

use App\Jobs\AddMembers;
use App\Services\MarketingAutomation\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Saves group and group member info sent to the API
 *
 * @package App\Http\Middleware
 *
 * @deprecated This is now handled via a {@see AddMembers}
 */
class SaveGroupInfo
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, \Closure $next)
    {
        return $next($request);
    }

    /**
     * Handles registering group members to email marketing services after the response
     * has been sent to the extension.
     *
     * @param Request $request
     * @param JsonResponse $response
     *
     * @return void
     * @deprecated The functionality has been moved to {@see AddMembers}
     *
     */
    public function terminate(Request $request, JsonResponse $response)
    {
        $groupInfo = $response->getData()->data ? json_decode($response->getData()->data) : [];

        app(IntegrationService::class)->send($groupInfo->member ?? []);
    }
}
