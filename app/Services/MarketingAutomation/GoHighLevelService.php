<?php

namespace App\Services\MarketingAutomation;

use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

/**
 * Used to interact with the GoHighLevel email automation service
 * @link https://developers.gohighlevel.com/
 *
 * @package App\Services\MarketingAutomation
 */
class GoHighLevelService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'GoHighLevel';

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
        $extraParameters = static::getApiInfo($groupMember->group_id);

         /* Register or update this member on GoHighLevel */
        static::addOrUpdateContact($groupMember, $extraParameters);
    }

    /**
     * Adds or syncs this contact on GoHighLevel
     *
     * @param GroupMembers $groupMember
     *          that will be added to the GoHighLevel list
     * @param object $extraParameters
     *          which contain the API for authentication and active list,
     *          i.e. {activeList->value, api_key}
     *
     * @return void
     *
     * @throws RequestException
     *              if there is a problem with calling the GoHighLevel API
     * @throws InvalidStateException
     *              if the response is not in the expected format
     */
    private static function addOrUpdateContact(GroupMembers $groupMember, object $extraParameters): void
    {
        $response = Http::withHeaders([
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$extraParameters->api_key}",
            ])->post(
                'https://api.gohighlevel.com/campaign/start',
                [
                    'campaign_id' => $extraParameters->activeList->value,
                    'first_name' => $groupMember->f_name,
                    'last_name' => $groupMember->l_name,
                    'name' => '',
                    'email' => $groupMember->email,
                ]
            );

        // Throw an exception if a client or server error occurred...
        $response->throw();

        if (!static::isSuccessResponseCode($response->status())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->status(),
                    $extraParameters,
                    $groupMember->id
                )
            );
        }
    }
}
