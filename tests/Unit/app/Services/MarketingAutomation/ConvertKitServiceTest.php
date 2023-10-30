<?php

namespace Tests\Unit\app\Services\MarketingAutomation;

use App\AutoResponder;
use App\FacebookGroups;
use App\Services\MarketingAutomation\AbstractMarketingService;
use App\Services\MarketingAutomation\ConvertKitService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;
use Tests\TestHelper;

/**
 * Class ConvertKitServiceTest adds test coverage for {@see ConvertKitService}
 *
 * @package Tests\Unit\app\Services\ConveertKitService
 * @coversDefaultClass \App\Services\MarketingAutomation\ConvertKitService
 */
class ConvertKitServiceTest extends TestCase
{
    use DatabaseTransactions;
    use DatabaseMigrations;

    /**
     * Name of the marketing service that will be covered with test cases
     *
     * @var string
     */
    private const SERVICE_NAME = 'ConvertKit';

    /**
     * Autoresponder extra parameters, stored in responder_json
     *
     * @var object
     */
    private object $extraParameters;

    /**
     * Setup test dependencies
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->extraParameters = (object)[
            'api_key' => 'test_api_key',
            'api_secret' => 'test_api_secret',
            'activeList' => (object)[
                'label' => 'test_label',
                'value' => 'test_value',
            ],
            'custom_labels' => [
                'test_label_1',
            ],
            'custom_labels_mapper' => [
                 (object)[
                     'label' => 'test_label_1',
                     'member_field' => 'test_member_field_1',
                     'id' => 1,
                     'name' => 'ck_test_member_field_1',
                     'key' => 'test_member_field_1',
                 ],
             ],
        ];
    }

    /**
     * Sets property default value.
     *
     * @throws ReflectionException if apiInfo property doesn't exist
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        TestHelper::setNonPublicProperty(AbstractMarketingService::class, 'apiInfo', []);
    }

   /**
    * @test
    * that updateIntegrationData updates the integration data in the database
    * for the given group when new integration data is not a multidimensional array
    * and the label of new integration data matches with the label of integration data in the database.
    *
    * @covers ::updateIntegrationData
    *
    * @throws ReflectionException if updateIntegrationData method is not defined.
    */
    public function updateIntegrationData_withNonMultidimensionalIntegrationArray_savesIntegrationDataInDatabase(): void
    {
        $group = FacebookGroups::factory()->create();
        AutoResponder::factory()->create([
            'group_id' => $group->id,
            'responder_type' => self::SERVICE_NAME,
            'responder_json' => json_encode($this->extraParameters),
        ]);

        $newIntegrationData = [
            'label' => 'test_label_1',
            'id' => 1,
            'name' => 'ck_test_member_field_1',
            'key' => 'test_member_field_1',
        ];

        $updatedIntegrationData = [
            'api_key' => $this->extraParameters->api_key,
            'api_secret' => $this->extraParameters->api_secret,
            'activeList' => $this->extraParameters->activeList,
            'custom_labels' => $this->extraParameters->custom_labels,
            'custom_labels_mapper' => [
                (object)[
                    'label' => 'test_label_1',
                    'member_field' => 'test_member_field_1',
                    'id' => 1,
                    'name' => 'ck_test_member_field_1',
                    'key' => 'test_member_field_1',
                ],
            ],
        ];

        $currentMock = $this->createMock(ConvertKitService::class);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'updateIntegrationData',
            [$group->id, $newIntegrationData]
        );

        $this->assertDatabaseHas('auto_responder', [
            'group_id' => $group->id,
            'responder_type' => self::SERVICE_NAME,
            'responder_json' => json_encode($updatedIntegrationData),
        ]);
    }

    /**
     * @test
     * that getCustomFieldsToCreate returns the custom fields to create accordingly
     *
     * @covers ::getCustomFieldsToCreate
     *
     * @throws ReflectionException if getCustomFieldsToCreate method is not defined.
     */
    public function getCustomFieldsToCreate_always_returnsCustomFieldsToCreate()
    {
        Http::fake([
            'https://api.convertkit.com/v3/custom_fields?api_key=' . $this->extraParameters->api_key =>
                Http::response([
                'custom_fields' => [
                    [
                        'label' => 'test_label_2',
                        'id' => 2,
                        'name' => 'ck_test_member_field_2',
                        'key' => 'test_member_field_2',
                    ],
                ],
            ], Response::HTTP_OK),
        ]);

        $facebookGroup = FacebookGroups::factory()->create();
        AutoResponder::factory()->create([
            'group_id' => $facebookGroup->id,
            'responder_type' => self::SERVICE_NAME,
            'responder_json' => json_encode($this->extraParameters),
        ]);

        $currentMock = $this->createMock(ConvertKitService::class);
        $customFieldsToCreate = TestHelper::callNonPublicFunction(
            $currentMock,
            'getCustomFieldsToCreate',
            [$facebookGroup->id]
        );

        $this->assertEquals($this->extraParameters->custom_labels, $customFieldsToCreate);
    }

    /**
     * @test
     * that connectExistingField connects the existing integration custom field to the given group
     * fields if it already exists in the integration
     *
     * @param array $customLabelsMapper current database custom labels mapper
     * @param array $expectedCustomLabelsMapper expected custom labels mapper after the call
     *
     * @dataProvider connectExistingField_withVariousIntegrationCustomFieldsProvider
     *
     * @covers ::connectExistingFields
     *
     * @throws ReflectionException if connectExistingFields method is not defined.
     */
    public function connectExistingField_withVariousIntegrationCustomFields_savesIntegrationFieldsInTheDatabase (
        array $customLabelsMapper,
        array $expectedCustomLabelsMapper
    ): void {
        $this->extraParameters->custom_labels_mapper = $customLabelsMapper;

        $group = FacebookGroups::factory()->create();
        AutoResponder::factory()->create([
            'group_id' => $group->id,
            'responder_type' => self::SERVICE_NAME,
            'responder_json' => json_encode($this->extraParameters),
        ]);

        $existingCustomFieldsFromIntegration = [
            [
                'label' => 'test_label_1',
                'id' => 1,
                'name' => 'ck_test_member_field_1',
                'key' => 'test_member_field_1',
            ],
            [
                'label' => 'test_label_2',
                'id' => 2,
                'name' => 'ck_test_member_field_2',
                'key' => 'test_member_field_2',
            ],
        ];

        $updatedIntegrationData = [
            'api_key' => $this->extraParameters->api_key,
            'api_secret' => $this->extraParameters->api_secret,
            'activeList' => $this->extraParameters->activeList,
            'custom_labels' => $this->extraParameters->custom_labels,
            'custom_labels_mapper' => $expectedCustomLabelsMapper,
        ];

        $currentMock = $this->createMock(ConvertKitService::class);
        TestHelper::callNonPublicFunction(
            $currentMock,
            'connectExistingFields',
            [$group->id, $existingCustomFieldsFromIntegration]
        );

        $this->assertDatabaseHas('auto_responder', [
            'group_id' => $group->id,
            'responder_type' => self::SERVICE_NAME,
            'responder_json' => json_encode($updatedIntegrationData),
        ]);
    }

    /**
     * Data provider for
     * @see connectExistingField_withVariousIntegrationCustomFields_savesIntegrationFieldsInTheDatabase
     *
     * @return array containing the custom labels mapper and the expected custom labels mapper
     */
    public function connectExistingField_withVariousIntegrationCustomFieldsProvider(): array
    {
        return [
            'empty custom labels mapper' => [
                'customLabelsMapper' => [],
                'expectedCustomLabelsMapper' => [],
            ],
            'custom labels mapper with existing field' => [
                'customLabelsMapper' => [
                    (object)[
                        'label' => 'test_label_1',
                        'member_field' => 'test_member_field_1',
                        'id' => 1,
                        'name' => 'ck_test_member_field_1',
                        'key' => 'test_member_field_1',
                    ],
                    (object)[
                        'label' => 'test_label_2',
                        'member_field' => 'test_member_field_2',
                    ],
                ],
                'expectedCustomLabelsMapper' => [
                    (object)[
                        'label' => 'test_label_1',
                        'member_field' => 'test_member_field_1',
                    ],
                    (object)[
                        'label' => 'test_label_2',
                        'member_field' => 'test_member_field_2',
                        'id' => 2,
                        'name' => 'ck_test_member_field_2',
                        'key' => 'test_member_field_2',
                    ],
                ],
            ],
        ];
    }
}
