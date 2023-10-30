<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks if expected API credentials are present in the request
 *
 * @package App\Http\Middleware
 */
class ValidateAjaxRequest
{

    /**
     * Inspects request for API credentials.  If they are not found, an error response is
     * returned
     *
     * @param  Request  $request    The current request made to the server
     * @param  Closure  $next       The next middleware handle function or the action called in the request
     *
     * @return mixed    Returns the response which is sent back to the requesting client as JSON
     */
    public function handle(Request $request, Closure $next)
    {
        /**
         * @var GroupkitControllerBehavior $controller
         */
        $controller = $request->route()->getController();
        $validationRules = $controller->getAjaxValidatorRules($request->route()->getActionMethod());

        if ($validationRules) {
            $validator = Validator::make($request->all(), $validationRules);
            if ($controller->ajaxValidatorAfterCallback) {
                $validator->after($controller->ajaxValidatorAfterCallback);
            }

            if ($validator->fails()) {
                return response()->json(
                    [
                        'message' => implode("  ", $validator->messages()->all()),
                        'data' => $controller->ajaxValidationErrorResponseData,
                    ],
                    Response::HTTP_BAD_REQUEST # overriding status code when server side error occurred
                );
            }
        }

        return $next($request);
    }
}
