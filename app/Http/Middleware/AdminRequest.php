<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\Traits\AdminControllerBehavior;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;

/**
 * Class AdminRequest handles incoming requests for the Admin Endpoints
 *
 * @package App\Http\Middleware
 */
class AdminRequest
{
    use AdminControllerBehavior;

    /**
     * Inspects request for API credentials. If they are not found, an error response is
     * returned
     *
     * @param Request $request The current request made to the server
     * @param Closure $next The next middleware handle function or the action called in the request
     *
     * @return mixed Returns the response which is sent back to the requesting client as JSON
     */
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isAuthorizedRequest($request)) {
            throw new UnauthorizedException();
        }

        $request->setJson($this->getDecryptedRequest($request));

        return $next($request);
    }

    /**
     * Determines is request authorized
     *
     * @param Request $request
     *
     * @return bool true if the request is authorized, otherwise false
     */
    private function isAuthorizedRequest(Request $request)
    {
        return config('app.admin_secret_key') === $request->header('authentication');
    }
}
