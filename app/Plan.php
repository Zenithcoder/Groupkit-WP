<?php

namespace App;

use Stripe;

class Plan
{
    /**
     * All stripe free plans titles
     */
    public const STRIPE_FREE_PLAN_TITLES = [
        'FREE_BASIC',
        'FREE_PRO',
    ];

    /**
     * All stripe plan ids
     * The keys are used as the values
     */
    public const STRIPE_PLAN_IDS = [
        # GroupKit merchant account.
        'default' => [
            'BASIC' => 'plan_H81yEbnL2c1ng6',
            'PRO_MONTHLY' => 'plan_H81ycCkDlKy6Ng',
            'PRO_ANNUAL' => 'plan_H98gMql8UbiAgb',
            'FREE_BASIC' => 'price_1HwxvTLRkwVQ3lNkyD9xCuCw',
            'FREE_PRO' => 'price_1HwWAeLRkwVQ3lNkSf2Sc4WI', # Lifetime subscription
        ],
        # MDGM merchant account.
        'new' => [
            'BASIC' => 'price_1J0pgtGF4CZi4Kjgh9dukXhD',
            'PRO_MONTHLY' => 'price_1IwrrOGF4CZi4KjgqMnCD2O8',
            'PRO_ANNUAL' => 'price_1Iwrr6GF4CZi4Kjggfi0Zddx',
            'FREE_BASIC' => 'price_1JubPBGF4CZi4KjgdBj53VUN',
            'FREE_PRO' => 'price_1J3MwDGF4CZi4Kjg0BKVFg9Q', # Lifetime subscription
        ],
    ];

    /**
     * All project route names that are excluded from active plan check
     *
     * @var array
     */
    public const EXCLUDED_PLAN_CHECK_ROUTES = [
        'subscription.cancelSubscription',
        'getPlanDetails',
        'subscription.autorenewplan',
        'subscription.upgradePlan',
        'wait',
        'plans.index',
        'plans.show',
        'webinar',
        'noGroupsAssigned',
        'subscription.create',
        'subscription.pauseOrContinueSubscription',
    ];

    protected $fillable = [
        'name',
        'slug',
        'stripe_plan',
        'cost',
        'description',
    ];

    /**
     * @var bool Denotes whether the Stripe API key has already been set for the Stripe SDK
     */
    protected static bool $stripeApiKeyWasSet = false;

    /**
     * @var string Text that is shown in the modal when a team owner reaches
     * maximum of some parameters for its group(s).
     */
    public const UPGRADE_PLAN_TEXT = 'Please upgrade your plan.';

    /**
     * Creates new instance and sets {@see \Stripe\Stripe::$apiKey}
     */
    public function __construct()
    {
        $stripeSecret = User::getStripeSecret(auth()->user()->stripeId());
        Stripe\Stripe::setApiKey($stripeSecret);
    }

    /**
     * Gets all plans from stripe.
     *
     * @param ?array $expand items that should be returned with the plan
     *
     * @throws Stripe\Exception\ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return Stripe\Collection of the stripe plans
     */
    private static function getPlans(array $expand = null)
    {
        return app(Stripe\Plan::class)->all(($expand) ? [['expand' => $expand]] : '');
    }

    /**
     * Gets a plan with provided id from Stripe
     *
     * @param string $id of the {@see Stripe\Plan}
     * @param null|array $expand items provided as a array of expandable strings to return in Plan instance
     *                           objects for a items that can be expanded
     *
     * @throws Stripe\Exception\ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return Stripe\Plan instance
     */
    private static function getPlan(string $id, array $expand = null)
    {
        return app(Stripe\Plan::class)->retrieve(($expand) ? ['id' => $id, ['expand' => $expand]] : $id);
    }

    /**
     * Retrieves stripe yearly plan
     *
     * @throws Stripe\Exception\ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return Stripe\Plan yearly instance
     */
    private static function getYearlyPlan()
    {
        return self::getPlan(self::STRIPE_PLAN_IDS[auth()->user()->stripe_account ?? 'default']['PRO_ANNUAL']);
    }

    /**
     * Retrieves stripe plans that should be displayed on the plans page
     *
     * @throws Stripe\Exception\ApiErrorException if the {@see \Stripe\ApiRequestor::request} fails
     *
     * @return array of the stripe plans that should be displayed
     */
    private static function getDisplayedPlans()
    {
        $plans = self::getPlans(['data.product']);

        return array_filter(
            $plans->data,
            function ($plan) {
                return (bool)
                    ($plan->metadata->display_on_plans_page
                    ?? $plan->product->metadata->display_on_plans_page);
            }
        );
    }

    /**
     * Sets the Stripe API key to the Stripe SDK, if necessary, before API calls
     *
     * @param string $functionName that was called and was not accessible
     * @param array $arguments passed to the function
     *
     * @return mixed The value returned from the proxied function
     */
    public static function __callStatic(string $functionName, array $arguments)
    {
        $currentUser = auth()->user();

        if (!self::$stripeApiKeyWasSet) {
            $stripeSecret = User::getStripeSecret($currentUser ? $currentUser->stripeId() : null);
            Stripe\Stripe::setApiKey($stripeSecret);
            self::$stripeApiKeyWasSet = true;
        }

        return self::$functionName(...$arguments);
    }
}
