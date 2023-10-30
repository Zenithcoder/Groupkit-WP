<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\InvalidStateException;
use App\Exceptions\Integrations\ActiveCampaign\AuthorizationException;
use App\Exceptions\Integrations\ActiveCampaign\PaymentIssuesException;
use App\Exceptions\Integrations\ActiveCampaign\RateLimitException;
use App\Exceptions\Integrations\ActiveCampaign\RequestUnprocessableException;
use App\Exceptions\Integrations\ActiveCampaign\ResourceNotExistException;
use App\GroupMembers;
use Illuminate\Database\Eloquent\Collection;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\Http;

/**
 * Used to interact with the ActiveCampaign email automation service
 * @link https://developers.activecampaign.com/reference
 *
 * @package App\Services\MarketingAutomation
 */
class ActiveCampaignService extends AbstractMarketingService
{
    /**
     * @const int The http status code for failed requests due to payment issues
     */
    public const REQUEST_UNPROCESSABLE_DUE_TO_PAYMENT_ISSUES = 402;

    /**
     * @const int The http status code for failed requests due to authorization/authentication issues
     */
    public const REQUEST_UNAUTHORIZED = 403;

    /**
     * @const int The http status code if requested resource does not exist
     */
    public const REQUEST_RESOURCE_NOT_EXIST = 404;

    /**
     * @const int The http status code for unprocessable requests
     */
    public const REQUEST_UNPROCESSABLE = 422;

    /**
     * @const int The http status code for no result found
     */
    public const NO_RESULT_FOUND = 401;

    /**
     * @const int The http status code for rate limit exceeded
     */
    public const RATE_LIMIT_EXCEEDED = 429;

    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'ActiveCampaign';

    /**
     * @var string shows this text for a contact from Active Campaign
     *             if an exception is thrown from that service.
     */
    public const ACTIVE_CAMPAIGN_CONTACT_ID = 'ACTIVE CAMPAIGN CONTACT ID';

    /**
     * Subscribes an individual group member to the mailing list for this marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this marketing platform
     *
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     * @throws AuthorizationException
     *             if the API key is invalid or the user is not authorized to access the API
     * @throws PaymentIssuesException
     *             if the request failed due to payment issues
     * @throws RateLimitException
     *             if the request failed due to rate limit exceeded
     * @throws ResourceNotExistException
     *             if the request failed due to the requested resource not existing
     * @throws RequestUnprocessableException
     *             if the request failed due to the request being unprocessable
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

        /* Register or updated this member on ActiveCampaign */
        $activeCampaignContactId = static::addOrUpdateContact($groupMember, $extraParameters);

        /* Subscribe the member to the appropriate list */
        static::addMemberToMailingList($activeCampaignContactId, $extraParameters);

        /* Add any meta tags to contact */
        if (isset($extraParameters->activeTags->value)) {
            static::addTags($activeCampaignContactId, $extraParameters);
        }
    }

    /**
     * Adds or syncs this contact on ActiveCampaign
     *
     * @param GroupMembers $groupMember
     *          that will be added to the ActiveCampaign mailing list
     * @param object $extraParameters
     *          which contain the host subdomain and the API for authentication,
     *          i.e. {host_name, api_key, activeList}
     *
     * @return string|int The group member's contact ID used in the ActiveCampaign system
     *
     * @throws InvalidStateException
     *              if the contact data was invalid to be added or updated to the ActiveCampaign
     * @throws AuthorizationException
     *             if the API key is invalid or the user is not authorized to access the API
     * @throws PaymentIssuesException
     *             if the request failed due to payment issues
     * @throws RateLimitException
     *             if the request failed due to rate limit exceeded
     * @throws ResourceNotExistException
     *             if the request failed due to the requested resource not existing
     * @throws RequestUnprocessableException
     *             if the request failed due to the request being unprocessable
     */
    private static function addOrUpdateContact(GroupMembers $groupMember, object $extraParameters)
    {
        $subDomain = $extraParameters->host_name;
        $apiKey = $extraParameters->api_key;

        $response = Http::withHeaders(['Api-Token' => $apiKey])
            ->post(
                "https://{$subDomain}.api-us1.com/api/3/contact/sync",
                [
                    'contact' => [
                        'email' => $groupMember->email,
                        'firstName' => $groupMember->f_name,
                        'lastName' => $groupMember->l_name,
                    ],
                ]
            );

        if (!static::isSuccessResponseCode($response->status())) {
            switch ($response->status()) {
                case static::REQUEST_UNPROCESSABLE_DUE_TO_PAYMENT_ISSUES:
                    throw new PaymentIssuesException();
                case static::REQUEST_UNAUTHORIZED:
                    throw new AuthorizationException();
                case static::REQUEST_UNPROCESSABLE:
                    throw new RequestUnprocessableException();
                case static::RATE_LIMIT_EXCEEDED:
                    throw new RateLimitException();
                case static::REQUEST_RESOURCE_NOT_EXIST:
                case static::NO_RESULT_FOUND:
                    throw new ResourceNotExistException();
                default:
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

        $subscriptionDetails = $response->object();

        return $subscriptionDetails->contact->id;
    }

    /**
     * Subscribes an ActiveCampaign contact to a specified mailing list
     *
     * @param string|int $activeCampaignContactId
     *          The ActiveCampaign unique id for this group member who will be subscribed to the specified list
     * @param object $extraParameters
     *          which contain the host subdomain, the API credentials for authentication, and the mailing
     *          list to which the member will be subscribed, i.e. {host_name, api_key, activeList}
     *
     * @throws InvalidStateException
     *          if the contact was not able to be subscribed to the specified list
     * @throws AuthorizationException
     *          if the API key is invalid or the user is not authorized to access the API
     * @throws PaymentIssuesException
     *          if the request failed due to payment issues
     * @throws RateLimitException
     *          if the request failed due to rate limit exceeded
     * @throws ResourceNotExistException
     *          if the request failed due to the requested resource not existing
     * @throws RequestUnprocessableException
     *          if the request failed due to the request being unprocessable
     */
    private static function addMemberToMailingList($activeCampaignContactId, object $extraParameters)
    {
        $subDomain = $extraParameters->host_name;
        $apiKey = $extraParameters->api_key;

        $response = Http::withHeaders(['Api-Token' => $apiKey])
            ->post(
                "https://{$subDomain}.api-us1.com/api/3/contactLists",
                [
                    'contactList' =>
                        [
                            'contact' => $activeCampaignContactId,
                            'list' => $extraParameters->activeList->value,
                            'status' => 1,
                        ],
                ]
            );

        if (!static::isSuccessResponseCode($response->status())) {
            switch ($response->status()) {
                case static::REQUEST_UNPROCESSABLE_DUE_TO_PAYMENT_ISSUES:
                    throw new PaymentIssuesException();
                case static::REQUEST_UNAUTHORIZED:
                    throw new AuthorizationException();
                case static::REQUEST_UNPROCESSABLE:
                    throw new RequestUnprocessableException();
                case static::RATE_LIMIT_EXCEEDED:
                    throw new RateLimitException();
                case static::REQUEST_RESOURCE_NOT_EXIST:
                case static::NO_RESULT_FOUND:
                    throw new ResourceNotExistException();
                default:
                    throw new InvalidStateException(
                        GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                        self::formatExceptionDetails(
                            GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
                            $response->status(),
                            $extraParameters,
                            null,
                            null,
                            [self::ACTIVE_CAMPAIGN_CONTACT_ID => $activeCampaignContactId]
                        )
                    );
            }
        }
    }

    /**
     * Adds meta-tags to the specified ActiveCampaign contact
     *
     * @param string|int $activeCampaignContactId
     *          The ActiveCampaign unique id for this group member who will be subscribed to the specified list
     * @param object $extraParameters
     *          which contain the host subdomain, the API credentials for authentication, and the meta
     *          tags to associate with the contact, i.e. {host_name, api_key, activeTags}
     *
     * @throws InvalidStateException
     *          if there is a problem with adding tags to the group members contact details
     */
    private static function addTags($activeCampaignContactId, object $extraParameters)
    {
        $subDomain = $extraParameters->host_name;
        $apiKey = $extraParameters->api_key;

        $response = Http::withHeaders(['Api-Token' => $apiKey])
            ->post(
                "https://{$subDomain}.api-us1.com/api/3/contactTags",
                [
                    'contactTag' => [
                        'contact' => $activeCampaignContactId,
                        'tag' => $extraParameters->activeTags->value,
                    ],
                ]
            );

        if (!static::isSuccessResponseCode($response->status())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
                    $response->status(),
                    $extraParameters,
                    null,
                    null,
                    [self::ACTIVE_CAMPAIGN_CONTACT_ID => $activeCampaignContactId]
                )
            );
        }
    }

    /**
     * Subscribes a set of group members to the active campaign mailing list
     *
     * @param Collection $groupMembers
     *            The group members that will be subscribed to this marketing platform
     * @param bool $requestIsFromExtension
     *            True if group members comes from Google Chrome extension, otherwise false
     */
    public static function subscribeAll(Collection $groupMembers, bool $requestIsFromExtension): void
    {
        $groupMembers = static::getMembersWithEmailIfEmailIsRequired($groupMembers);

        foreach ($groupMembers as $groupMember) {
            try {
                static::subscribe($groupMember);

                $groupMember->respond_status = GroupMembers::RESPONSE_STATUSES['ADDED'];
                if ($requestIsFromExtension) {
                    $groupMember->respond_date_time = now();
                }

                $groupMember->save();
            } catch (AuthorizationException $authorizationException) {
                $groupMember->update([
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE']
                ]);
            } catch (PaymentIssuesException $paymentIssuesException) {
                $groupMember->update([
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_PAYMENT_ISSUE']
                ]);
            } catch (ResourceNotExistException $resourceNotExistException) {
                $groupMember->update([
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST']
                ]);
            } catch (RequestUnprocessableException $requestUnprocessableException) {
                $groupMember->update([
                    'respond_status' => GroupMembers::RESPONSE_STATUSES['ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE']
                ]);
            } catch (RateLimitException $rateLimitException) {
                Bugsnag::notifyException($rateLimitException);
            } catch (InvalidStateException $invalidStateException) {
                Bugsnag::notifyException($invalidStateException);
                $groupMember->update(['respond_status' => $invalidStateException->getMessage()]);
            }
        }
    }
}
