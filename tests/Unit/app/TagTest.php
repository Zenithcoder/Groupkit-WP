<?php

namespace Tests\Unit\app;

use App\GroupMembers;
use App\Tag;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\TestCase;

/**
 * Class TagTest adds test coverage for {@see Tag}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\Tag
 */
class TagTest extends TestCase
{
    /**
     * @test
     * that members always returns {@see BelongsToMany} instance
     *
     * @covers ::members
     */
    public function members_always_returnsBelongToMany()
    {
        $belongsToManyMock = $this->createMock(BelongsToMany::class);
        $currentMock = $this->createPartialMock(Tag::class, ['belongsToMany']);
        $currentMock->expects(static::once())
            ->method('belongsToMany')
            ->with(GroupMembers::class, 'group_members_tags', 'tag_id', 'group_member_id')
            ->willReturn($belongsToManyMock);

        $result = $currentMock->members();

        $this->assertInstanceOf(BelongsToMany::class, $result);
        $this->assertEquals($belongsToManyMock, $result);
    }
}
