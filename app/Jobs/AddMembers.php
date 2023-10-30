<?php

namespace App\Jobs;

use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\IntegrationService;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldBeUnique;

/**
 * Stores the Facebook group members data passed during the approval process
 */
class AddMembers implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @var User The user that made the approval request
     */
    protected User $currentUser;

    /**
     * @var array The group member data sent by the user, grouped by Facebook group
     */
    protected array $requestGroups = [];

    /**
     * @var bool When true, this indicates that the job was dispatched via the approval process.  If false, it is
     *           implying that this is coming via an import routine.
     */
    protected bool $requestIsFromExtension = true;

    /**
     * @var Collection When Any pre-existing groups where members are to be added.
     */
    protected Collection $matchingFacebookGroups;

    /**
     * @var int The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3;

    /**
     * The unique ID of the job.
     *
     * @return int
     */
    public function uniqueId(): int
    {
        return $this->currentUser->id;
    }

    /**
     * Creates the job to be dispatched and marks class data members to be serialized
     *
     * @param User $currentUser The user that made the approval request
     * @param array $requestGroups The group member data sent by the user, grouped by Facebook group
     * @param bool $requestIsFromExtension When true, this indicates that the job was dispatched via the approval
     *                                     process.  If false, it is implying that this is coming via an import routine.
     * @param Collection $matchingFacebookGroups Any pre-existing groups where members are to be added.
     */
    public function __construct(
        User $currentUser,
        array $requestGroups,
        bool $requestIsFromExtension,
        Collection $matchingFacebookGroups
    ) {
        $this->currentUser = $currentUser;
        $this->requestGroups = $requestGroups;
        $this->requestIsFromExtension = $requestIsFromExtension;
        $this->matchingFacebookGroups = $matchingFacebookGroups;
    }

    /**
     * Execute the job, saving the group members to the database. We opt not to implement transactions because
     * of the upsert nature of this process.  After all members are added, they are sent to the integrated
     * services.
     */
    public function handle()
    {
        $groups = $this->requestGroups;
        $currentUser = $this->currentUser;
        $requestIsFromExtension = $this->requestIsFromExtension;
        $groupMemberIds = [];

        foreach ($groups as $groupData) {
            $facebookGroupData = $groupData['group'];
            $facebookGroupMembers = $groupData['user_details'];
            $facebookGroupsToAddMembers = [];

            # Update the Facebook group details
            if ($this->matchingFacebookGroups->isNotEmpty()) {
                foreach ($this->matchingFacebookGroups as $facebookGroup) {
                    $facebookGroup->fb_name = $facebookGroupData['groupname'];
                    $facebookGroup->fb_id = $facebookGroupData['groupid'];
                    @$facebookGroupData['img'] ? $facebookGroup->img = $facebookGroupData['img'] : "";
                    $facebookGroup->questionOne = $facebookGroupData['question_1'] ?? null;
                    $facebookGroup->questionTwo = $facebookGroupData['question_2'] ?? null;
                    $facebookGroup->questionThree = $facebookGroupData['question_3'] ?? null;
                    $facebookGroup->deleted_at = null;
                    $facebookGroup->save();
                    $facebookGroupsToAddMembers[] = $facebookGroup;
                }
            } else {
                # Create a new group or recover deleted group
                $facebookGroup = FacebookGroups::withTrashed()->where('fb_id', $facebookGroupData['groupid'])
                        ->where('user_id', $this->currentUser->id)
                        ->first()
                    ?? new FacebookGroups();
                $facebookGroup->fb_name = $facebookGroupData['groupname'];
                $facebookGroup->fb_id = $facebookGroupData['groupid'];
                @$facebookGroupData['img'] ? $facebookGroup->img = $facebookGroupData['img'] : "";
                $facebookGroup->questionOne = $facebookGroupData['question_1'] ?? null;
                $facebookGroup->questionTwo = $facebookGroupData['question_2'] ?? null;
                $facebookGroup->questionThree = $facebookGroupData['question_3'] ?? null;
                $facebookGroup->user_id = $currentUser->id;
                $facebookGroup->deleted_at = null;
                $facebookGroup->save();
                $facebookGroupsToAddMembers[] = $facebookGroup;
            }

            # Add or update group members
            foreach ($facebookGroupMembers as $facebookGroupMember) {
                #################################################################################
                # Normalize data
                #################################################################################
                $facebookGroupMember['fb_id'] = $facebookGroupMember['user_id'];

                $facebookGroupMember['email'] = (@$facebookGroupMember['email'] === '-')
                    ? null
                    : $facebookGroupMember['email'];

                $facebookGroupMember['respond_status'] =
                    ($facebookGroupMember['respond_status'] === 'N/A')
                        ? GroupMembers::RESPONSE_STATUSES['NOT_ADDED']
                        : $facebookGroupMember['respond_status'];

                /** Unset keys in the facebook data that conflict with our DB fields */
                unset($facebookGroupMember['date_add_time']);
                unset($facebookGroupMember['user_id']);

                $facebookGroupMember = array_filter($facebookGroupMember, function ($datum) {
                    return is_string($datum) ? strlen($datum) : true;
                });
                #################################################################################

                foreach ($facebookGroupsToAddMembers as $facebookGroup) {
                    ########################################
                    # Determine if we are updating or adding
                    ########################################
                    $groupMember = GroupMembers::withTrashed()
                        ->where('fb_id', $facebookGroupMember['fb_id'])
                        ->where('group_id', $facebookGroup->id)
                        ->first();

                    if (!$groupMember) {
                        $groupMember = new GroupMembers();
                        $groupMember->date_add_time = date('Y-m-d H:i:s');
                    }
                    ########################################

                    ########################################
                    # Save the group member
                    ########################################
                    $groupMember->user_id = $currentUser->id; # this will be used as added_by
                    $groupMember->group_id = $facebookGroup->id;

                    if ($groupMember->deleted_at) {
                        $groupMember->date_add_time = date('Y-m-d H:i:s');
                    }

                    $groupMember->deleted_at = null;

                    if ($requestIsFromExtension) {
                        $groupMember->is_approved = 1;
                    }

                    $groupMember->lives_in = $facebookGroupMember['lives_in'] ?? '';
                    $groupMember->agreed_group_rules = (int) ($facebookGroupMember['agreed_group_rules'] ?? false);

                    $groupMember->fill($facebookGroupMember);

                    if ($facebookGroup->questionOne) {
                         // if group question is populated we fill answers by the questions
                         // it's always populated firstly first group question
                        $groupMember->a1 = $this->getAnswerForGroupQuestion(
                            $facebookGroup->questionOne,
                            $facebookGroupMember
                        );
                        $groupMember->a2 = $this->getAnswerForGroupQuestion(
                            $facebookGroup->questionTwo,
                            $facebookGroupMember
                        );
                        $groupMember->a3 = $this->getAnswerForGroupQuestion(
                            $facebookGroup->questionThree,
                            $facebookGroupMember
                        );
                    }

                    $groupMember->save();

                    if (key_exists('invited_by_id', $facebookGroupMember)) {
                        if (!empty($facebookGroupMember['invited_by_id'])) {
                            $invitedByName = key_exists('invited_by_name', $facebookGroupMember)
                                ? $facebookGroupMember['invited_by_name']
                                : null;
                            $invitedById = $facebookGroupMember['invited_by_id'];
                            $spacePos = strpos($invitedByName, ' ');
                            $fname = substr($invitedByName, 0, $spacePos);
                            $lname = substr($invitedByName, $spacePos + 1, strlen($invitedByName) - $spacePos);

                            $invitedByMember = GroupMembers::withTrashed()->firstOrNew(
                                ['fb_id' => $invitedById, 'group_id' => $facebookGroup->id],
                                ['f_name' => $fname, 'l_name' => $lname, 'deleted_at' => null]
                            );

                            if (!$invitedByMember->exists) {
                                # to be able to invite you must be a member of the group, in the case we missed
                                # that particular member, it is created now
                                $invitedByMember->group_id = $facebookGroup->id;
                                $invitedByMember->user_id = $currentUser->id;
                                $invitedByMember->date_add_time = now();
                                $invitedByMember->save();
                            }

                            $groupMember->invited_by_member_id = $invitedByMember->id;
                            $groupMember->save();
                        }
                    }

                    array_push($groupMemberIds, $groupMember->id);
                }
            }
        }

        app(IntegrationService::class)->send($groupMemberIds, $requestIsFromExtension);
    }

    /**
     * Gets group member's answer to provided group question if the answer exists
     *
     * @param ?string $question by which we will search for Facebook group member answer
     * @param array $facebookGroupMember including group questions and user answers if they exist
     *
     * @return string empty if the question is not provided or
     * there is no answer in the member's answer to the question,
     * otherwise group member answer for provided group question
     */
    private function getAnswerForGroupQuestion(?string $question, array $facebookGroupMember): string
    {
        if (!$question || !in_array($question, array_values($facebookGroupMember))) {
            return '';
        }

        $questionKey = array_search($question, $facebookGroupMember);
        $answerKey = str_replace('q', 'a', $questionKey);

        return array_key_exists($answerKey, $facebookGroupMember)
            ? $facebookGroupMember[$answerKey]
            : ''
        ;
    }
}
