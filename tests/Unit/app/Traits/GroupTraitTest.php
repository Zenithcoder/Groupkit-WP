<?php

namespace Tests\Unit\app\Traits;

use App\Plan;
use App\Traits\GroupTrait;
use Tests\TestCase;

/**
 * Test class covers unit tests for GroupTrait class.
 *
 * @coversDefaultClass \App\Traits\GroupTrait
 */
class GroupTraitTest extends TestCase
{
    /**
     * @test
     *
     * that upgradePlanLink returns the URL to the upgrade
     * your plan page, with the text 'Please upgrade your plan.'.
     *
     * @covers \App\Traits\GroupTrait::upgradePlanLink
     *
     * @return void
     */
    public function upgradePlanLink_always_returnStringAsUrlForUpgradingPlan(): void
    {
        $groupTraitMock = $this->mock(GroupTrait::class);
        $expected = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s</a>',
            route('setting'),
            __(Plan::UPGRADE_PLAN_TEXT)
        );

        $response = $groupTraitMock->upgradePlanLink();

        $this->assertStringContainsString(Plan::UPGRADE_PLAN_TEXT, $response);
        $this->assertEquals($expected, $response);
    }
}
