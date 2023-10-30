<?php

namespace Tests\Unit\app\Mail;

use App\Mail\UpdateEmail;
use App\UpdateEmailAddress;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Class UpdateEmailTest adds test coverage for {@see UpdateEmail}
 *
 * @package Tests\Unit\app\Mail
 * @coversDefaultClass \App\Mail\UpdateEmail
 */
class UpdateEmailTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * @test
     * that constructor sets the internal properties
     *
     * @covers ::__construct
     */
    public function __construct_always_setsInternalProperties()
    {
        $emailToUpdate = 'john.doe@gmail.com';
        $userName = 'John Doe';

        $result = new UpdateEmail($emailToUpdate, $userName);

        $this->assertInstanceOf(UpdateEmail::class, $result);
        $this->assertEquals($emailToUpdate, $result->customerName);
        $this->assertEquals($userName, $result->activationCode);
    }

    /**
     * @test
     * Sent email notification to updated email address.
     *
     * @covers ::build
     */
    public function build_always_sentEmailNotificationToUpdateEmail()
    {
        $currentMock = $this->getMockBuilder(UpdateEmail::class)
            ->onlyMethods(['subject', 'markdown'])
            ->disableOriginalConstructor()
            ->getMock();
        $currentMock->expects(static::once())->method('subject')->with('Change user email address')->willReturnSelf();
        $currentMock->expects(static::once())->method('markdown')->with('emails.update-email')->willReturnSelf();

        $result = $currentMock->build();

        $this->assertInstanceOf(UpdateEmail::class, $result);
    }
}
