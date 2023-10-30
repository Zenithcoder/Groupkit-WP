<?php

namespace App\Services\MarketingAutomation;

use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Used to interact with the MailLite email automation service
 * @link https://developers.mailerlite.com/docs
 *
 * @package App\Services\MarketingAutomation
 */
class MailerLiteService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'Mailerlite';

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

        $url = "https://api.mailerlite.com/api/v2/groups/{$extraParameters->activeList->value}/subscribers";
        $client = new Client(['verify' => false]);
        $response = $client->post($url, [
            'headers' => [
                'Content-Type'        => 'application/json',
                'X-MailerLite-ApiKey' => $extraParameters->api_key,
            ],
            'json'    => [
                'email' => $groupMember->email,
                'name'  => $groupMember->f_name . ' ' . $groupMember->l_name,
            ],
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
