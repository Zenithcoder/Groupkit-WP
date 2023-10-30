<?php

namespace Tests\Unit\app\Http\Controllers;

use App\Exceptions\Stripe\StripeDataIsMissingException;
use App\Http\Controllers\WebhookController;
use App\Mail\StripeSyncMail;
use App\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Stripe\Customer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

/**
 * Class WebhookControllerTest adds test coverage for {@see WebhookController}
 *
 * @package Tests\Unit\app\Http\Controllers
 * @coversDefaultClass \App\Http\Controllers\WebhookController
 */
class WebhookControllerTest extends TestCase
{
    use DatabaseMigrations;
    use DatabaseTransactions;

    /**
     * Test data for stubbing Stripe customer API response
     *
     * @var array
     */
    private const STRIPE_CUSTOMER = [
        'id' => 'cus_3123123432432',
        'name' => 'John Doe',
        'email' => 'john.doe@gmail.com',
        'metadata' => [
            'name' => 'John Doe',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ],
    ];

    /**
     * @test
     * That __construct throws an exception when
     * 1. Stripe webhook secret has set in the .env file
     * 2. The incoming request has not come in from Stripe
     *
     * @covers ::__construct
     *
     * @return void
     */
    public function __construct_whenRequestDoesntCameInFromDefaultStripeAccount_throwsAccessDeniedHttpException()
    {
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Unable to extract timestamp and signatures from header');

        new WebhookController();
    }

    /**
     * @test
     * Handles the subscription payment succeeded {@see WebhookController::PAYMENT_INTENT_SUCCEEDED_EVENT} event
     *
     * @covers ::index
     *
     * @dataProvider index_withVariousStripeIdProvider
     *
     * @param array $requestParam of the user request data
     * @param int $expectedCode of the tested method call
     * @param string $expectedMessage of the tested method call
     */
    public function index_withVariousStripeId_returnsResponse(
        array $requestParam,
        int $expectedCode,
        string $expectedMessage
    ) {
        $payload = json_decode($this->webhookPayload(), true);

        User::factory()->create(
            [
                'name' => 'John Doe',
                'email' => 'john.doe@gmail.com',
                'password' => 'password',
                'stripe_id' => $requestParam['stripeId'],
                'ref_code' => $requestParam['ref_code'],
            ]
        );

        $mockObject = $this->partialMock(WebhookController::class);
        $request = new Request($requestParam['stripeId'] ? $payload : []);

        $actual = $mockObject->index($request);

        $this->assertEquals($expectedMessage, $actual->getContent());
        $this->assertEquals($expectedCode, $actual->status());
    }

    /**
     * Data provider for {@see index_withVariousStripeId_returnsResponse}
     *
     * @return array[] containing stripe id and expected code of the method call
     */
    public function index_withVariousStripeIdProvider(): array
    {
        $payload = json_decode($this->webhookPayload());

        return [
            'Correct Stripe Id' => [
                'requestParam' => [
                    'stripeId' => $payload->data->object->customer,
                    'ref_code' => 'test',
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Success',
            ],
            'No Tapfiliate Referral Code' => [
                'requestParam' => [
                    'stripeId' => $payload->data->object->customer,
                    'ref_code' => '',
                ],
                'expectedCode' => Response::HTTP_OK,
                'expectedMessage' => 'Success',
            ],
            'Incorrect Stripe Id' => [
                'requestParam' => [
                    'stripeId' => '',
                    'ref_code' => 'test',
                ],
                'expectedCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'expectedMessage' => 'Something went wrong',
            ],
        ];
    }

    /**
     * Stripe payment success data payload
     *
     * @return string for subscription payload
     */
    public function webhookPayload(): string
    {
        return
            '{
              "id": "evt_1IRBvHLRkwVQ3lNkZUZh3LvK",
              "object": "event",
              "api_version": "2020-03-02",
              "created": 1614844118,
              "data": {
                "object": {
                  "id": "pi_1IRBvFLRkwVQ3lNkV760H3lO",
                  "object": "payment_intent",
                  "amount": 3400,
                  "amount_capturable": 0,
                  "amount_received": 3400,
                  "application": null,
                  "application_fee_amount": null,
                  "canceled_at": null,
                  "cancellation_reason": null,
                  "capture_method": "automatic",
                  "charges": {
                    "object": "list",
                    "data": [
                      {
                        "id": "ch_1IRBvFLRkwVQ3lNkqe35h5RY",
                        "object": "charge",
                        "amount": 3400,
                        "amount_captured": 3400,
                        "amount_refunded": 0,
                        "application": null,
                        "application_fee": null,
                        "application_fee_amount": null,
                        "balance_transaction": "txn_1IRBvGLRkwVQ3lNkORX06s1j",
                        "billing_details": {
                          "address": {
                            "city": null,
                            "country": null,
                            "line1": null,
                            "line2": null,
                            "postal_code": null,
                            "state": null
                          },
                          "email": null,
                          "name": "Pre A",
                          "phone": null
                        },
                        "calculated_statement_descriptor": "GROUPKIT",
                        "captured": true,
                        "created": 1614844117,
                        "currency": "usd",
                        "customer": "cus_HdiEGmQfEbCnjx",
                        "description": "Subscription update",
                        "destination": null,
                        "dispute": null,
                        "disputed": false,
                        "failure_code": null,
                        "failure_message": null,
                        "fraud_details": {
                        },
                        "invoice": "in_1IRAvvLRkwVQ3lNkgtb6auhZ",
                        "livemode": false,
                        "metadata": {
                        },
                        "on_behalf_of": null,
                        "order": null,
                        "outcome": {
                          "network_status": "approved_by_network",
                          "reason": null,
                          "risk_level": "normal",
                          "risk_score": 19,
                          "seller_message": "Payment complete.",
                          "type": "authorized"
                        },
                        "paid": true,
                        "payment_intent": "pi_1IRBvFLRkwVQ3lNkV760H3lO",
                        "payment_method": "pm_1HSbvLLRkwVQ3lNkkl93SZ3a",
                        "payment_method_details": {
                          "card": {
                            "brand": "visa",
                            "checks": {
                              "address_line1_check": null,
                              "address_postal_code_check": null,
                              "cvc_check": null
                            },
                            "country": "US",
                            "exp_month": 2,
                            "exp_year": 2022,
                            "fingerprint": "gJ9PdRh0Hk6KVCKc",
                            "funding": "credit",
                            "installments": null,
                            "last4": "4242",
                            "network": "visa",
                            "three_d_secure": null,
                            "wallet": null
                          },
                          "type": "card"
                        },
                        "receipt_email": "pradeep@groupkit.com",
                        "receipt_number": null,
                        "receipt_url":null,
                        "refunded": false,
                        "refunds": {
                          "object": "list",
                          "data": [
                          ],
                          "has_more": false,
                          "total_count": 0,
                          "url": "/v1/charges/ch_1IRBvFLRkwVQ3lNkqe35h5RY/refunds"
                        },
                        "review": null,
                        "shipping": null,
                        "source": null,
                        "source_transfer": null,
                        "statement_descriptor": null,
                        "statement_descriptor_suffix": null,
                        "status": "succeeded",
                        "transfer_data": null,
                        "transfer_group": null
                      }
                    ],
                    "has_more": false,
                    "total_count": 1,
                    "url": "/v1/charges?payment_intent=pi_1IRBvFLRkwVQ3lNkV760H3lO"
                  },
                  "client_secret": "pi_1IRBvFLRkwVQ3lNkV760H3lO_secret_zsFpefErMVeB62uGNr7Te2Ddl",
                  "confirmation_method": "automatic",
                  "created": 1614844117,
                  "currency": "usd",
                  "customer": "cus_HdiEGmQfEbCnjx",
                  "description": "Subscription update",
                  "invoice": "in_1IRAvvLRkwVQ3lNkgtb6auhZ",
                  "last_payment_error": null,
                  "livemode": false,
                  "metadata": {
                    "products": "GroupKit Pro"
                  },
                  "next_action": null,
                  "on_behalf_of": null,
                  "payment_method": "pm_1HSbvLLRkwVQ3lNkkl93SZ3a",
                  "payment_method_options": {
                    "card": {
                      "installments": null,
                      "network": null,
                      "request_three_d_secure": "automatic"
                    }
                  },
                  "payment_method_types": [
                    "card"
                  ],
                  "receipt_email": null,
                  "review": null,
                  "setup_future_usage": null,
                  "shipping": null,
                  "source": null,
                  "statement_descriptor": null,
                  "statement_descriptor_suffix": null,
                  "status": "succeeded",
                  "transfer_data": null,
                  "transfer_group": null
                }
              },
              "livemode": false,
              "pending_webhooks": 8,
              "request": {
                "id": null,
                "idempotency_key": "in_1IRAvvLRkwVQ3lNkgtb6auhZ-initial_attempt-c3568e7a88ddb6b8b"
              },
              "type": "' . WebhookController::PAYMENT_INTENT_SUCCEEDED_EVENT . '"
            }';
    }

    /**
     * @test
     * that createOrUpdateUser
     * 1. creates the provided customer in the database
     * 2. send the {@see StripeSyncMail} to the created customer email address
     * when customer from stripe response is not in the database
     *
     * @covers ::createOrUpdateUser
     */
    public function createOrUpdateUser_whenProvidedCustomerDoesNotExistInDatabase_createsCustomerInDatabase()
    {
        $currentMock = $this->partialMock(WebhookController::class);
        Mail::fake();
        $this->assertDatabaseMissing('users', [
            'stripe_id' => static::STRIPE_CUSTOMER['id'],
            'email' => static::STRIPE_CUSTOMER['email'],
            'name' => static::STRIPE_CUSTOMER['metadata']['name'],
        ]);

        $stubbedCustomer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->getMock();
        $stubbedCustomer->expects(static::exactly(4))
            ->method('__get')
            ->withConsecutive(['id'], ['email'], ['metadata'], ['metadata'])
            ->willReturnOnConsecutiveCalls(
                static::STRIPE_CUSTOMER['id'],
                static::STRIPE_CUSTOMER['email'],
                (object)static::STRIPE_CUSTOMER['metadata'],
                (object)static::STRIPE_CUSTOMER['metadata']
            );

        $this->mock(Customer::class)
            ->allows('retrieve')
            ->with(static::STRIPE_CUSTOMER['id'])
            ->andReturn($stubbedCustomer);

        $currentMock->createOrUpdateUser(static::STRIPE_CUSTOMER['id']);

        $this->assertDatabaseHas('users', [
            'stripe_id' => static::STRIPE_CUSTOMER['id'],
            'email' => static::STRIPE_CUSTOMER['email'],
            'name' => static::STRIPE_CUSTOMER['metadata']['name'],
        ]);
        Mail::assertSent(StripeSyncMail::class, function ($mail) {
            $mail->hasTo(static::STRIPE_CUSTOMER['email']);
            return $mail;
        });
    }

    /**
     * @test
     * that createOrUpdateUser throws {@see StripeDataIsMissingException} exception
     * when id or email are not present in Stripe API Customer response
     *
     * @covers ::createOrUpdateUser
     *
     * @dataProvider createOrUpdateUser_withoutStripeDataProvider
     *
     * @param string $id of the Stripe customer that will be requested from Stripe API
     * @param array $stripeCustomer stubbed response of the Stripe API, includes (id, name, email)
     */
    public function createOrUpdateUser_withoutStripeData_throwsStripeDataIsMissingException(
        string $id,
        array $stripeCustomer
    ) {
        $currentMock = $this->partialMock(WebhookController::class);
        Mail::fake();
        $this->assertDatabaseMissing('users', [
            'stripe_id' => $stripeCustomer['id'],
            'email' => $stripeCustomer['email'],
            'name' => $stripeCustomer['name'],
        ]);

        $stubbedCustomer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->getMock();
        $stubbedCustomer->expects(static::exactly(2))
            ->method('__get')
            ->withConsecutive(['id'], ['email'])
            ->willReturnOnConsecutiveCalls($stripeCustomer['id'], $stripeCustomer['email']);

        $this->mock(Customer::class)->allows('retrieve')->with($id)->andReturn($stubbedCustomer);

        $this->expectException(StripeDataIsMissingException::class);
        $currentMock->createOrUpdateUser($id);

        $this->assertDatabaseMissing('users', [
            'stripe_id' => $stripeCustomer['id'],
            'email' => $stripeCustomer['email'],
            'name' => $stripeCustomer['name'],
        ]);
        Mail::assertNothingSent();
    }

    /**
     * Data provider for {@see createOrUpdateUser_withoutStripeData_throwsStripeDataIsMissingException}
     *
     * @return array[] containing id to be requested from stripe and stripe customer stubbed response
     */
    public function createOrUpdateUser_withoutStripeDataProvider(): array
    {
        return [
            'Stripe Customer Response Email Missing' => [
                'id' => static::STRIPE_CUSTOMER['id'],
                'stripeCustomer' => [
                    'id' => static::STRIPE_CUSTOMER['id'],
                    'email' => null,
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
            ],
            'Stripe Customer Response Id Missing' => [
                'id' => static::STRIPE_CUSTOMER['id'],
                'stripeCustomer' => [
                    'id' => null,
                    'email' => static::STRIPE_CUSTOMER['email'],
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
            ],
        ];
    }

    /**
     * SetUp method for:
     * 1. {@see createOrUpdateUser_withSameCustomersInDatabase}
     * 2. {@see createOrUpdateUser_withSoftDeletedCustomersInDatabaseProvider}
     *
     * @return array[] containing:
     * 1. id to be requested from stripe
     * 2. stripe customer stubbed response
     * 3. database customer that is created in database before tested method call
     */
    private function customerWithSameStripeParametersInDatabaseSetUp(): array
    {
        return [
            'Customer with same email already exists' => [
                'id' => static::STRIPE_CUSTOMER['id'],
                'stripeCustomer' => [
                    'id' => static::STRIPE_CUSTOMER['id'],
                    'email' => static::STRIPE_CUSTOMER['email'],
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
                'databaseCustomer' => [
                    'stripe_id' => 'cus_43341414',
                    'email' => static::STRIPE_CUSTOMER['email'],
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
            ],
            'Customer with same stripe id already exists' => [
                'id' => static::STRIPE_CUSTOMER['id'],
                'stripeCustomer' => [
                    'id' => static::STRIPE_CUSTOMER['id'],
                    'email' => static::STRIPE_CUSTOMER['email'],
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
                'databaseCustomer' => [
                    'stripe_id' => static::STRIPE_CUSTOMER['id'],
                    'email' => 'jane.doe@gmail.com',
                    'name' => static::STRIPE_CUSTOMER['name'],
                ],
            ],
        ];
    }

    /**
     * @test
     * that createOrUpdateUser stop the script if there already exists the same customer
     * in the database by stripe id or email
     *
     * @covers ::createOrUpdateUser
     *
     * @dataProvider createOrUpdateUser_withSameCustomersInDatabase
     *
     * @param string $id of the Stripe customer that will be requested from Stripe API
     * @param array $stripeCustomer stubbed response of the Stripe API, includes (id, name, email)
     * @param array $databaseCustomer that will be created in the database before tested method call
     */
    public function createOrUpdateUser_withSameCustomersInDatabase_stopsTheScript(
        string $id,
        array $stripeCustomer,
        array $databaseCustomer
    ) {
        $currentMock = $this->partialMock(WebhookController::class);
        Mail::fake();
        User::factory()->create([
            'stripe_id' => $databaseCustomer['stripe_id'],
            'email' => $databaseCustomer['email'],
            'name' => $databaseCustomer['name'],
        ]);

        $stubbedCustomer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->getMock();
        $stubbedCustomer->expects(static::exactly(2))
            ->method('__get')
            ->withConsecutive(['id'], ['email'])
            ->willReturnOnConsecutiveCalls($stripeCustomer['id'], $stripeCustomer['email']);

        $this->mock(Customer::class)->allows('retrieve')->with($id)->andReturn($stubbedCustomer);

        $currentMock->createOrUpdateUser($id);

        $this->assertDatabaseHas('users', [
            'stripe_id' => $databaseCustomer['stripe_id'],
            'email' => $databaseCustomer['email'],
            'name' => $databaseCustomer['name'],
        ]);
        Mail::assertNothingSent();
    }

    /**
     * Data provider {@see createOrUpdateUser_withSameCustomersInDatabase_stopsTheScript}
     *
     * @return array[] containing:
     * 1. id to be requested from stripe
     * 2. stripe customer stubbed response
     * 3. database customer that is created in database before tested method call
     */
    public function createOrUpdateUser_withSameCustomersInDatabase(): array
    {
        return $this->customerWithSameStripeParametersInDatabaseSetUp();
    }

    /**
     * @test
     * that createOrUpdateUser resurrect soft-deleted customer from the database
     * if the soft-deleted customer has been matched by email or stripe_id
     *
     * @covers ::createOrUpdateUser
     *
     * @dataProvider createOrUpdateUser_withSoftDeletedCustomersInDatabaseProvider
     *
     * @param string $id of the Stripe customer that will be requested from Stripe API
     * @param array $stripeCustomer stubbed response of the Stripe API, includes (id, name, email)
     * @param array $databaseCustomer that will be created in the database before tested method call
     */
    public function createOrUpdateUser_withSoftDeletedCustomersInDatabase_undeletesTheCustomer(
        string $id,
        array $stripeCustomer,
        array $databaseCustomer
    ) {
        $currentMock = $this->partialMock(WebhookController::class);
        Mail::fake();
        User::factory()->create([
            'stripe_id' => $databaseCustomer['stripe_id'],
            'email' => $databaseCustomer['email'],
            'name' => $databaseCustomer['name'],
            'deleted_at' => now()->subDays(rand()),
        ]);
        $this->assertSoftDeleted('users', [
            'stripe_id' => $databaseCustomer['stripe_id'],
            'email' => $databaseCustomer['email'],
            'name' => $databaseCustomer['name'],
        ]);

        $stubbedCustomer = $this->getMockBuilder(Customer::class)->disableOriginalConstructor()->getMock();
        $stubbedCustomer->expects(static::exactly(2))
            ->method('__get')
            ->withConsecutive(['id'], ['email'])
            ->willReturnOnConsecutiveCalls($stripeCustomer['id'], $stripeCustomer['email']);

        $this->mock(Customer::class)->allows('retrieve')->with($id)->andReturn($stubbedCustomer);

        $currentMock->createOrUpdateUser($id);

        $this->assertNotSoftDeleted('users', [
            'stripe_id' => $databaseCustomer['stripe_id'],
            'email' => $databaseCustomer['email'],
            'name' => $databaseCustomer['name'],
        ]);
        Mail::assertNothingSent();
    }

    /**
     * Data provider for {@see createOrUpdateUser_withSoftDeletedCustomersInDatabase_undeletesTheCustomer}
     *
     * @return array[] containing:
     * 1. id to be requested from stripe
     * 2. stripe customer stubbed response
     * 3. database customer that is created in database before tested method call
     */
    public function createOrUpdateUser_withSoftDeletedCustomersInDatabaseProvider(): array
    {
        return $this->customerWithSameStripeParametersInDatabaseSetUp();
    }
}
