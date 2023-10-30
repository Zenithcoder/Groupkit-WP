<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\IntegrationService;
use App\User;
use App\Exceptions\Integrations\NoMembersToSendException;
use App\Exceptions\Integrations\GroupLimitExceededException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionException;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class IntegrationServiceTest adds test coverage for {@see IntegrationService}
 *
 * @package Tests\Unit\app\Services\MarketingAutomation
 * @coversDefaultClass \App\Services\MarketingAutomation\IntegrationService
 */
class IntegrationServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @var User represents a user that is logged in into session
     */
    protected User $user;

    /**
     * Services which this test class will avoid because of different internal implementation
     *
     * @var array
     */
    private const EXCLUDED_SERVICES = [
        'ActiveCampaign',
    ];

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->actingAsUser();
    }

    /**
     * Sets property default value.
     *
     * @throws ReflectionException if apiInfo property doesn't exist
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        TestHelper::setNonPublicProperty(AbstractMarketingService::class, 'apiInfo', []);
    }

    /**
     * @test
     * that send sets respond status as empty string for each request group member
     * when facebook group hasn't any email marketing service
     *
     * @covers ::send
     */
    public function send_withoutEmailMarketingService_setsRespondStatusAsEmptyString()
    {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $groupMembers = GroupMembers::factory(5)->create([
            'user_id'        => $this->user->id,
            'group_id'       => $facebookGroup->id,
            'respond_status' => GroupMembers::RESPONSE_STATUSES['ADDED'],
            'deleted_at'     => null,
        ]);

        app(IntegrationService::class)->send($groupMembers->pluck('id')->toArray());

        foreach ($groupMembers as $groupMember) {
            $this->assertDatabaseHas('group_members', [
                'id'             => $groupMember->id,
                'group_id'       => $facebookGroup->id,
                'user_id'        => $this->user->id,
                'deleted_at'     => null,
                'respond_status' => '',
            ]);
        }
    }

    /**
     * @test
     * that send throws @see NoMembersToSendException if provided ids can't be found in the database
     *
     * @covers ::send
     */
    public function send_withUnknownGroupMembersIds_throwsNoMembersToSendException()
    {
        $ids = [0, 1, 2];

        $this->expectException(NoMembersToSendException::class);

        app(IntegrationService::class)->send($ids);
    }

    /**
     * @test
     * that sends throws @see GroupLimitExceededException
     * if provided group members belong to more than one group
     *
     * @covers ::send
     */
    public function send_withMoreThanOneGroup_throwsGroupLimitExceededException()
    {
        $firstFacebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $secondFacebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);

        $groupMembersFromFirstGroup = GroupMembers::factory(5)->create([
            'user_id' => $this->user->id,
            'group_id' => $firstFacebookGroup->id,
            'respond_status' => '',
            'deleted_at' => null,
        ]);
        $firstGroupAutoResponder = array_key_first(AutoResponder::SERVICE_TYPES);
        AutoResponder::factory()->create([
            'responder_type' => $firstGroupAutoResponder,
            'user_id' => $this->user->id,
            'group_id' => $firstFacebookGroup->id,
        ]);

        $groupMembersFromSecondGroup = GroupMembers::factory(5)->create([
            'user_id' => $this->user->id,
            'group_id' => $secondFacebookGroup->id,
            'respond_status' => '',
            'deleted_at' => null,
        ]);
        $secondGroupAutoResponder = array_key_last(AutoResponder::SERVICE_TYPES);
        AutoResponder::factory()->create([
            'responder_type' => $secondGroupAutoResponder,
            'user_id' => $this->user->id,
            'group_id' => $secondFacebookGroup->id,
        ]);

        $allMembersIds = array_merge(
            $groupMembersFromFirstGroup->pluck('id')->toArray(),
            $groupMembersFromSecondGroup->pluck('id')->toArray(),
        );

        $this->expectException(GroupLimitExceededException::class);

        app(IntegrationService::class)->send($allMembersIds);
    }

    /**
     * @test
     * that send sets added status {@see GroupMembers::RESPONSE_STATUSES} for provided group member
     *
     * @covers ::send
     *
     * @dataProvider send_withVariousMarketingServicesProvider
     *
     * @param string $serviceName that provided group member will be provided to
     */
    public function send_ifGroupMemberHasEmail_savesAddedResponseStatus(string $serviceName) {
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $this->user->id]);
        $groupMember = GroupMembers::factory()->create([
            'user_id'        => $this->user->id,
            'group_id'       => $facebookGroup->id,
            'respond_status' => null,
            'deleted_at'     => null,
        ]);
        AutoResponder::factory()->create([
            'responder_type' => $serviceName,
            'user_id'        => $this->user->id,
            'group_id'       => $facebookGroup->id,
        ]);
        $groupMemberMock = $this->mock(GroupMembers::class);
        $groupMemberMock->shouldReceive('with')
            ->with(['approvedBy', 'invited_by', 'tags'])
            ->andReturnSelf();
        $groupMemberMock->shouldReceive('whereIn')
            ->with('id', [$groupMember->id])
            ->andReturn($groupMember);

        $serviceClass = AutoResponder::SERVICE_TYPES[$serviceName];

        $this->getMockBuilder($serviceClass)
            ->disableOriginalConstructor()
            ->onlyMethods(['subscribeAll'])
            ->getMock()
            ->method('subscribeAll')
            ->with(
                app(GroupMembers::class)
                    ->with(['approvedBy', 'invited_by', 'tags'])
                    ->whereIn('id', [$groupMember->id])
                    ->get()
            );

        app(IntegrationService::class)->send($groupMember->pluck('id')->toArray());

        $this->assertDatabaseHas('group_members', [
            'id'             => $groupMember->id,
            'group_id'       => $facebookGroup->id,
            'user_id'        => $this->user->id,
            'deleted_at'     => null,
        ]);
    }

    /**
     * Data provider for
     * {@see send_ifGroupMemberHasEmail_savesAddedResponseStatus}
     * {@see send_ifGroupMemberHasNotEmail_savesProperRespondStatus}
     *
     * @return array[] containing service name
     */
    public function send_withVariousMarketingServicesProvider()
    {
        $serviceNames = array_map(function ($serviceName) {
            return ['serviceName' => $serviceName];
        }, array_keys(AutoResponder::SERVICE_TYPES));

        return array_filter($serviceNames, function ($service) {
            return !in_array($service['serviceName'], static::EXCLUDED_SERVICES);
        });
    }
}
