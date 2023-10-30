<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\GroupkitControllerBehavior;
use App\Subscriptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use App\Services\TapfiliateService;
use Illuminate\Routing\Controller;
use App\Plan;
use App\User;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    use GroupkitControllerBehavior;

    /**
     * Sets the middleware and validation rules for this controller
     */
    protected function init()
    {
        $this->middleware('validate.ajax.request')->only(['validateEmail']);

        $this->ajaxValidatorRules['validateEmail'] = [
            'email' => ['required', 'email', 'max:100', 'unique:users,email'],
        ];
    }

    /**
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return View response containing all Stripe plans
     *
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     */
    public function index(): View
    {
        $plans = Plan::getDisplayedPlans();

        $subscriptionIsPaused = $this->request->user()
            && User::subscriptionIsPaused(
                $this->request->user()->id,
                Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE']
            );

        return view('plans.index', compact('plans', 'subscriptionIsPaused'));
    }

    /**
     * @throws ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return View response containing selected stripe plan
     */
    public function show()
    {
        $plan = Plan::getPlan(base64_decode($this->request->plan), ['product']);
        // Check if plan has trial, if true, price becomes $0 else price becomes value passed from stripe
        $planPrice = $plan->amount / 100;
        $trialLength = @$plan->product->metadata->trialLength;
        $initialPrice = $trialLength  > 0 ? 0 : $planPrice;
        $templatePrice = 37;

        return view('plans.show', compact('plan', 'initialPrice', 'planPrice', 'trialLength', 'templatePrice'));
    }

    /**
     * Checks if the email address is valid.
     *
     * @return Response containing JSON {"success": true}, indicating that the email validation rules run prior to calling this method have all passed
     */
    public function validateEmail(): Response
    {
        return response(['success' => true]);
    }

    /**
     * Shows webinar page.
     *
     * @return View response
     */
    public function webinar()
    {
        if ($this->request->input(TapfiliateService::TAPFILIATE_REQUEST_PARAMETER)) {
            TapfiliateService::storeTapfiliateCookie(
                $this->request->input(TapfiliateService::TAPFILIATE_REQUEST_PARAMETER)
            );
        }
        return view('webinar');
    }
}
