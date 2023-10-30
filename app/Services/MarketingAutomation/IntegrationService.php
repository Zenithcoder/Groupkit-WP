<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\Integrations\GroupLimitExceededException;
use App\Exceptions\Integrations\NoMembersToSendException;
use App\FacebookGroups;
use App\GroupMembers;

/**
 * Class IntegrationService connects all the Marketing Automation Services with group members
 *
 * @package App\Services\MarketingAutomation
 */
class IntegrationService
{
    /**
     * Sends group members to the Facebook email marketing services
     *
     * @param array $groupMembersId for containing group members id
     * @param bool $requestIsFromExtension true if group members comes from Google Chrome extension,
     *                                     otherwise and by default false
     *
     * @return void
     *
     * @throws GroupLimitExceededException if count of groups exceeded supported value
     * @throws NoMembersToSendException if provided group members are empty
     */
    public function send(array $groupMembersId, bool $requestIsFromExtension = false): void
    {
        $groupMembers = app(GroupMembers::class)
            ->with(['approvedBy', 'invited_by', 'tags'])
            ->whereIn('id', $groupMembersId)
            ->get();

        AbstractMarketingService::validateBeforeSubscribeAll($groupMembers);

        $emailMarketingServices = FacebookGroups::findOrFail($groupMembers->pluck('group_id')->first())->responder;

        /* first, if there are no integrations, we don't need to check anything */
        if ($emailMarketingServices->isEmpty()) {
            GroupMembers::whereIn('id', $groupMembers->pluck('id'))->update(['respond_status' => '']);
            return;
        }

        foreach ($emailMarketingServices as $emailMarketingService) {
            /**
             * @var AbstractMarketingService $serviceClass
             */
            $serviceClass = AutoResponder::SERVICE_TYPES[$emailMarketingService->responder_type];

            app($serviceClass)->subscribeAll($groupMembers, $requestIsFromExtension);
        }
    }
}
