<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\Integrations\GroupLimitExceededException;
use App\Exceptions\Integrations\NoMembersToSendException;
use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Database\Eloquent\Collection;

/**
 * Used to interact with the ConvertKit email automation service
 * @link https://developers.convertkit.com/
 *
 * @package App\Services\MarketingAutomation
 */
class ConvertKitService extends AbstractMarketingService
{
    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'ConvertKit';

    /**
     * The API URL of this marketing service
     *
     * @var string
     */
    public const URL = 'https://api.convertkit.com/v3/';

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
        static::createCustomFieldsIfNotExist($groupMember->group_id);
        static::addOrUpdateContact($groupMember);
    }

    /**
     * Adds/updates provided group members to the integration
     * If there is no group members or group members belong to more than one group, stops the request
     *
     * @param Collection $groupMembers to be added/updated to the integration
     * @param bool $requestIsFromExtension true if group members comes from Google Chrome extension, otherwise false
     *
     * @throws GroupLimitExceededException if count of groups exceeded supported value
     * @throws NoMembersToSendException if provided group members are empty
     * @throws RequestException if there is a problem connecting with the marketing service
     */
    public static function subscribeAll(Collection $groupMembers, bool $requestIsFromExtension): void
    {
        static::validateBeforeSubscribeAll($groupMembers);
        static::createCustomFieldsIfNotExist($groupMembers->first()->group_id);

        if (static::EMAIL_IS_REQUIRED) {
            $ids = $groupMembers->whereNull('email')->pluck('id');

            GroupMembers::whereIn('id', $ids)
                ->update(['respond_status' => GroupMembers::RESPONSE_STATUSES['NO_EMAIL']]);

            $groupMembers = $groupMembers->whereNotNull('email');
        }

        foreach ($groupMembers as $groupMember) {
            try {
                static::addOrUpdateContact($groupMember);

                $groupMember->respond_status = GroupMembers::RESPONSE_STATUSES['ADDED'];
                if ($requestIsFromExtension) {
                    $groupMember->respond_date_time = now();
                }
            } catch (InvalidStateException $e) {
                $groupMember->respond_date_time = $e->getMessage();
                Bugsnag::notifyException($e);
            } catch (Exception | RequestException $e) {
                $groupMember->respond_date_time = GroupMembers::RESPONSE_STATUSES['ERROR'];
                Bugsnag::notifyException($e);
            }

            $groupMember->save();
        }
    }

    /**
     * Determines if the product for lifetime subscription has been purchased according to the current environment
     *
     * @param GroupMembers $groupMember
     *      The group member that will be subscribed to this marketing platform
     *
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     * @throws RequestException
     *      if there is a problem connecting with the marketing service
     */
    private static function addOrUpdateContact(GroupMembers $groupMember): void
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

        $url = sprintf(
            "%sforms/%s/subscribe",
            static::URL,
            $extraParameters->activeList->value,
        );

        $requestData = [
            'api_key' => $extraParameters->api_key,
            'email' => $groupMember->email,
            'first_name' => $groupMember->f_name,
        ];

        $requestData = static::appendCustomFields($requestData, $groupMember);

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($url, $requestData);

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

        $response->throw(); # Throw an exception if a client or server error occurred...
    }

    /**
     * Adds custom fields to the $requestData if custom fields has integration key
     *
     * @param array $requestData with subscriber params (first_name, email, api_key)
     * @param GroupMembers $groupMember for getting custom field values
     *
     * @return array request data merged with custom fields if they exist
     */
    private static function appendCustomFields(array $requestData, GroupMembers $groupMember): array
    {
        $extraParameters = static::getApiInfo($groupMember->group_id);

        if (!isset($extraParameters->custom_labels_mapper)) {
            return $requestData;
        }

        $customFields = array_filter(
            $extraParameters->custom_labels_mapper,
            function (object $customLabel) {
                return isset($customLabel->key);
            }
        );

        if ($customFields) {
            foreach ($customFields as $customField) {
                $requestData['fields'][$customField->key] = $groupMember->{$customField->member_field} ?? "";
            }
        }

        return $requestData;
    }

    /**
     * Compares API's custom fields with custom fields in the database
     * If there is custom fields that missing in the API,
     * adds them via {@see \App\Services\MarketingAutomation\ConvertKitService::createCustomFields}
     * If there is no api secret or custom fields, stops the request
     *
     * @throws RequestException if there is a problem connecting with the marketing service
     */
    private static function createCustomFieldsIfNotExist(int $groupId): void
    {
        $extraParameters = static::getApiInfo($groupId);

        if (!isset($extraParameters->api_secret) || !isset($extraParameters->custom_labels)) {
            return;
        }

        $customFieldsToCreate = static::getCustomFieldsToCreate($groupId);

        if (!empty($customFieldsToCreate)) {
            $response = static::createCustomFields($groupId, $customFieldsToCreate);

            $responseData = $response->json();
            if ($responseData) {
                static::updateIntegrationData($groupId, $responseData);
            }
        }
    }

    /**
     * Sends provided custom fields to the create custom fields API
     *
     * @param int $groupId for getting this marketing service api secret
     * @param array $customFields to be sent
     *
     * @return Response containing created custom fields data (key, id, name)
     */
    private static function createCustomFields(int $groupId, array $customFields): Response
    {
        $extraParameters = static::getApiInfo($groupId);

        return Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post(
            static::URL . 'custom_fields',
            [
                'api_secret' => $extraParameters->api_secret,
                'label' => $customFields,
            ]
        );
    }

    /**
     * Updates marketing service data in the database and in the cache
     *
     * @param int $groupId for getting existing marketing data
     * @param array $newIntegrationData to be populated in the existing marketing data
     */
    private static function updateIntegrationData(int $groupId, array $newIntegrationData): void
    {
        $extraParameters = static::getApiInfo($groupId);

        $updatedIntegrationData = json_encode([
            'api_key' => $extraParameters->api_key,
            'api_secret' => $extraParameters->api_secret,
            'activeList' => [
                'label' => $extraParameters->activeList->label,
                'value' => $extraParameters->activeList->value,
            ],
            'custom_labels' => $extraParameters->custom_labels,
            'custom_labels_mapper' => array_map(function (object $customLabel) use ($newIntegrationData) {
                $formattedCustomLabel = [
                    'label' => $customLabel->label,
                    'member_field' => $customLabel->member_field,
                ];

                # new integration data could be jagged array if there is multiple custom fields
                if (is_array($newIntegrationData[array_key_first($newIntegrationData)])) {
                    $index = array_search($customLabel->label, array_column($newIntegrationData, 'label'));

                    if (is_int($index)) {
                        return array_merge($formattedCustomLabel, $newIntegrationData[$index]);
                    }
                } elseif (
                    isset($newIntegrationData['label'])
                    && $newIntegrationData['label'] === $customLabel->label
                ) {
                    return array_merge($formattedCustomLabel, $newIntegrationData);
                }

                return $formattedCustomLabel;
            }, $extraParameters->custom_labels_mapper),
        ]);

        static::$apiInfo[$groupId] = json_decode($updatedIntegrationData);

        AutoResponder::where('group_id', $groupId)
            ->where('responder_type', static::$serviceName)
            ->update(['responder_json' => $updatedIntegrationData]);
    }

    /**
     * Returns array of custom fields that are not currently part of integration custom fields
     *
     * @param int $groupId of the connected integration
     *
     * @return array of the custom fields that should be created
     *
     * @throws RequestException if there is a problem connecting with the marketing service
     */
    private static function getCustomFieldsToCreate(int $groupId): array
    {
        $extraParameters = static::getApiInfo($groupId);

        $url = sprintf(
            "%scustom_fields?api_key=%s",
            static::URL,
            $extraParameters->api_key,
        );

        $response = Http::withHeaders(['Content-Type' => 'application/json'])->get($url);

        $response->throw(); # Throw an exception if a client or server error occurred...

        $integrationCustomFields = $response->json()['custom_fields'];

        self::connectExistingFields($groupId, $integrationCustomFields);

        return array_diff($extraParameters->custom_labels, array_column($integrationCustomFields, 'label'));
    }

    /**
     * Connects existing custom fields from the integration with the database group's fields
     * if the database group's fields already exist in the integration
     *
     * @param int $groupId of the connected integration
     * @param array $integrationCustomFields including all custom fields from the user's integration
     *
     * @return void
     */
    private static function connectExistingFields(int $groupId, array $integrationCustomFields): void
    {
        $extraParameters = static::getApiInfo($groupId);

        if (empty($extraParameters->custom_labels_mapper)) {
            return;
        }

        $customFieldsToConnect = [];

        foreach ($extraParameters->custom_labels_mapper as $databaseCustomField) {
            if (
                isset($databaseCustomField->id)
                && isset($databaseCustomField->name)
                && isset($databaseCustomField->key)
            ) {
                continue; # if database custom field already have id, name and key, we are skipping updating that field
            }

            $index = array_search(
                $databaseCustomField->label,
                array_column($integrationCustomFields, 'label')
            );

            if ($index) {
                $customFieldsToConnect[] = $integrationCustomFields[$index];
            }
        }

        if ($customFieldsToConnect) {
            self::updateIntegrationData($groupId, $customFieldsToConnect);
        }
    }
}
