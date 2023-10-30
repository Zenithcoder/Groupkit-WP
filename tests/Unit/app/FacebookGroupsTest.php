<?php

namespace Tests\Unit\app;

use App\AutoResponder;
use App\FacebookGroups;
use App\GroupMembers;
use App\Tag;
use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\TestCase;

/**
 * Class FacebookGroupsTest adds test coverage for {@see FacebookGroups}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\FacebookGroups
 */
class FacebookGroupsTest extends TestCase
{
    /**
     * @test
     * that members always returns {@see HasMany} instance
     *
     * @covers ::members
     */
    public function members_always_returnsHasMany()
    {
        $hasManyMock = $this->createMock(HasMany::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
            ->disableOriginalConstructor()
            ->addMethods(['orderBy'])
            ->onlyMethods(['hasMany'])
            ->getMock();

        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(GroupMembers::class, 'group_id', 'id')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('orderBy')
            ->with('date_add_time', 'desc')
            ->willReturn($hasManyMock);

        $result = $currentMock->members();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that membersCount always returns {@see HasOne} instance
     *
     * @covers ::membersCount
     */
    public function membersCount_always_returnsHasOne()
    {
        $hasOneMock = $this->createMock(HasOne::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
            ->disableOriginalConstructor()
            ->addMethods(['selectRaw', 'groupBy'])
            ->onlyMethods(['hasOne'])
            ->getMock();

        $currentMock->expects(static::once())
            ->method('hasOne')
            ->with(GroupMembers::class, 'group_id')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('selectRaw')
            ->with('group_id, count(*) as members')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('groupBy')
            ->with('group_id')
            ->willReturn($hasOneMock);

        $result = $currentMock->membersCount();

        $this->assertInstanceOf(HasOne::class, $result);
        $this->assertEquals($hasOneMock, $result);
    }

    /**
     * @test
     * that responder always returns {@see HasMany} instance
     *
     * @covers ::responder
     */
    public function responder_always_returnsHasMany()
    {
        $hasManyMock = $this->createMock(HasMany::class);
        $currentMock = $this->createPartialMock(FacebookGroups::class, ['hasMany']);
        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(AutoResponder::class)
            ->willReturn($hasManyMock);

        $result = $currentMock->responder();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that googleSheetIntegration always returns {@see HasMany} instance
     *
     * @covers ::googleSheetIntegration
     */
    public function googleSheetIntegration_always_returnsHasMany()
    {
        $hasManyMock = $this->createMock(HasMany::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
            ->disableOriginalConstructor()
            ->addMethods(['whereNull', 'where'])
            ->onlyMethods(['hasMany'])
            ->getMock();

        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(AutoResponder::class, 'group_id', 'id')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('whereNull')
            ->with('deleted_at')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('where')
            ->with('responder_type', 'GoogleSheet')
            ->willReturn($hasManyMock);

        $result = $currentMock->googleSheetIntegration();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that convertKitIntegration always returns {@see HasMany} instance
     *
     * @covers ::convertKitIntegration
     */
    public function convertKitIntegration_always_returnsHasMany()
    {
        $hasManyMock = $this->createMock(HasMany::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
            ->disableOriginalConstructor()
            ->addMethods(['whereNull', 'where'])
            ->onlyMethods(['hasMany'])
            ->getMock();

        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(AutoResponder::class, 'group_id', 'id')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('whereNull')
            ->with('deleted_at')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('where')
            ->with('responder_type', 'ConvertKit')
            ->willReturn($hasManyMock);

        $result = $currentMock->convertKitIntegration();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that tags always returns {@see HasMany} instance
     *
     * @covers ::tags
     */
    public function tags_always_returnsHasManyTags()
    {
        $hasManyMock = $this->createMock(HasMany::class);
        $currentMock = $this->createPartialMock(FacebookGroups::class, ['hasMany']);
        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(Tag::class)
            ->willReturn($hasManyMock);

        $result = $currentMock->tags();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that recommendedTags always returns {@see HasMany} instance
     *
     * @covers ::recommendedTags
     */
    public function recommendedTags_always_returnsHasMany()
    {
        $hasManyMock = $this->createMock(HasMany::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
            ->disableOriginalConstructor()
            ->addMethods(['where'])
            ->onlyMethods(['hasMany'])
            ->getMock();

        $currentMock->expects(static::once())
            ->method('hasMany')
            ->with(Tag::class, 'group_id', 'id')
            ->willReturnSelf();

        $currentMock->expects(static::once())
            ->method('where')
            ->with('is_recommended', true)
            ->willReturn($hasManyMock);

        $result = $currentMock->recommendedTags();

        $this->assertInstanceOf(HasMany::class, $result);
        $this->assertEquals($hasManyMock, $result);
    }

    /**
     * @test
     * that owner always returns {@see BelongsTo} instance
     *
     * @covers ::owner
     */
    public function owner_always_returnsBelongsTo()
    {
        $belongsToMock = $this->createMock(BelongsTo::class);

        $currentMock = $this->getMockBuilder(FacebookGroups::class)
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

        $result = $currentMock->owner();

        $this->assertInstanceOf(BelongsTo::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }
}
