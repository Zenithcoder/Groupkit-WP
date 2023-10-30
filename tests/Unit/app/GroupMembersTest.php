<?php

namespace Tests\Unit\app;

use App\FacebookGroups;
use App\GroupMembers;
use App\Tag;
use App\User;
use Carbon\Exceptions\InvalidTimeZoneException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Passport\Passport;
use Tests\TestCase;
use Tests\TestHelper;
use ReflectionException;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class GroupMembersTest adds test coverage for {@see GroupMembers}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\GroupMembers
 */
class GroupMembersTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that getDateAddTimeAttribute returns date-time converted from UTC in user's timezone
     * if the user has the timezone
     *
     * @covers ::getDateAddTimeAttribute
     *
     * @dataProvider getDateAddTimeAttribute_withVariousTimezonesProvider
     *
     * @param string $timezone represents timezone of the {@see User}
     */
    public function getDateAddTimeAttribute_withVariousTimezones_returnsDateAddTimeInUserTimezone(
        string $timezone
    ) {
        $user = User::factory()->create(['timezone' => $timezone]);
        $this->actingAs($user);

        $this->assertNotEmpty($user->timezone);

        $dateAddTimeUTC = now();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMember = GroupMembers::factory()->create([
            'group_id'      => $facebookGroup->id,
            'user_id'       => $user->id,
            'date_add_time' => $dateAddTimeUTC,
        ]);

        $this->assertEquals(
            $dateAddTimeUTC->timezone($user->timezone)->format('m-d-Y G:i:s'),
            GroupMembers::find($groupMember->id)->date_add_time
        );

        $this->assertAuthenticated();
    }

    /**
     * Data provider for {@see getDateAddTimeAttribute_withVariousTimezones_returnsDateAddTimeInUserTimezone}
     *
     * @return array[] containing user's timezone
     */
    public function getDateAddTimeAttribute_withVariousTimezonesProvider(): array
    {
        return [
            ['timezone' => 'Africa/Accra'],
            ['timezone' => 'Brazil/West'],
            ['timezone' => 'Canada/Pacific'],
            ['timezone' => 'Europe/Vatican'],
            ['timezone' => 'Indian/Reunion'],
        ];
    }

    /**
     * @test
     * that getDateAddTimeAttribute returns formatted date-time if the user has no timezone
     *
     * @covers ::getDateAddTimeAttribute
     */
    public function getDateAddTimeAttribute_ifUserHasNoTimezone_returnsFormattedDateAddTime()
    {
        $user = User::factory()->create(['timezone' => null]);
        Passport::actingAs($user);

        $this->assertNull($user->timezone);

        $dateAddTimeUTC = now();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMember = GroupMembers::factory()->create([
            'group_id'      => $facebookGroup->id,
            'user_id'       => $user->id,
            'date_add_time' => $dateAddTimeUTC,
        ]);

        $this->assertEquals(
            $dateAddTimeUTC->format('m-d-Y G:i:s'),
            GroupMembers::find($groupMember->id)->date_add_time
        );

        $this->assertAuthenticated();
    }

    /**
     * @test
     * that getDateAddTimeAttribute returns date-time converted from UTC in user's timezone
     * if the user has the timezone
     *
     * @covers ::getDateAddTimeAttribute
     *
     * @dataProvider getDateAddTimeAttribute_withVariousInvalidTimezonesProvider
     *
     * @param string $timezone represents timezone of the {@see User}
     */
    public function getDateAddTimeAttribute_withVariousInvalidTimezones_returnsException(string $timezone)
    {
        $user = User::factory()->create(['timezone' => $timezone]);
        Passport::actingAs($user);

        $this->assertNotEmpty($user->timezone);

        $dateAddTimeUTC = now();
        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMember = GroupMembers::factory()->create([
            'group_id'      => $facebookGroup->id,
            'user_id'       => $user->id,
            'date_add_time' => $dateAddTimeUTC,
        ]);

        $this->assertAuthenticated();
        $this->expectException(InvalidTimeZoneException::class);
        $this->expectErrorMessage("Unknown or bad timezone ({$timezone})");

        GroupMembers::find($groupMember->id)->date_add_time;
    }

    /**
     * Data provider for {@see getDateAddTimeAttribute_withVariousInvalidTimezones_returnsException}
     *
     * @return array[] containing user's timezone
     */
    public function getDateAddTimeAttribute_withVariousInvalidTimezonesProvider(): array
    {
        return [
            ['timezone' => 'Africa/unknown'],
            ['timezone' => 'Africa/Accras'],
            ['timezone' => 'India/EastIndia'],
            ['timezone' => 'timezone'],
            ['timezone' => 'America / Agra'],
        ];
    }

    /**
     * @test
     * that tags always returns {@see BelongsToMany} instance
     *
     * @covers ::tags
     */
    public function tags_always_returnsBelongToMany()
    {
        $belongsToManyMock = $this->createMock(BelongsToMany::class);
        $currentMock = $this->createPartialMock(GroupMembers::class, ['belongsToMany']);
        $currentMock->expects(static::once())
            ->method('belongsToMany')
            ->with(Tag::class, 'group_members_tags', 'group_member_id', 'tag_id')
            ->willReturn($belongsToManyMock);

        $result = $currentMock->tags();

        $this->assertInstanceOf(BelongsToMany::class, $result);
        $this->assertEquals($belongsToManyMock, $result);
    }

    /**
     * @test
     * that phone_number field always return the extracted
     * phone number from answers field when saving only if
     * the phone number is a valid phone number and null otherwise.
     *
     * @covers ::booted
     *
     * @dataProvider booted_onSavingEvent_extractsPhoneNumberFromAnswerFieldsOnlyIfValidProvider
     *
     * @param string|null $a1 from answer field a1
     * @param string|null $a2 from answer field a2
     * @param string|null $a3 from answer field a3
     * @param string|null $expectedResult of the tested method call
     */
    public function booted_onSavingEvent_extractsPhoneNumberFromAnswerFieldsOnlyIfValid(
        ?string $a1,
        ?string $a2,
        ?string $a3,
        ?string $expectedResult
    ) {
        $user = User::factory()->create();

        $facebookGroup = FacebookGroups::factory()->create(['user_id' => $user->id]);
        $groupMember = GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'user_id' => $user->id,
            'a1' => $a1,
            'a2' => $a2,
            'a3' => $a3,
        ]);

        $this->assertEquals($expectedResult, $groupMember->phone_number);
    }

    /**
     * Data provider for {@see booted_onSavingEvent_extractsPhoneNumberFromAnswerFieldsOnlyIfValid}
     *
     * @return array[] containing a1, a2, a3, expected result
     */
    public function booted_onSavingEvent_extractsPhoneNumberFromAnswerFieldsOnlyIfValidProvider(): array
    {
        return [
            [
                'a1' => '+1 (555) 555-5555',
                'a2' => null,
                'a3' => null,
                'expectedResult' => '+1 (555) 555-5555',
            ],
            [
                'a1' => null,
                'a2' => '+1 (555) 555-5555',
                'a3' => null,
                'expectedResult' => '+1 (555) 555-5555',
            ],
            [
                'a1' => null,
                'a2' => null,
                'a3' => '+1 (555) 555-5555',
                'expectedResult' => '+1 (555) 555-5555',
            ],
            [
                'a1' => '+1 (555) 555-5551',
                'a2' => '+1 (555) 555-5552',
                'a3' => null,
                'expectedResult' => '+1 (555) 555-5551',
            ],
            [
                'a1' => '+1 (555) 555-5551',
                'a2' => null,
                'a3' => '+1 (555) 555-5552',
                'expectedResult' => '+1 (555) 555-5551',
            ],
            [
                'a1' => null,
                'a2' => '+1 (555) 555-5552',
                'a3' => '+1 (555) 555-5555',
                'expectedResult' => '+1 (555) 555-5552',
            ],
            [
                'a1' => '+1 (555) 555-5551',
                'a2' => '+1 (555) 555-5553',
                'a3' => '+1 (555) 555-5555',
                'expectedResult' => '+1 (555) 555-5551',
            ],
            [
                'a1' => null,
                'a2' => null,
                'a3' => null,
                'expectedResult' => null,
            ],
            [
                'a1' => 'Not a phone number',
                'a2' => 'Not a phone number',
                'a3' => null,
                'expectedResult' => null,
            ],
            [
                'a1' => 'Not a phone number',
                'a2' => 'Not a phone number',
                'a3' => 'Not a phone number',
                'expectedResult' => null,
            ],
        ];
    }

    /**
     * @test
     * that filterBy returns group members builder filtered by email if search param is provided
     *
     * @covers ::filterBy
     *
     * @throws ReflectionException if filterBy method is not defined
     */
    public function filterBy_withEmailSearchParameter_returnsQueryBuilder()
    {
        $this->addMySQLConcatFunction();
        $requestMock = new Request([
            'searchText' => 'folami@subira.com',
        ]);

        $facebookGroup = FacebookGroups::factory()->create();
        GroupMembers::factory(20)->create(['group_id' => $facebookGroup->id]);

        GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
            'email' => 'folami@subira.com',
        ]);

        GroupMembers::factory()->create([
            'group_id' => $facebookGroup->id,
            'date_add_time' => now(),
            'email' => 'follammi@subira.com',
        ]);

        $currentMock = $this->partialMock(GroupMembers::class);

        $response = TestHelper::callNonPublicFunction(
            $currentMock,
            'filterBy',
            [$requestMock]
        );

        $this->assertInstanceOf(Builder::class, $response);
        $this->assertStringContainsString(
            "(CONCAT(`f_name`, ' ', `l_name`) LIKE ? OR fb_id = ? OR email = ?)",
            $response->toSql()
        );
        $responseEmails = $response->get()->pluck('email');
        $this->assertContains(
            "folami@subira.com",
            $responseEmails
        );
        $this->assertNotContains(
            "follammi@subira.com",
            $responseEmails
        );
    }

    /**
     * @test
     * that approvedBy always returns {@see \Illuminate\Database\Eloquent\Relations\BelongsTo} instance
     *
     * @covers ::approvedBy
     */
    public function approvedBy_always_returnsBelongsTo()
    {
        $belongsToMock = $this->createMock(BelongsTo::class);
        $currentMock = $this->getMockBuilder(GroupMembers::class)
            ->disableOriginalConstructor()
            ->addMethods(['withTrashed'])
            ->onlyMethods(['belongsTo'])
            ->getMock();
        $currentMock->expects(static::once())
            ->method('belongsTo')
            ->with(User::class, 'user_id')
            ->willReturnSelf();
        $currentMock->expects(static::once())
            ->method('withTrashed')
            ->willReturn($belongsToMock);

        $result = $currentMock->approvedBy();

        $this->assertInstanceOf(BelongsTo::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }

    /**
     * @test
     * that invited_by always returns {@see \Illuminate\Database\Eloquent\Relations\HasOne} instance
     *
     * @covers ::invited_by
     */
    public function invited_by_always_returnsHasOne()
    {
        $hasOneMock = $this->createMock(HasOne::class);
        $currentMock = $this->createPartialMock(GroupMembers::class, ['hasOne']);
        $currentMock->expects(static::once())
            ->method('hasOne')
            ->with(GroupMembers::class, 'id', 'invited_by_member_id')
            ->willReturn($hasOneMock);

        $result = $currentMock->invited_by();

        $this->assertInstanceOf(HasOne::class, $result);
        $this->assertEquals($hasOneMock, $result);
    }

    /**
     * @test
     * that filterBy returns all group members and avoid filters if autoResponder filter equals to 'all'
     *
     * @covers ::filterBy
     */
    public function filterBy_withAutoResponderAll_avoidFilters()
    {
        $requestMock = new Request([
            'autoResponder' => 'all',
        ]);

        $facebookGroup = FacebookGroups::factory()->create();
        $createdGroupMembers = GroupMembers::factory(20)->create(['group_id' => $facebookGroup->id]);

        $response = GroupMembers::filterBy($requestMock);

        $this->assertInstanceOf(Builder::class, $response);
        $this->assertStringContainsString(
            'select * from "group_members" where "group_members"."deleted_at" is null',
            $response->toSql()
        );
        $this->assertEquals($createdGroupMembers->count(), $response->get()->count());
    }

    /**
     * @test
     * that filterBy returns all group members containing any of the errors
     * in {@see \App\GroupMembers::RESPONSE_STATUSES} when autoResponder value equals to `ERROR`
     *
     * @covers ::filterBy
     */
    public function filterBy_withAutoResponderError_returnsGroupMembersWithAnyOfTheErrors()
    {
        $requestMock = new Request([
            'autoResponder' => GroupMembers::RESPONSE_STATUS_ERROR,
        ]);
        $facebookGroup = FacebookGroups::factory()->create();
        $errorRespondStatuses = array_filter(
            GroupMembers::RESPONSE_STATUSES,
            function ($responseStatus) {
                return !in_array($responseStatus, GroupMembers::$integrationFilterStatuses);
            }
        );
        $expectedMembersInResponse = [];
        foreach ($errorRespondStatuses as $errorRespondStatus) {
            $expectedMembersInResponse[] = GroupMembers::factory()->create([
                'group_id' => $facebookGroup->id,
                'respond_status' => $errorRespondStatus,
            ]);
        }
        foreach (GroupMembers::$integrationFilterStatuses as $groupMembersResponseStatus) {
            GroupMembers::factory(5)->create([
                'group_id' => $facebookGroup->id,
                'respond_status' => $groupMembersResponseStatus,
            ]);
        }

        $response = GroupMembers::filterBy($requestMock);

        $responseContent = $response->get();
        $this->assertInstanceOf(Builder::class, $response);
        $this->assertEquals(count($expectedMembersInResponse), $responseContent->count());
        foreach (GroupMembers::$integrationFilterStatuses as $groupMembersResponseStatus) {
            $this->assertNull(
                $responseContent->where('respond_status', $groupMembersResponseStatus)->first()
            );
        }
    }
}
