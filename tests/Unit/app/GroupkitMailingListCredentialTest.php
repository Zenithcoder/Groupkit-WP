<?php

namespace Tests\Unit\app;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\GroupkitMailingListCredential;

/**
 * Class GroupkitMailingListCredentialTest adds test coverage for {@see GroupkitMailingListCredential}
 *
 * @package Tests\Unit\app
 * @coversDefaultClass \App\GroupkitMailingListCredential
 */
class GroupkitMailingListCredentialTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that setUpdatedAt doesn't set updated_at field on group kit mailing list creation
     *
     * @covers ::setUpdatedAt
     */
    public function setUpdatedAt_always_updatedAtFieldDoesNotExistAfterSave()
    {
        $groupkitMailingListCredential = GroupkitMailingListCredential::factory()->create();

        $groupkitMailingListCredentialObject = GroupkitMailingListCredential::find($groupkitMailingListCredential->id);

        $this->assertNull($groupkitMailingListCredentialObject->updated_at);
    }
}
