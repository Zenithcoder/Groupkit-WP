<?php

namespace App\Services\MarketingAutomation;

use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Used to interact with the Kartra email automation service
 * @link https://documentation.kartra.com/category/api/
 *
 * @package App\Services\MarketingAutomation
 */
class KartraService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'Kartra';

    /**
     * The url of the Kartra API
     * @var string
     */
    private static string $url = 'https://app.kartra.com/api/';

    /**
     * Subscribes an individual group member to the mailing list for this marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this marketing platform
     *
     * @throws GuzzleException
     *      if there is a problem connecting with the marketing service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

         /* Register or update this member on Kartra */
        static::addOrUpdateContact($groupMember, $extraParameters);

        /* Subscribe the member to the appropriate list */
        static::addMemberToMailingList($groupMember, $extraParameters);
    }

    /**
     * Adds or syncs this contact on Kartra
     *
     * @param GroupMembers $groupMember
     *          that will be added to the Kartra lead list
     * @param object $extraParameters
     *          which contains the application id and the API for authentication,
     *          i.e. {app_id, api_key, password}
     *
     * @return void
     *
     * @throws GuzzleException
     *              if there is a problem with calling the Kartra API
     * @throws InvalidStateException
     *              if the response is not in the expected format
     */
    private static function addOrUpdateContact(GroupMembers $groupMember, object $extraParameters): void
    {
        $client = new Client(['verify' => false]);
        $response = $client->post(self::$url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body'    => http_build_query([
                'app_id'           => $extraParameters->app_id,
                'api_key'          => $extraParameters->api_key,
                'api_password'     => $extraParameters->password,
                'lead[email]'      => $groupMember->email,
                'lead[first_name]' => $groupMember->f_name,
                'lead[last_name]'  => $groupMember->l_name,
                'actions[0][cmd]'  => 'create_lead',
            ]),
            'http_errors' => false,
        ]);

        if (!static::isSuccessResponseCode($response->getStatusCode())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->getStatusCode(),
                    $extraParameters,
                    $groupMember->id
                )
            );
        }
    }

    /**
     * Subscribes an Kartra contact to a specified mailing list
     *
     * @param GroupMembers $groupMember
     *          that will be added to the Kartra mailing list
     * @param object $extraParameters
     *          which contain the application id, the API credentials for authentication, and the mailing
     *          list to which the member will be subscribed, i.e. {app_id, api_key, activeList->value}
     *
     * @throws GuzzleException if there is a problem with calling the Kartra API
     * @throws InvalidStateException if the contact was not able to be subscribed to the specified list
     */
    private static function addMemberToMailingList(GroupMembers $groupMember, object $extraParameters)
    {
        $client = new Client(['verify' => false]);
        $response = $client->post(self::$url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'app_id' => $extraParameters->app_id,
                'api_key' => $extraParameters->api_key,
                'api_password' => $extraParameters->password,
                'lead[email]' => $groupMember->email,
                'actions[0][list_name]' => $extraParameters->activeList->value,
                'actions[0][cmd]' => 'subscribe_lead_to_list',
            ]),
            'http_errors' => false,
        ]);

        if (!static::isSuccessResponseCode($response->getStatusCode())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->getStatusCode(),
                    $extraParameters,
                    $groupMember->id
                )
            );
        }
    }
}
