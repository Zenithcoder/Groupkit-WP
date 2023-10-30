<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use GuzzleHttp\Client;
use App\GroupMembers;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;
use Exception;

/**
 * Used to interact with InfusionSoft to sync contacts
 *
 * @package App\Services\MarketingAutomation
 */
class InfusionSoftService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'InfusionSoft';

    /**
     * The URL of InfusionSoft service which is used to call InfusionSoft APIs.
     *
     * @var string
     */
    protected static string $serviceUrl = 'https://api.infusionsoft.com/';

    /**
     * @var string shows this text for the GInfusion Soft contact ID
     *             if an exception is thrown from that service.
     */
    public const INFUSION_SOFT_CONTACT_ID = 'INFUSION SOFT CONTACT ID';

    /**
     * Subscribes an individual group member to the InfusionSoft marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this InfusionSoft marketing platform
     *
     * @throws GuzzleException
     *      if there is a problem connecting with the InfusionSoft service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the InfusionSoft marketing service
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

        $infusionSoftContactId = app(InfusionSoftService::class)->addOrUpdateContact($groupMember, $extraParameters);

        if (isset($extraParameters->activeTags) && count($extraParameters->activeTags) > 0) {
            static::addTagsToContact($infusionSoftContactId, $extraParameters);
        }
    }

    /**
     * Adds or update this contact on InfusionSoft
     *
     * @param GroupMembers $groupMember
     *          that will be added to the InfusionSoft contact list
     * @param object $extraParameters
     *          which contain the app id app key for authentication,
     *          i.e. {client id, client secret, access token, refresh token}
     *
     * @return int the InfusionSoft unique contact id for this group member
     *
     * @throws GuzzleException
     *              if there is a problem with calling the InfusionSoft API
     *              if the response is not in the expected format
     * @throws InvalidStateException
     *              if the contact data was invalid to be added or updated to the InfusionSoft
     */
    private static function addOrUpdateContact(GroupMembers $groupMember, object $extraParameters): int
    {
        $response = app(Client::class)->put(
            static::$serviceUrl . 'crm/rest/v1/contacts?access_token=' . $extraParameters->accessToken,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'email_addresses' => [
                        [
                            'email' => $groupMember->email,
                            'field' => 'EMAIL1',
                        ],
                    ],
                    'family_name' => $groupMember->l_name,
                    'given_name' => $groupMember->f_name,
                    'duplicate_option' => 'Email', //Performs duplicate checking by 'Email'
                ],
                'http_errors' => false,
            ]
        );

        $result = json_decode($response->getBody());

        // HTTP status code of the response.
        $statusCode = $response->getStatusCode();
        if (!static::isSuccessResponseCode($statusCode)) {
            if (
                isset($result->fault->faultstring)
                && in_array(
                    $result->fault->faultstring,
                    ['Access Token expired', 'Invalid Access Token']
                )
            ) {
                // Refreshing access token from older details
                $refreshedApiInfo = static::getRefreshedApiInfo($groupMember, $extraParameters);

                if (!isset($refreshedApiInfo->accessToken) || !isset($refreshedApiInfo->refreshToken)) {
                    throw new InvalidStateException(
                        GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                        self::formatExceptionDetails(
                            GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                            $statusCode,
                            $extraParameters,
                            $groupMember->id
                        )
                    );
                }

                return static::addOrUpdateContact($groupMember, $refreshedApiInfo);
            }
        }

        return $result->id;
    }

    /**
     * Requests access token based on client id and client secret provided by a user.
     *
     * @param string $clientId contains client id of InfusionSoft integration
     * @param string $clientSecret contains the client secret key of InfusionSoft integration
     * @param string $authorizeCode contains the authorization code which returns once
     *               the client id and client secret keys are verified
     *
     * @return array containing successful message with below keys
     *         'scope' that contains default 'full' value.
     *         'access_token' that contains string which is used to call other API's of infusionsoft.
     *         'token_type' contains default 'bearer' value.
     *         'expires_in' contains digits in seconds which shows time to expire access token.
     *         'refresh_token' contains string that used to regenerate access token.
     *
     * if the Verification is successful otherwise an error message
     */
    public static function requestAccessToken(string $clientId, string $clientSecret, string $authorizeCode): array
    {
        try {
            $response = app(Client::class)->post(
                static::$serviceUrl . 'token',
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => http_build_query([
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'code' => $authorizeCode,
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => url('/infusionSoftAuth/callback'),
                    ]),
                ]
            );

            $responseBody = [];
            if ($response->getStatusCode()) {
                $responseBody = json_decode($response->getBody());
            }

            return [
                'message' => __('Verification completed successfully.'),
                'code' => Response::HTTP_OK,
                'body' => $responseBody,
            ];
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());

            return [
                'message' => __('Invalid Request'),
                'code' => Response::HTTP_BAD_REQUEST,
            ];
        }
    }

    /**
     * That regenerate new access token.
     *
     * @param string $clientId contains client id of InfusionSoft integration
     * @param string $clientSecret contains client secret key of InfusionSoft integration
     * @param string $refreshToken contains string that used to regenerate access token of InfusionSoft integration
     *
     * @return array containing successful message with below keys
     * 'scope' that contains default 'full' value.
     * 'access_token' that contains string which is used to call other API's of infusionsoft.
     * 'token_type' contains default 'bearer' value.
     * 'expires_in' contains digits in seconds which shows time to expire access token.
     * 'refresh_token' contains string that used to regenerate access token.
     *
     * if the Verification is successful otherwise an error message
     */
    public static function refreshAccessToken(
        string $clientId,
        string $clientSecret,
        string $refreshToken
    ): array {
        try {
            $response = app(Client::class)->post(
                static::$serviceUrl . 'token',
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $clientSecret),
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => http_build_query([
                        'client_id' => $clientId,
                        'client_secret' => $clientSecret,
                        'refresh_token' => $refreshToken,
                        'grant_type' => 'refresh_token',
                        'redirect_uri' => url('/infusionSoftAuth/callback'),
                    ]),
                ]
            );

            $responseBody = [];
            if ($response->getStatusCode()) {
                $responseBody = json_decode($response->getBody());
            }

            return [
                'message' => __('Token has been refreshed successfully.'),
                'code' => Response::HTTP_OK,
                'body' => $responseBody,
            ];
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());

            return [
                'message' => __('Invalid Request'),
                'code' => Response::HTTP_BAD_REQUEST,
            ];
        }
    }

    /** That returns regenerated new access token.
     *
     * @param object $groupMember contains group members details with group details
     * @param object $extraParameters contains client id, client secret, authorize code, access token, refresh token
     *
     * @return object A valid Infusionsoft API access information.
     */
    private static function getRefreshedApiInfo(object $groupMember, object $extraParameters): object
    {
        try {
            $refreshAccessToken = app(InfusionSoftService::class)->refreshAccessToken(
                $extraParameters->clientId,
                $extraParameters->clientSecret,
                $extraParameters->refreshToken
            );

            if (!isset($refreshAccessToken['body']) || !static::getApiInfo($groupMember->group_id)) {
                throw new InvalidStateException(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    self::formatExceptionDetails(
                        GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                        Response::HTTP_BAD_REQUEST,
                        $extraParameters,
                        $groupMember->id
                    )
                );
            }

            $autoRespondersData = json_encode([
                'clientId' => $extraParameters->clientId,
                'clientSecret' => $extraParameters->clientSecret,
                'accessToken' => $refreshAccessToken['body']->access_token,
                'refreshToken' => $refreshAccessToken['body']->refresh_token,
            ]);

            AutoResponder::where('group_id', $groupMember->group_id)
                ->where('responder_type', static::$serviceName)
                ->update(['responder_json' => $autoRespondersData]);

            //Updating new access token & refresh token.
            $extraParameters->accessToken = $refreshAccessToken['body']->access_token;
            $extraParameters->refreshToken = $refreshAccessToken['body']->refresh_token;
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());

            return (object)['message' => __('Invalid Request')];
        }

        return $extraParameters;
    }

    /**
     * Returns all available client tags
     *
     * @param int $facebookGroupId The ID of the group for which we wish to retrieve the autoresponder login info
     *
     * @return array with a message, response code, and tags when the service response is completed successfully
     */
    public static function getTags(int $facebookGroupId): array
    {
        try {
            $extraParameters = static::getApiInfo($facebookGroupId);
            $url = sprintf(
                '%scrm/rest/v1/tags?access_token=%s',
                static::$serviceUrl,
                $extraParameters->accessToken,
            );

            $response = Http::withHeaders(['Content-Type' => 'application/json'])->get($url);

            // Throw an exception if a client or server error occurred...
            $response->throw();

            $tags = [];
            if (static::isSuccessResponseCode($response->status())) {
                $responseJson = $response->json();
                $tags = $responseJson['tags'] ?? [];
            }

            return [
                'message' => __('Success'),
                'code' => Response::HTTP_OK,
                'tags' => $tags,
            ];
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());

            return [
                'message' => __('Invalid Request'),
                'code' => Response::HTTP_BAD_REQUEST,
            ];
        }
    }

    /**
     * Adds tags to the specified InfusionSoft contact
     *
     * @param int $infusionSoftContactId
     *          The InfusionSoft unique id for group member
     * @param object $extraParameters
     *          which contain the app id app key for authentication,
     *          i.e. {client id, client secret, access token, refresh token, activeTag}
     *
     * @throws RequestException
     *          if there is a problem with calling the InfusionSoft API
     * @throws InvalidStateException
     *          if there is a problem with adding tag to the group members contact details
     */
    private static function addTagsToContact(int $infusionSoftContactId, object $extraParameters): void
    {
        $url = sprintf(
            '%scrm/rest/v1/contacts/%s/tags?access_token=%s',
            static::$serviceUrl,
            $infusionSoftContactId,
            $extraParameters->accessToken,
        );

        $tagIds = array_map(function ($activeTag) {
            return $activeTag->value;
        }, $extraParameters->activeTags);

        $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post(
                $url,
                [
                    'tagIds' => $tagIds,
                ]
            );

        // Throw an exception if a client or server error occurred...
        $response->throw();

        if (!static::isSuccessResponseCode($response->status())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
                    $response->status(),
                    $extraParameters,
                    null,
                    null,
                    [self::INFUSION_SOFT_CONTACT_ID => $infusionSoftContactId]
                )
            );
        }
    }
}
