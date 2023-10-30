<?php

namespace App\Jobs;

use App\FacebookGroups;
use App\GroupMembers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class RemoveMembersJob soft deletes multiple group member as background job
 *
 * @todo implement ShouldBeUnique after Laravel upgrade {@see https://laravel.com/docs/8.x/queues#unique-jobs}
 */
class RemoveMembersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array including id of one or multiple {@see GroupMembers}
     */
    private array $memberIds;

    /**
     * @var int id of the {@see FacebookGroups} that group members are connected to
     */
    private int $groupId;

    /**
     * Sets internal properties
     *
     * @param array $memberIds one or more id of {@see GroupMembers} that will be soft deleted
     * @param int $groupId for determine what group members should be deleted
     */
    public function __construct(array $memberIds, int $groupId)
    {
        $this->memberIds = $memberIds;
        $this->groupId = $groupId;
    }

    /**
     * Soft deletes all provided members
     *
     * @return int number of removed members
     */
    public function handle(): int
    {
        $groupMembersIdChunks = array_chunk(
            $this->memberIds,
            config('database.connections.mysql.chunk_size')
        );

        $numberOfMembersRemoved = 0;
        
        foreach ($groupMembersIdChunks as $groupMembersIdChunk) {
            $numberOfMembersRemoved += GroupMembers::where('group_id', $this->groupId)
                ->whereIn('id', $groupMembersIdChunk)
                ->delete();
        }
        
        return $numberOfMembersRemoved;
    }
}
