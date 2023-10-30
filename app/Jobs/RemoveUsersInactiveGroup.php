<?php

namespace App\Jobs;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\User;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Exception;

/**
 * Class RemoveUsersInactiveGroup soft deletes multiple groups,group member,auto_responders as background job
 *
 * @package App\Jobs
 */
class RemoveUsersInactiveGroup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var int including id of User {@see User}
     */
    private int $userId;

    /**
     * @var int id of the {@see FacebookGroups} that groups to be deleted with related group members
     * and auto_responders.
     */
    private int $groupIdToPreserve;

    /**
     * Sets internal properties
     *
     * @param int $userId id of {@see User}
     * @param int $groupIdToPreserve id of {@see FacebookGroups}
     */
    public function __construct(int $userId, int $groupIdToPreserve)
    {
        $this->userId = $userId;
        $this->groupIdToPreserve = $groupIdToPreserve;
    }

    /**
     * Executes the job once plans expiry date is reached
     * and removes all other groups with related group members and auto_responders except active group id which stores
     * at `groupIdToPreserve` parameter.
     *
     * @return void
     */
    public function handle()
    {
        $groupsToDelete = FacebookGroups::where('user_id', $this->userId)
            ->where('id', '!=', $this->groupIdToPreserve)
            ->get();

        if ($groupsToDelete) {
            $groupsIds = $groupsToDelete->pluck('id');

            GroupMembers::whereIn('group_id', $groupsIds)->delete();
            AutoResponder::whereIn('group_id', $groupsIds)->delete();

            FacebookGroups::whereIn('id', $groupsIds)->delete();
        }
    }

    /**
     * The unique ID of the job.
     *
     * @return int
     */
    public function uniqueId(): int
    {
        return $this->userId;
    }

    /**
     * If the job failed to process we are logging that error into the bugsnag & inside a log file.
     *
     * @param Exception $exception
     * @return void
     */
    public function failed(Exception $exception)
    {
        Bugsnag::notifyException($exception);
        logger()->info($exception->getMessage());
    }
}
