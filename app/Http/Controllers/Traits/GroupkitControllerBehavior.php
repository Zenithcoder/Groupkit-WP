<?php

namespace App\Http\Controllers\Traits;

use App\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;

/**
 * Defines the generic behavior which is special and globally shared
 * by all Groupkit controllers
 *
 * @package App\Http\Controllers
 */
trait GroupkitControllerBehavior
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;

    /**
     * @var User|null The cached model of the currently logged in user.
     *           For a real-time copy of the user data use {@see Auth::user()} or {@see auth::user()} instead
     */
    protected ?User $currentUser;

    /**
     * @var Request The current request being handled
     */
    protected Request $request;

    /**
     * @var array The rules used for AJAX request validation for each action with the action name used as the key
     *            for each set of rules
     */
    protected array $ajaxValidatorRules = [];

    /**
     * @var \Closure|null The callback that will be provided to any register AJAX request validator
     */
    public ?\Closure $ajaxValidatorAfterCallback = null;

    /**
     * @var array Any data that is to be sent back as JSON in response's 'data' field for an AJAX request
     *            with any validation errors.  This is intended to be set in the validator 'after' callback,
     *            {@see GroupkitControllerBehavior::$ajaxValidatorAfterCallback}
     */
    public array $ajaxValidationErrorResponseData = [];

    /**
     * Generic initialization this controller, globally setting the current request and user
     *
     * @param Request $request  The current request being handled injected by the Laravel Framework
     */
    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->middleware(
            /**
             * Set the user via a deferred middleware because the constructor is run before any middleware
             * is run, including web and auth, so we won't have the user at this point
             *
             * @var Request $request The current request that is container injected
             * @var \Closure $next The next middleware in the queue to be executed
             */
            function ($request, $next) {
                # default defined by config('auth.defaults.guard')
                $this->currentUser = $request->user(@$this->guardType);
                return $next($request);
            }
        );

        $this->init();
    }

    /**
     * Post-construction initialization code for the controller.
     * This is called once, before any middleware and the action. As such, one typical application
     * might be to define the middleware that will be used.
     */
    protected function init()
    {
    }

    /**
     * Returns the validator rules for a specific controller action
     *
     * @param string|null $actionName    The action of the controller for which the rules are to be returned.
     *                                   If this is null, then all rules are returned
     *
     * @return array    Standard Laravel validation rules for the specified action, or all actions if no action
     *                  is specified
     */
    public function getAjaxValidatorRules(?string $actionName): array
    {
        return $actionName ? ($this->ajaxValidatorRules[$actionName] ?? []) : $this->ajaxValidatorRules;
    }
}
