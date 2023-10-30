<?php

namespace Tests\Unit\app;

use App\AutoResponder;
use App\FacebookGroups;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Class AutoResponderTest adds test coverage for {@see \App\AutoResponder}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\AutoResponder
 */
class AutoResponderTest extends TestCase
{
    /**
     * @test
     * that group always returns {@see BelongsTo} instance
     *
     * @covers ::group
     */
    public function group_always_returnsBelongsTo()
    {
        $belongsToMock = $this->createMock(BelongsTo::class);
        $currentMock = $this->createPartialMock(AutoResponder::class, ['belongsTo']);
        $currentMock->expects(static::once())
            ->method('belongsTo')
            ->with(FacebookGroups::class, 'group_id')
            ->willReturn($belongsToMock);

        $result = $currentMock->group();

        $this->assertInstanceOf(BelongsTo::class, $result);
        $this->assertEquals($belongsToMock, $result);
    }
}
