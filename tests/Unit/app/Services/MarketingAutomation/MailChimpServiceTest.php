<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\User;
use Tests\TestCase;
use App\GroupMembers;
use App\AutoResponder;
use App\FacebookGroups;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use App\Services\MarketingAutomation\MailChimpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Class MailChimpServiceTest adds test coverage for MailChimpService
 *
 * @package Tests\Unit\app\Services\MailChimpServiceTest
 * @coversDefaultClass \App\Services\MarketingAutomation\MailChimpService
 */
class MailChimpServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * User logged in the session
     *
     * @var User
     */
    private User $user;

    /**
     * Autoresponder extra parameters, stored in responder_json
     *
     * @var object
     */
    private object $extraParameters;

    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    private const SERVICE_NAME = 'MailChimp';

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsUser();

        $this->extraParameters = (object) [
            'api_key' => 'test_api_key-us11',
            'host_name' => 'api.mailchimp.com',
            'activeList' => (object) [
                'value' => 'active_list',
            ],
        ];
    }

    /**
     * SetUp method for creating group member with associated Facebook Group and auto responder
     *
     * @return GroupMembers that will be used in the test case
     */
    private function groupMemberSetUp(): GroupMembers
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $groupMember = GroupMembers::factory()->create(
            [
                'user_id' => $this->user->id,
                'group_id' => $facebookGroup->id,
                'deleted_at' => null,
            ]
        );

        AutoResponder::factory()->create(
            [
                'responder_type' => self::SERVICE_NAME,
                'user_id' => $this->user->id,
                'group_id' => $facebookGroup->id,
                'responder_json' => json_encode($this->extraParameters),
            ]
        );

        return $groupMember;
    }

    /**
     * @test
     * that covers the case when a MailChimpService tries to send a member
     * to the mailing list but the response from the MailChimpService endpoint is
     * 'Invalid resource' with HTTP status code 400.
     *
     * @covers ::subscribe
     */
    public function subscribe_withInvalidResourceResponseTitle_throwsExceptionAndUpdateGroupMember(): void
    {
        $groupMember = $this->groupMemberSetUp();

        $apiKey = 'test_api_key';
        $testUrl = "https://us11.api.mailchimp.com/3.0/lists/active_list/members";

        Http::fake([
            $testUrl => Http::response(
                ['title' => GroupMembers::RESPONSE_STATUSES['INVALID_RESOURCE']],
                Response::HTTP_BAD_REQUEST
            ),
        ]);

        $response = Http::withBasicAuth('', $apiKey)
            ->post(
                $testUrl,
                [
                    'merge_fields' => [
                        'FNAME' => $groupMember->f_name,
                        'LNAME' => $groupMember->l_name,
                    ],
                    'email_address' => $groupMember->email,
                    'status' => MailChimpService::STATUSES['SUBSCRIBED'],
                ],
            );

        app(MailChimpService::class)->subscribe($groupMember);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->status());
        $this->assertDatabaseHas('group_members', [
            'id' => $groupMember->id,
            'respond_status' => ucfirst(GroupMembers::RESPONSE_STATUSES['INVALID_RESOURCE']),
        ]);
    }
}
