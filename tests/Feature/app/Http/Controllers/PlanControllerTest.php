<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Services\TapfiliateService;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

/**
 * Class PlanControllerTest
 *
 * This is a test case for the PlanController and it covers all the methods of the PlanController
 *
 * @package Tests\Feature\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\PlanController
 */
class PlanControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->assertGuest();
    }

    /**
     * @test
     * that index returns the plans index view
     *
     * @covers ::index
     */
    public function index_always_returnsPlansView()
    {
        $user = User::factory()->create(['stripe_id' => null]);
        $this->actingAs($user);

        $response = $this->get(route('plans.index'));

        $response->assertViewIs('plans.index');
        $this->assertAuthenticated();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @test
     * the CheckOut page is returned when StripePlanID is passed
     *
     * @covers ::show
     *
     * @dataProvider show_withVariousPlanIdsProvider
     *
     * @param string $stripePlanId from the stripe plans
     * @param int $expectedCode for the response
     */
    public function show_withVariousPlanIds_returnsResponse(string $stripePlanId, int $expectedCode)
    {
        $this->actingAsUser();

        $response = $this->get(route('plans.show', base64_encode($stripePlanId)));

        $this->assertAuthenticated();
        $this->assertEquals($expectedCode, $response->getStatusCode());
    }

    /**
     * Data provider for {@see show_withVariousPlanIds_returnsResponse}
     *
     * @return array[] containing stripe plan id and expected code for the tested method call
     */
    public function show_withVariousPlanIdsProvider()
    {
        return [
            'Correct PlanId' => [
                'stripe_plan_id' => 'plan_H98gMql8UbiAgb', 'expectedCode' => Response::HTTP_OK,
            ],
            'Incorrect PlanId' => [
                'stripe_plan_id' => '12221221', 'expectedCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'Incorrect PlanId Passed Blank' => [
                'stripe_plan_id' => '', 'expectedCode' => Response::HTTP_NOT_FOUND,
            ],
        ];
    }

    /**
     * @test
     * that validateEmail returns validation message if the requested email is not valid
     *
     * @covers ::validateEmail
     *
     * @dataProvider validateEmail_withVariousInvalidEmailsProvider
     *
     * @param string $requestedEmail represents provided email for the request
     * @param array $inputEmails for import into database
     * @param array $expectedResult of the tested method call
     */
    public function validateEmail_withVariousInvalidEmails_returnsVariousValidationMessage(
        string $requestedEmail,
        array $inputEmails,
        array $expectedResult
    ) {
        $this->actingAsUser();
        foreach ($inputEmails as $inputEmail) {
            User::factory()->create(['email' => $inputEmail]);
        }

        $response = $this->post(route('validateEmail', ['email' => $requestedEmail]));

        $response->assertStatus(Response::HTTP_BAD_REQUEST);
        $response->assertJsonFragment([
            'message' => $expectedResult['responseContent']['message'],
            'data' => $expectedResult['responseContent']['data'],
        ]);
    }

    /**
     * Data provider for {@see validateEmail_withVariousInvalidEmails_returnsVariousValidationMessage}
     *
     * @return array[] containing requested email, input emails and expected result of the tested method
     */
    public function validateEmail_withVariousInvalidEmailsProvider(): array
    {
        return [
            'Email Is Required' => [
                'requestedEmail' => '',
                'inputEmails' => [],
                'expectedResult' => [
                    'responseContent' => [
                        'message' => 'The email field is required.',
                        'data' => [],
                    ],
                ],
            ],
            'Email String Is Too Long' => [
                'requestedEmail' => Str::random(99) . '@gmail.com',
                'inputEmails' => [],
                'expectedResult' => [
                    'responseContent' => [
                        'message' => 'The email may not be greater than 100 characters.',
                        'data' => [],
                    ],
                ],
            ],
            'Email Is Not Valid' => [
                'requestedEmail' => 'jane.doe-gmailcom',
                'inputEmails' => [],
                'expectedResult' => [
                    'responseContent' => [
                        'message' => 'The email must be a valid email address.',
                        'data' => [],
                    ],
                ],
            ],
            'Email Contains Illegal Characters' => [
                'requestedEmail' => "\"(),:;<>@[\]@'-/.`{",
                'inputEmails' => [],
                'expectedResult' => [
                    'responseContent' => [
                        'message' => 'The email must be a valid email address.',
                        'data' => [],
                    ],
                ],
            ],
            'Email Is Taken' => [
                'requestedEmail' => 'jane.doe@gmail.com',
                'inputEmails' => [
                    'jane.doe@gmail.com',
                    'johny.doe@gmail.com',
                ],
                'expectedResult' => [
                    'responseContent' => [
                        'message' => 'The email has already been taken.',
                        'data' => [],
                    ],
                ],
            ],
        ];
    }

    /**
     * @test
     * that validateEmail returns success if the requested email is valid and available
     *
     * @covers ::validateEmail
     *
     * @dataProvider validateEmail_withVariousValidAndAvailableEmailsProvider
     *
     * @param string $requestedEmail represents provided email for the request
     */
    public function validateEmail_withVariousValidAndAvailableEmails_returnsSuccessResponse(
        string $requestedEmail
    ) {
        $this->actingAsUser();

        $inputEmails = [
            'sam.doe@gmail.com',
            'johny.smith@outlook.com',
        ];
        foreach ($inputEmails as $inputEmail) {
            User::factory()->create(['email' => $inputEmail]);
        }

        $response = $this->post(route('validateEmail', ['email' => $requestedEmail]));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonFragment([
            'success' => true,
        ]);
    }

    /**
     * Data provider for {@see validateEmail_withVariousValidAndAvailableEmails_returnsSuccessResponse}
     *
     * @return array[] containing requested email
     */
    public function validateEmail_withVariousValidAndAvailableEmailsProvider(): array
    {
        return [
            'Email Address With Uppercase And Lowercase Latin Letters' => [
                'requestedEmail' => 'UPPERCASELowercase@LatinLETTERS.coM',
            ],
            'Email Address With Digits' => [
                'requestedEmail' => '7403261958@7403.261958',
            ],
            'Email Address With Special Characters' => [
                'requestedEmail' => "!#$%&'*+-/=?^_`{|}~@!#$%&.*+=?^_|}~",
            ],
            'Email Address With Spaces And Illegal Special Characters' => [
                'requestedEmail' => '"() ,:;\"<>@[\]"@some.domain',
            ],
            'Email Address With 2nd level TLDs' => [
                'requestedEmail' => 'australian.address@example.com.au',
            ],
            'Simple Email Address' => [
                'requestedEmail' => 'simple@example.com',
            ],
            'Very Common Email Address' => [
                'requestedEmail' => 'very.common@example.com',
            ],
            'Email Address With + Symbol' => [
                'requestedEmail' => 'disposable.style.email.with+symbol@example.com',
            ],
            'Email Address With Hyphen' => [
                'requestedEmail' => 'other.email-with-hyphen@example.com',
            ],
            'Email Address With Fully Qualified Domain' => [
                'requestedEmail' => 'fully-qualified-domain@example.com',
            ],
            'Email Address With One-letter Local-part' => [
                'requestedEmail' => 'x@example.com',
            ],
            'Email Address With A Specific Domain' => [
                'requestedEmail' => 'example-indeed@strange-example.com',
            ],
            'Email Address With Slashes' => [
                'requestedEmail' => 'test/test@test.com',
            ],
            'Email Address With Top-level Domains' => [
                'requestedEmail' => 'example@s.example',
            ],
            'Email Address With Space Between The Quotes' => [
                'requestedEmail' => '" "@example.org',
            ],
            'Email Address With A Quoted Double Dot' => [
                'requestedEmail' => '"john..doe"@example.org',
            ],
            'Email Address With Bangified Host Route' => [
                'requestedEmail' => 'mailhost!username@example.org',
            ],
            'Email Address With % Escaped Mail Route' => [
                'requestedEmail' => 'user%example.com@example.org',
            ],
            'Email Address With Non-alphanumeric Character ' => [
                'requestedEmail' => 'user-@example.org',
            ],
            'Email Address With Local Domain Name' => [
                'requestedEmail' => 'admin@mailserver1',
            ],
            'Email Address With IP Address Instead Of Domains' => [
                'requestedEmail' => 'postmaster@[123.123.123.123]',
            ],
            'Email Address With IPv6 Address' => [
                'requestedEmail' => 'postmaster@[IPv6:2001:0db8:85a3:0000:0000:8a2e:0370:7334]',
            ],
            'guaranteed.network Email Address' => [
                'requestedEmail' => 'leon@guaranteed.network',
            ],
            'guaranteed.software Email Address' => [
                'requestedEmail' => 'developers@guaranteed.software',
            ],
            'gmail.com Email Address' => [
                'requestedEmail' => 'john.vega@gmail.com',
            ],
            'yahoo.com Email Address' => [
                'requestedEmail' => 'barbara.sparks@yahoo.com',
            ],
            'outlook.com Email Address' => [
                'requestedEmail' => 'james.hayden@outlook.com',
            ],
        ];
    }

    /**
     * @test
     * that webinar displays webinar page
     *
     * @covers ::webinar
     */
    public function webinar_withoutTapfiliateParameter_redirectsToPlansPage()
    {
        $this->actingAsUser();

        $response = $this->get(route('webinar'));

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertViewIs('webinar');
    }

    /**
     * @test
     * that webinar:
     * 1. displays webinar page
     * 2. stores tapfiliate parameter in cookie if is present in the request
     *    as {@see TapfiliateService::TAPFILIATE_REQUEST_PARAMETER}
     *
     * @covers ::webinar
     */
    public function webinar_withTapfiliateParameter_storesTapfiliateParameterInCookie()
    {
        $this->actingAsUser();
        $tapfiliateValue = 'customers_ref_code';

        $response = $this->get(
            route('webinar', [TapfiliateService::TAPFILIATE_REQUEST_PARAMETER => $tapfiliateValue])
        );

        $this->assertAuthenticated();
        $response->assertOk();
        $response->assertViewIs('webinar');
        $response->assertCookie('tapfiliate_id', $tapfiliateValue);
    }
}
