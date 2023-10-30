<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\Integrations\GroupLimitExceededException;
use App\Exceptions\Integrations\NoMembersToSendException;
use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Base class for all automated marketing services.  Colloquially, we refer to these as
 * "autoresponders"
 *
 * @package App\Services\MarketingAutomation
 */
abstract class AbstractMarketingService
{
    /**
     * Determines if the service requires a group member email for that member's addition to this service
     *
     * @var bool
     */
    public const EMAIL_IS_REQUIRED = true;

    /**
     * Limit of supported groups for subscribe all functionality
     *
     * @var int
     */
    public const SUBSCRIBE_GROUPS_LIMIT = 1;

    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName;

    /**
     * Any credentials and parameters needed to access this service.  These are categorized by Facebook group IDs
     *
     * @var array
     */
    protected static array $apiInfo = [];

    /**
     * @var string shows this text for the Facebook group ID if an exception
     *             is thrown from that service.
     */
    public const FACEBOOK_GROUP_ID = 'FACEBOOK GROUP ID';

    /**
     * Resets the caching variables on the load
     */
    public function __construct()
    {
        self::$apiInfo = [];
    }

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
    abstract public static function subscribe(GroupMembers $groupMember): void;

    /**
     * Subscribes a set of group members to the mailing list for this marketing service
     *
     * This is the generic implementation which should be overridden to use batch processing
     * instead of individual API calls
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
            } catch (InvalidStateException $invalidStateException) {
                /* a predicted potential error has occurred */
                Bugsnag::notifyException($invalidStateException);
                $groupMember->update(['respond_status' => $invalidStateException->getMessage()]);
            } catch (Exception | GuzzleException $e) {
                /* Unexpected/Unknown Error */
                Bugsnag::notifyException($e);
                $groupMember->update(['respond_status' => GroupMembers::RESPONSE_STATUSES['ERROR']]);
            }
        }
    }

    /**
     * Gets any credentials and parameters needed to access this service
     *
     * @param int $facebookGroupId The ID of the group for which we wish to retrieve the autoresponder login info
     *
     * @return object a plain old object of login credentials
     */
    protected static function getApiInfo(int $facebookGroupId): object
    {
        /* If the info is already cached, we just return the cached value */
        if (@static::$apiInfo[$facebookGroupId]) {
            return static::$apiInfo[$facebookGroupId];
        }

        $emailMarketingService = AutoResponder::with(['group', 'group.owner'])
            ->where('group_id', $facebookGroupId)
            ->where('responder_type', static::$serviceName)
            ->first();

        static::$apiInfo[$facebookGroupId] = json_decode($emailMarketingService->responder_json);
        static::$apiInfo[$facebookGroupId]->owner = $emailMarketingService->group->owner;

        return static::$apiInfo[$facebookGroupId];
    }

    /**
     * Determines if an HTTP response code represents a successful response
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status#successful_responses
     *
     * @param int $statusCode HTTP response code
     * @return bool true if the response code represents a successful response, otherwise false
     */
    protected static function isSuccessResponseCode(int $statusCode): bool
    {
        return ($statusCode >= 200) && ($statusCode < 300);
    }

    /**
     * Check if group limit is reached
     *
     * @param int $groupsNumber to be checked for limitation
     */
    public static function isGroupNumberOverLimit(int $groupsNumber): bool
    {
        return $groupsNumber > static::SUBSCRIBE_GROUPS_LIMIT;
    }

    /**
     * Validates provided group members before subscribe all
     *
     * @param Collection $groupMembers to be validated
     *
     * @throws GroupLimitExceededException if count of groups exceeded supported value
     * @throws NoMembersToSendException if provided group members are empty
     */
    public static function validateBeforeSubscribeAll(Collection $groupMembers)
    {
        if (static::isGroupNumberOverLimit($groupMembers->unique('group_id')->count())) {
            throw new GroupLimitExceededException();
        }

        if ($groupMembers->isEmpty()) {
            throw new NoMembersToSendException();
        }
    }

    /**
     * Get members with email if email is required
     *
     * @param Collection $groupMembers group members to be checked
     *
     * @return Collection group members with email
     */
    public static function getMembersWithEmailIfEmailIsRequired(Collection $groupMembers): Collection
    {
        if (static::EMAIL_IS_REQUIRED) {
            GroupMembers::whereIn('id', $groupMembers->whereNull('email')->pluck('id'))
                ->update(['respond_status' => GroupMembers::RESPONSE_STATUSES['NO_EMAIL']]);

            return $groupMembers->whereNotNull('email');
        }

        return $groupMembers;
    }
 
    /**
     * More info and details about an exception thrown with
     * InvalidStateException exception class.
     *
     * @param string $status status of the service's action.
     * @param int $httpCode HTTP status code from the API response.
     * @param object|null $extraParameters which contain the host subdomain,
     *               the API credentials for authentication,
     *               and the meta tags to associate with the
     *               contact, i.e. {host_name, api_key, activeTags}.
     * @param int|null $groupMemberId ID of the group member.
     * @param int|null $responseCode response code from the API.
     * @param array $additionalServicesInfo that can contain the information
     *              about different marketing services that are integrated
     *              in the application.
     *
     * @return string with all information that will be thrown as an exception
     *                in the appropriate services.
     *
     */
    public static function formatExceptionDetails(
        string $status,
        int $httpCode,
        object $extraParameters = null,
        int $groupMemberId = null,
        int $responseCode = null,
        array $additionalServicesInfo = []
    ): string {
        $extraParameters->api_key = (@$extraParameters->api_key ? '<redacted>' : '<missing>');

        $message = ($groupMemberId ? "MEMBER ID: {$groupMemberId}. " : null) .
            'RESPONSE STATUS: ' . $status .
            '. SERVICE: ' . static::class .
            '. API INFO: ' . json_encode($extraParameters) .
            '. HTTP STATUS: ' . $httpCode . '. ' .
            ($responseCode ? "RESPONSE CODE: $responseCode" : null);

        return $message . str_replace(
            '+',
            ' ',
            str_replace(
                '=',
                ': ',
                http_build_query($additionalServicesInfo, '', '. ')
            )
        );
    }
}
