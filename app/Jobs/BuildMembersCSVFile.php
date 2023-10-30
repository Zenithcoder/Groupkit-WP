<?php

namespace App\Jobs;

use App\Exceptions\FileIsNotCreatedException;
use App\FacebookGroups;
use App\GroupMembers;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Class BuildMembersCSVFile creates group members CSV in the storage
 *
 * @package App\Jobs
 */
class BuildMembersCSVFile implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var array of {@see GroupMembers} IDs
     */
    private array $memberIds;

    /**
     * @var string to store CSV file as
     */
    private string $fileName;

    /**
     * @var int {@see FacebookGroups} ID
     */
    private int $groupId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $memberIds, string $fileName, int $groupId)
    {
        $this->memberIds = $memberIds;
        $this->fileName = $fileName;
        $this->groupId = $groupId;
    }

    /**
     * Generates unique ID for this job, which is then automatically namespaced by the job class by the caller
     *
     * @return string representing the unique id of this job
     */
    public function uniqueId()
    {
        return  $this->groupId . '|' . implode(',', $this->memberIds);
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws FileIsNotCreatedException if group members csv file is not created
     */
    public function handle()
    {
        $members = GroupMembers::selectRaw(
            "group_members.id,
            group_members.f_name,
            group_members.l_name,
            CONCAT(group_members.f_name, ' ', group_members.l_name) as full_name,
            group_members.email,
            CONCAT(invited.f_name, ' ', invited.l_name) as invitedBy,
            group_members.fb_id,
            if(group_members.a1 != '', group_members.a1, '-') as a1,
            if(group_members.a2 != '', group_members.a2, '-') as a2,
            if(group_members.a3 != '', group_members.a3, '-') as a3,
            group_members.notes,
            group_members.date_add_time,
            GROUP_CONCAT(tags.label) as all_tags,
            users.name as approvedBy,
            if(group_members.agreed_group_rules, 'Yes', 'No') as agreed_group_rules,
            group_members.lives_in"
        )
            ->whereIn('group_members.id', $this->memberIds)
            ->leftJoin('group_members_tags', 'group_members_tags.group_member_id', '=', 'group_members.id')
            ->leftJoin('tags', 'tags.id', '=', 'group_members_tags.tag_id')
            ->leftJoin('users', 'users.id', '=', 'group_members.user_id')
            ->leftJoin('group_members as invited', 'group_members.invited_by_member_id', '=', 'invited.id')
            ->groupBy('group_members.id')
            ->get();

        $facebookGroup = FacebookGroups::with('responder')->find($this->groupId);

        $integration = $facebookGroup->responder->isNotEmpty() ?
            $facebookGroup->responder[0]->responder_type : 'Not Added';

        if (!(Storage::disk('local')->put(GroupMembers::CSV_FILES_PATH . $this->fileName, ''))) {
            throw new FileIsNotCreatedException();
        }

        /**
         * @todo use {@see \Illuminate\Contracts\Filesystem\Filesystem::append} to make this driver-agnostic
         */
        $file = fopen(
            Storage::disk('local')->path(GroupMembers::CSV_FILES_PATH . $this->fileName),
            'w'
        );

        $csvHeader = [
            'ID',
            'DATE ADDED',
            'AUTORESPONDER',
            'FULL NAME',
            'FIRST NAME',
            'LAST NAME',
            'EMAIL ADDRESS',
            'USER ID',
            'Q1 ANSWER',
            'Q2 ANSWER',
            'Q3 ANSWER',
            'NOTES',
            'TAGS',
            'APPROVED BY',
            'INVITED BY',
            'LIVES IN',
            'AGREED TO RULES',
        ];
        fputcsv($file, $csvHeader);

        foreach ($members as $member) {
            fputcsv(
                $file,
                [
                    'ID' => $member->id,
                    'DATE ADDED' => Carbon::createFromFormat('m-d-Y G:i:s', $member->date_add_time)
                        ->format('m-d-Y G:i'),
                    'AUTORESPONDER' => $integration,
                    'FULL NAME' => $member->full_name,
                    'FIRST NAME' => $member->f_name,
                    'LAST NAME' => $member->l_name,
                    'EMAIL ADDRESS' => $member->email,
                    'USER ID' => $member->fb_id,
                    'Q1 ANSWER' => $member->a1,
                    'Q2 ANSWER' => $member->a2,
                    'Q3 ANSWER' => $member->a3,
                    'NOTES' => $member->notes,
                    'TAGS' => $member->all_tags,
                    'APPROVED BY' => $member->approvedBy,
                    'INVITED BY' => $member->invitedBy,
                    'LIVES IN' => $member->lives_in,
                    'AGREED TO RULES' => $member->agreed_group_rules,
                ]
            );
        }

        fclose($file);
    }
}
