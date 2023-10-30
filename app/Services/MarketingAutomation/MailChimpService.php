<?php

namespace App\Services\MarketingAutomation;

use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Used to interact with the MailChimp email automation service
 * @link https://mailchimp.com/developer/marketing/api/
 *
 * @package App\Services\MarketingAutomation
 */
class MailChimpService extends AbstractMarketingService
{
    /**
     * @var string[] available MailChimp member statuses
     */
    public const STATUSES = [
        'PENDING' => 'pending',
        'SUBSCRIBED' => 'subscribed',
    ];

    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'MailChimp';

    /**
     * The API keys for authorization requests to the MailChimp, indexed by Facebook group IDs
     *
     * @var string[]
     */
    private static array $apiKey = [];

    /**
     * The MailChimp list API URL map, indexed by Facebook group ID
     * Different accounts can have different base URLs as they are based on the data center they are assigned to
     * @link https://mailchimp.com/developer/marketing/docs/fundamentals/
     *
     * @var string[]
     */
    private static array $url = [];

    /**
     * Subscribes an individual group member to the mailing list for this marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this marketing platform
     *
     * @throws RequestException
     *      if there is a problem connecting with the marketing service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        try {
            $response = Http::withBasicAuth('', static::getApiKey($groupMember->group_id))
                ->post(
                    static::getUrl($groupMember->group_id) . '/members',
                    [
                        'merge_fields'  => [
                            'FNAME' => $groupMember->f_name,
                            'LNAME' => $groupMember->l_name,
                        ],
                        'email_address' => $groupMember->email,
                        'status' => self::STATUSES['SUBSCRIBED'],
                    ],
                )->throw();
        } catch (RequestException $exception) {
            /* We should check if the exception is because the user has already subscribed */
            $responseBody = json_decode($exception->response);

            /* Don't throw an exception if the status code from the Mailchimp service is
            400 with the appropriate title. */
            if (
                strtolower(@$responseBody->title) === 
                strtolower(GroupMembers::RESPONSE_STATUSES['INVALID_RESOURCE'])
            ) {
                $groupMember->update([
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['INVALID_RESOURCE'],
                ]);

                return;
            }

            if (strtolower(@$responseBody->title) == 'member exists') {
                static::updateStatusToSubscribedIfPending($groupMember);

                return;
            }

            #otherwise, there was an unexpected problem that we will raise up the system
            throw $exception;
        }

        if (!static::isSuccessResponseCode($response->status())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->status(),
                    null,
                    $groupMember->id
                )
            );
        }
    }

    /**
     * Sets the mailing list status to 'subscribed' for the customer with the provided email if his status is not
     * already set to 'subscribed'
     *
     * @param string $customerId of the provided $groupMember in MailChimp
     * @param GroupMembers $groupMember which status will be updated to subscribed
     *
     * @throws RequestException if there is a problem connecting with the marketing service
     */
    private static function setStatusToSubscribed(string $customerId, GroupMembers $groupMember)
    {
        try {
            Http::withBasicAuth('', static::getApiKey($groupMember->group_id))
                ->put(
                    static::getUrl($groupMember->group_id) . '/members/' . $customerId,
                    [
                        'status'       => self::STATUSES['SUBSCRIBED'],
                        'merge_fields' => [
                            'FNAME' => $groupMember->f_name,
                            'LNAME' => $groupMember->l_name,
                        ],
                    ]
                )->throw();
        } catch (RequestException $exception) {
            $responseBody = json_decode($exception->response);

            if (in_array(strtolower($responseBody->title), ['invalid resource', 'member in compliance state'])) {
                # We don't log error if customer is unsubscribed (invalid resource)
                # or his status is in compliance state
                return;
            }

            throw $exception;
        }
    }

    /**
     * Gets customer from MailChimp API based on group member email address
     *
     * @param GroupMembers $groupMember by whose email we would retrieve the MailChimp account
     *
     * @return object containing MailChimp customer data
     *
     * @throws RequestException if there is a problem with getting the MailChimp member
     */
    private static function getMailChimpCustomer(GroupMembers $groupMember): object
    {
        try {
            $response = Http::withBasicAuth('', static::getApiKey($groupMember->group_id))
                ->get(
                    static::getUrl($groupMember->group_id) . '/members/' . $groupMember->email,
                    [
                        'query' => [
                            'fields' => 'id,status',
                        ],
                    ]
                )->throw();
        } catch (RequestException $e) {
            Bugsnag::notifyException($e);

            throw $e;
        }

        return $response->object();
    }

    /**
     * Gets url for the MailChimp API
     *
     * @param int $facebookGroupId
     * for getting {@see \App\Services\MarketingAutomation\AbstractMarketingService::getApiInfo}
     * of the current service
     *
     * @return string containing URL for MailChimp API
     */
    private static function getUrl(int $facebookGroupId): string
    {
        if (key_exists($facebookGroupId, static::$url)) {
            return static::$url[$facebookGroupId];
        }

        $extraParameters = static::getApiInfo($facebookGroupId);
        [$apiKey, $subDomain] = explode('-', $extraParameters->api_key);
        $activeList = $extraParameters->activeList ? $extraParameters->activeList->value : null;

        return static::$url[$facebookGroupId] = "https://{$subDomain}.api.mailchimp.com/3.0/lists/{$activeList}";
    }

    /**
     * Gets API key for authorization MailChimp API
     *
     * @param int $facebookGroupId
     * for getting {@see \App\Services\MarketingAutomation\AbstractMarketingService::getApiInfo}
     * of the current service
     *
     * @return string containing API key for MailChimp API authorization
     */
    private static function getApiKey(int $facebookGroupId): string
    {
        if (!key_exists($facebookGroupId, static::$apiKey)) {
            $extraParameters = static::getApiInfo($facebookGroupId);
            static::$apiKey[$facebookGroupId] = explode('-', $extraParameters->api_key)[0];
        }

        return static::$apiKey[$facebookGroupId];
    }

    /**
     * Checks MailChimp member status, if pending then updates it to the subscribed otherwise return null
     *
     * @param GroupMembers $groupMember which status will be updated to the subscribed if status is pending
     *
     * @throws RequestException if there is problem connecting MailChimp API to set subscribed status for member
     */
    private static function updateStatusToSubscribedIfPending(GroupMembers $groupMember): void
    {
        try {
            $mailChimpCustomer = static::getMailChimpCustomer($groupMember);
        } catch (RequestException $exception) {
            # if MailChimp member can't be retrieved, we just skip throwing an exception
            return;
        }

        # if member is not subscribed we need to update his status
        if ($mailChimpCustomer->status === self::STATUSES['PENDING']) {
            static::setStatusToSubscribed($mailChimpCustomer->id, $groupMember);
        }
    }
}
