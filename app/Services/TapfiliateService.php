<?php

namespace App\Services;

use App\User;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TapfiliateService represents connection with Tapfiliate integration
 * @package App\Services
 */
class TapfiliateService
{
    /**
     * Represents expire time for cookie tapfiliate_id
     *
     * @var int returns the amount of minutes in 30 days
     */
    public const COOKIE_TAPFILIATE_ID_TIME = 60 * 24 * 30;

    /**
     * Represents Tapfiliate request parameter key
     *
     * @var string request key
     */
    public const TAPFILIATE_REQUEST_PARAMETER = 'ref';

    /**
     * Tapfiliate statuses according to the stripe subscription
     *
     * @var array key is used as stripe subscription status while value represents tapfiliate status
     */
    public const TAPFILIATE_STATUSES = [
        'trialing' => 'trial',
        'active' => 'new',
    ];

    /**
     * Stores the provided ref code inside tapfiliate_id cookie
     * for {@see TapfiliateService::COOKIE_TAPFILIATE_ID_TIME} minutes
     *
     * @param string $ref code from URL
     */
    public static function storeTapfiliateCookie(string $ref): void
    {
        Cookie::queue(
            Cookie::make(
                'tapfiliate_id',
                $ref,
                self::COOKIE_TAPFILIATE_ID_TIME
            )
        );
    }

    /**
     * Removes tapfiliate id from Cookie storage
     */
    public static function removeTapfiliateCookie(): void
    {
        Cookie::queue(Cookie::forget('tapfiliate_id'));
    }

    /**
     * Creates Tapfiliate Customer for the application.
     *
     * @param User $user represents new Tapfiliate customer
     * @param object $subscription created for $user, it used to determine stripe status for the user
     *
     * @return bool true if an customer is successfully create on Tapfiliate, otherwise false
     */
    public static function createCustomer(User $user, object $subscription): bool
    {
        $requestData = [
            'referral_code' => $user->ref_code,
            'customer_id' => $user->stripe_id,
            'status' => self::TAPFILIATE_STATUSES[$subscription->stripe_status],
            'meta_data' => [
                'full_name' => $user->name,
                'email' => $user->email,
                'clickfunnel_id' => 'GroupkitUser' . $user->id,
            ],
        ];
        $url = config('services.tapfiliate.url') . 'customers/';

        return self::send($requestData, $url);
    }

    /**
     * Creates Tapfiliate conversion for provided customer {@see User} if amount is greater than zero
     *
     * @param User $user represents Tapfiliate customer
     * @param int $amountPaid represents amount for user payment via Stripe, it comes multiplied with 100 for example:
     *                        $40 comes as integer 4000
     *
     * @return bool true if send request success otherwise false
     */
    public static function createConversion(User $user, int $amountPaid): bool
    {
        if (!$amountPaid) {
            return false;
        }

        $requestData = [
            'referral_code' => $user->ref_code,
            'currency' => 'USD',
            'external_id' => uniqid("{$user->email}-"),
            'customer_id' => $user->stripe_id,
            'amount' => number_format(($amountPaid / 100), 2), #convert to real amount and adds two decimals
            'commission_type' => 'standard',
            'meta_data' => [
                'ref_id' => $user->ref_code,
                'clickfunnel_id' => 'GroupkitUser' . $user->id,
                'full_name' => $user->name,
            ],
        ];
        $url = config('services.tapfiliate.url') . 'conversions/';

        return self::send($requestData, $url);
    }

    /**
     * Sends provided data to the Tapfiliate API $url endpoint
     *
     * @param array $requestData represents data for Tapfiliate api
     * @param string $url represent Tapfiliate API endpoint
     *
     * @return bool true if request success otherwise false
     */
    private static function send(array $requestData, string $url): bool
    {
        $client = new Client();
        try {
            $response = $client->post($url, [
                'headers' => [
                    'Api-Key' => config('services.tapfiliate.key'),
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'json' => $requestData,
            ]);

            return $response->getStatusCode() === Response::HTTP_OK;
        } catch (GuzzleException $e) {
            logger()->error($e->getMessage()); #log error if referral code is not valid or some error is happen
            return false;
        }
    }
}
