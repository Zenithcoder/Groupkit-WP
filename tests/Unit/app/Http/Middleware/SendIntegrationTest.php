<?php

namespace Tests\Unit\app\Http\Middleware;

use App\Http\Middleware\SendIntegration;
use App\Services\MarketingAutomation\IntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;
use Tests\TestCase;

/**
 * Class SendIntegrationTest adds test coverage for {@see SendIntegration}
 *
 * @package Tests\Unit\app\Http\Middleware
 * @coversDefaultClass \App\Http\Middleware\SendIntegration
 */
class SendIntegrationTest extends TestCase
{
    /**
     * @test
     * that handle returns request in the closure
     *
     * @covers ::handle
     */
    public function handle_always_returnsRequestInClosure()
    {
        $currentMock = $this->getMockBuilder(SendIntegration::class)->onlyMethods([])->getMock();
        $requestMock = $this->createMock(Request::class);
        $closureMock = $this->getMockBuilder(stdClass::class)->addMethods(['next'])->getMock();
        $closureMock->expects($this->once())
            ->method('next')
            ->with($requestMock)
            ->willReturn(new Response($requestMock));

        $currentMock->handle($requestMock, function ($request) use ($closureMock) {
            return $closureMock->next($request);
        });
    }

    /**
     * @test
     * that terminate sends group members id to the IntegrationService
     *
     * @covers ::terminate
     */
    public function terminate_always_sendsGroupMembersToIntegrationService()
    {
        $sendIntegration = new SendIntegration();
        $groupMembersId = [1, 4, 54, 98];
        $requestMock = new Request();
        $requestMock->merge(['group_members_id' => $groupMembersId]);

        $this->partialMock(IntegrationService::class)->shouldReceive('send')->with($groupMembersId);

        $sendIntegration->terminate($requestMock);
    }
}
