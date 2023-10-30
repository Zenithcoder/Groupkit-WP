<?php

namespace App\Jobs;

use App\FacebookGroups;
use App\Services\MarketingAutomation\IntegrationService;
use App\Services\TagService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * This job runs when the request for adding/removing tags to the
 * members is for more than 100 members.
 */
class ManageTagsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Array of the tags.
     * optional recommended_tags_to_add to be stored as group recommended tags,
     * optional recommended_tags_to_delete that will be removed from group recommended tags,
     * optional tags_to_add for the provided $membersIds,
     * optional tags_to_delete from the provided $membersIds
     *
     * @var array
     */
    private array $tags;

    /**
     * Group ID.
     *
     * @var int
     */
    private int $facebookGroupId;

    /**
     * Array of the group's members IDs.
     *
     * @var array
     */
    private array $groupMembersIds;

    /**
     * TagService instance.
     *
     * @var TagService
     */
    private TagService $tagService;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $tags, int $facebookGroupId, array $groupMembersIds)
    {
        $this->tags = $tags;
        $this->facebookGroupId = $facebookGroupId;
        $this->groupMembersIds = $groupMembersIds;
        $this->tagService = new TagService();
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws Exception if an error occurs on MySQL import/update queries
     */
    public function handle(): void
    {
        $this->tagService->manageTags($this->tags, $this->facebookGroupId, $this->groupMembersIds);

        #todo after moving re-sending to the integrations into jobs this should be handled in another job
        if (
            FacebookGroups::find($this->facebookGroupId)->has('googleSheetIntegration')->exists()
            && count($this->groupMembersIds) <= config('const.MAXIMUM_MEMBERS_NUMBER_TO_SEND')
        ) {
            app(IntegrationService::class)->send($this->groupMembersIds, false);
        }
    }
}
