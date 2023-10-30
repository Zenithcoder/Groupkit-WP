<?php

namespace App\Jobs\Subscription;

use App\Services\SubscriptionService;
use App\Subscriptions;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stripe\Stripe;

/**
 * Pauses customer's subscription
 */
class PauseSubscription implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Stripe pause type, it can be one of the {@see Subscriptions::PAUSE_TYPES}
     *
     * @var string
     */
    private string $pauseType;

    /**
     * Represents id of the Stripe subscription, which will pause
     *
     * @var string
     */
    private string $subscriptionId;

    /**
     * Timestamp represent time after subscription will automatically continue
     *
     * @var ?int
     */
    private ?int $resumeIn;

    /**
     * The user whose subscription we will pause
     *
     * @var User
     */
    private User $user;

    /**
     * Create a new job instance.
     *
     * @param User $user which subscription we will schedule to pause
     * @param string $pauseType can be one of the {@see Subscriptions::PAUSE_TYPES}
     * @param string $subscriptionId of the customer subscription
     * @param int|null $resumeIn represents number of months after who subscription will automatically continue
     */
    public function __construct(User $user, string $pauseType, string $subscriptionId, ?int $resumeIn)
    {
        $this->user = $user;
        $this->pauseType = $pauseType;
        $this->subscriptionId = $subscriptionId;
        $this->resumeIn = $resumeIn;
    }

    /**
     * Pauses subscription and set metadata to default
     *
     * @return void
     */
    public function handle(): void
    {
        Stripe::setApiKey(User::getStripeSecret($this->user->stripeId()));

        # automatically resume subscription after a provided number of months upon pausing
        $resumeAt = now()->addMonths($this->resumeIn)->getTimestamp();

        app(SubscriptionService::class)->pauseSubscription(
            $this->subscriptionId,
            $this->pauseType,
            $resumeAt
        );

        app(SubscriptionService::class)->update($this->subscriptionId, ['metadata' => '']);
    }
}
