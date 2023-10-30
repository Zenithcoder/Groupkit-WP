<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stripe\Subscription;

class Subscriptions extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'stripe_id',
        'stripe_plan',
        'stripe_status',
        'quantity',
        'is_deleted',
    ];

    /**
     * Stripe subscriptions statuses mapper
     *
     * @var array key is used as stripe subscription status while value represents the human-readable status.
     */
    public const SUBSCRIPTION_STATUSES = [
        'trialing' => 'Trial',
        'active' => 'New',
        'incomplete' => 'Incomplete',
        'incomplete_expired' => 'Incomplete Expired',
        'past_due' => 'Past Due',
        'canceled' => 'Canceled',
        'unpaid' => 'Unpaid',
    ];

    /**
     * Stripe subscription pause types
     * @link https://stripe.com/docs/billing/subscriptions/pause
     *
     * @var array key is used as description of type while value represents Stripe pause type
     */
    public const PAUSE_TYPES = [
        'TEMPORARILY_OFFER_SERVICE_FOR_FREE' => 'keep_as_draft', #collects payment for later
        'OFFER_SERVICE_FOR_FREE' => 'mark_uncollectible', # could be used for lifetime memberships
        'SUSPEND_SERVICE' => 'void', # pause the subscription
    ];

    /**
     * Number of months that a subscription will be paused
     *
     * @var int
     */
    public const RESUME_PAUSED_SUBSCRIPTION_IN = 6;

    /**
     * Determines if subscription is not paused with unable to provide service
     * {@see Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE']}
     *
     * @param Subscription|null $subscription
     *
     * @return bool true if subscription contain active status, otherwise false
     */
    public static function isActive(?Subscription $subscription): bool
    {
        return $subscription
            && (
                ($subscription->plan && !$subscription->pause_collection)
                || (
                    $subscription->plan
                    && $subscription->pause_collection
                    && $subscription->pause_collection->behavior
                        !== Subscriptions::PAUSE_TYPES['SUSPEND_SERVICE']
                )
            );
    }
}
