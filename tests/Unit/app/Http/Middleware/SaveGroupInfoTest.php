<?php

namespace Tests\Unit\app\Http\Middleware;

use App\Http\Middleware\SaveGroupInfo;
use App\Services\MarketingAutomation\IntegrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;
use Tests\TestCase;

/**
 * Class SaveGroupInfoTest adds test coverage for {@see SaveGroupInfo}
 *
 * @package Tests\Unit\app\Http\Middleware
 * @coversDefaultClass \App\Http\Middleware\SaveGroupInfo
 */
class SaveGroupInfoTest extends TestCase
{
    /**
     * @test
     * that handle returns request in the closure
     *
     * @covers ::handle
     */
    public function handle_always_returnsRequestInClosure()
    {
        $currentMock = $this->getMockBuilder(SaveGroupInfo::class)->onlyMethods([])->getMock();
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
        $saveGroupInfo = new SaveGroupInfo();
        $groupMembersId = [1, 4, 54, 98];
        $jsonResponse = new JsonResponse();
        $requestMock = new Request();
        $memberJson = json_encode(['member' => $groupMembersId]);
        $jsonResponse->setJson(json_encode(['data' => $memberJson]));

        $this->partialMock(IntegrationService::class)->shouldReceive('send')->with($groupMembersId);

        $saveGroupInfo->terminate($requestMock, $jsonResponse);
    }
}
