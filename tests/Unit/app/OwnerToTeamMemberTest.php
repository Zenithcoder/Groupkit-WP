<?php

namespace Tests\Unit\app;

use App\OwnerToTeamMember;
use App\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Class OwnerToTeamMemberTest adds test coverage for {@see OwnerToTeamMember}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\OwnerToTeamMember
 */
class OwnerToTeamMemberTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that owner returns {@see BelongsTo} relationship for {@see User}
     *
     * @covers ::owner
     */
    public function owner_always_returnsBelongsTo()
    {
        $belongsToMock = $this->createMock(BelongsTo::class);
        $currentMock = $this->createPartialMock(OwnerToTeamMember::class, ['belongsTo']);
        $currentMock->expects(static::once())
            ->method('belongsTo')
            ->with(User::class, 'owner_id')
            ->willReturn($belongsToMock);

        $result = $currentMock->owner();

        $this->assertInstanceOf(BelongsTo::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }
}
