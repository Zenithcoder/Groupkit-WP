<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use GuzzleHttp\Client;
use App\GroupMembers;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\HttpFoundation\Response;
use Exception;

/**
 * Used to interact with OntraPort to sync contacts
 *
 * @package App\Services\MarketingAutomation
 */
class OntraPortService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'OntraPort';

    /**
     * Subscribes an individual group member to the OntraPort marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this OntraPort marketing platform
     *
     * @throws GuzzleException
     *      if there is a problem connecting with the OntraPort service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the OntraPort marketing service
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

        app(OntraPortService::class)->addOrUpdateContact($groupMember, $extraParameters);
    }

    /**
     * Adds or update this contact on OntraPort
     *
     * @param GroupMembers $groupMember
     *          that will be added to the OntraPort contact list
     * @param object $extraParameters
     *          which contain the app id app key for authentication,
     *          i.e. {app_id, app_key}
     *
     * @return void
     *
     * @throws GuzzleException
     *              if there is a problem with calling the OntraPort API
     *              if the response is not in the expected format
     * @throws InvalidStateException
     *              if the contact data was invalid to be added or updated to the OntraPort
     */
    private static function addOrUpdateContact(GroupMembers $groupMember, object $extraParameters): void
    {
        $requestParams = "firstname=" . $groupMember->f_name;
        $requestParams .= "&lastname=" . $groupMember->l_name;
        $requestParams .= "&email=" . urlencode($groupMember->email);

        $client = app(Client::class, ['verify' => false]);

        $response = $client->post(
            "https://api.ontraport.com/1/Contacts/saveorupdate",
            [
                'headers' => [
                    'Api-Key' => $extraParameters->app_key,
                    'Api-Appid' => $extraParameters->app_id,
                    'Content-Type' => "application/x-www-form-urlencoded",
                ],
                'body' => $requestParams,
                'http_errors' => false,
            ]
        );

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
     * Verifies OntraPort credentials provided by a user.
     *
     * @param string $appKey contains application key of OntraPort integration
     * @param string $appId contains application id of OntraPort integration
     *
     * @return array containing successful message with below keys
     * "code" that contains 0 if request is successful
     * "data" that contains object-specific attributes and data
     * "account_id" contains ID of the account making the API call
     * "misc" contains other details.
     * if the Verification is successful otherwise an error message
     *
     * @throws GuzzleException if there is a problem with calling the OntraPort API
     */
    public static function verifyCredentials(string $appKey, string $appId): array
    {
        $url = "https://api.ontraport.com/1/Contacts?start=0&range=1";
        $client = new Client(['verify' => false]);

        try {
            $client->get(
                $url,
                [
                    'headers' => [
                        'Api-Key' => $appKey,
                        'Api-Appid' => $appId,
                    ]
                ]
            );
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());

            return [
                'success' => false,
                'message' => __('Invalid Request'),
                'code' => Response::HTTP_BAD_REQUEST,
            ];
        }

        return [
            'success' => true,
            'message' => __('Verification completed successfully'),
            'code' => Response::HTTP_OK,
        ];
    }
}
