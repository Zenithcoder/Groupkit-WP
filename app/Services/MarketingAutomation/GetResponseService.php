<?php

namespace App\Services\MarketingAutomation;

use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Used to interact with the GetResponse email automation service
 * @link https://apidocs.getresponse.com/v3
 *
 * @package App\Services\MarketingAutomation
 */
class GetResponseService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'Getresponse';

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
        try {
            $extraParameters = static::getApiInfo($groupMember->group_id);

            $client = new Client(['verify' => false]);
            $response = $client->post(
                "https://api.getresponse.com/v3/contacts",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-Auth-Token' => "api-key {$extraParameters->api_key}",
                    ],
                    'json' => [
                        'campaign' => [
                            'campaignId' => $extraParameters->activeList->value,
                        ],
                        'dayOfCycle' => 0,
                        'email' => $groupMember->email,
                        'name' => $groupMember->f_name . ' ' . $groupMember->l_name,
                    ],
                ]
            );
        } catch (GuzzleException $guzzleException) {
            /* If member already subscribed in GetResponse then exception will be handled */
            $response = json_decode($guzzleException->getResponse()->getBody()->getContents());

            /*
             * If the response from teh GetResponse API contains:
             * Error Code: 1008 (There is another resource with the same
             * value of unique property)
             * HTTP status Code: 409: We cannot add resource because there
             * is already resource with the same unique properties.
             * @link https://apidocs.getresponse.com/v3/errors/1008
             */
            if (@$response->httpStatus == 409 && $response->code == 1008) {
                // We return as OK since the member is already subscribed
                return;
            }

            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->httpStatus,
                    $extraParameters,
                    $groupMember->id,
                    $response->code
                )
            );
        }

        if (!static::isSuccessResponseCode($response->getStatusCode())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->httpStatus,
                    $extraParameters,
                    $groupMember->id,
                    $response->code
                )
            );
        }
    }
}
