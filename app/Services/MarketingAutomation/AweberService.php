<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Used to interact with the Aweber email automation service
 * @link https://api.aweber.com/
 *
 * @package App\Services\MarketingAutomation
 */
class AweberService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'Aweber';

    /**
     * Subscribes an individual group member to the mailing list for this marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this marketing platform
     *
     *
     * @throws GuzzleException
     *      if there is a problem connecting with the marketing service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        try {
            $apiInfo = static::getApiInfo($groupMember->group_id);
            $activeList = $apiInfo->activeList->value;

            $body = "email={$groupMember->email}&name={$groupMember->f_name} {$groupMember->l_name}";

            $url = "https://api.aweber.com/1.0/accounts/{$apiInfo->account_id}/lists/{$activeList}/subscribers";
            $client = new Client(['verify' => false]);
            $response = $client->post(
                $url,
                [
                    'headers' => [
                        "Authorization" => "Bearer {$apiInfo->access_token}",
                        "Content-Type" => "application/x-www-form-urlencoded"
                    ],
                    'body' => $body,
                ]
            );
        } catch (GuzzleException $guzzleException) {
            $response = json_decode($guzzleException->getResponse()->getBody()->getContents());
            $message = $response->error_description ?? strtolower($response->error->message);

            if (strpos($message, "subscriber already subscribed")) {
                // We return as OK since the member is already subscribed
                return;
            }

            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->httpStatus,
                    null,
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
                    $response->getStatusCode(),
                    null,
                    $groupMember->id
                )
            );
        }
    }

    /**
     * Gets a fresh API access information.  If the token has expired, an attempt will be made
     * for a new one with this new data being updated in the database.
     *
     * @param object $currentApiInfo
     *          Current credentials needed to login to Aweber
     * @param int $facebookGroupId
     *          The Groupkit Facebook group ID associated with this mailing list
     *
     * @return object A valid Aweber API access information
     *
     * @throws GuzzleException  if there is an error connecting to Aweber
     * @throws \Exception If there is an unknown error after connecting with Aweber
     */
    private static function getRefreshedApiInfo(object $currentApiInfo, int $facebookGroupId): object {
        $currentTimestamp = Carbon::now()->timestamp;

        if (@$currentApiInfo->expires_in > $currentTimestamp) {
            return $currentApiInfo;
        }

        $body = "grant_type=refresh_token"
            . "&refresh_token=" . $currentApiInfo->refresh_token
            . "&redirect_uri=urn:ietf:wg:oauth:2.0:oob"
            . "&client_id=" . $currentApiInfo->client_id;

        $url = "https://auth.aweber.com/oauth2/token";
        $client = new Client(['verify' => false]);
        $response = $client->post(
            $url,
            [
                'headers' => [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ],
                'body' => $body,
                'http_errors' => false,
            ]
        );

        if (!static::isSuccessResponseCode( $response->getStatusCode() )) {
            # retrieving the new access token failed, likely due to an expired refresh token
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['EXPIRED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['EXPIRED'],
                    $response->getStatusCode(),
                    null,
                    null,
                    null,
                    [self::FACEBOOK_GROUP_ID => $facebookGroupId]
                )
            );
        }

        $responseData = json_decode($response->getBody());

        $currentApiInfo->access_token = $responseData->access_token;
        $currentApiInfo->refresh_token = $responseData->refresh_token;
        $currentApiInfo->expires_in = $currentTimestamp + $responseData->expires_in;

        $responder = AutoResponder::where('group_id', $facebookGroupId)->first();
        $responder->responder_json = json_encode($currentApiInfo);
        $responder->save();

        return $currentApiInfo;
    }

    /**
     * Gets the info necessary to access the Aweber API
     *
     * @param int $facebookGroupId
     *          The Groupkit Facebook group ID associated with this mailing list
     *
     * @return object A valid Aweber API access information
     *
     * @throws GuzzleException  if there is an error connecting to Aweber
     * @throws \Exception If there is an unknown error after connecting with Aweber
     */
    static protected function getApiInfo(int $facebookGroupId): object
    {
        return static::getRefreshedApiInfo(parent::getApiInfo($facebookGroupId), $facebookGroupId);
    }
}
