<?php

namespace App\Console\Commands;

use App\FacebookGroups;
use App\Jobs\MarketingAutomation\ConvertKit\UpdateCustomFields;
use Illuminate\Console\Command;

/**
 * Updates all customers ConvertKit custom fields
 */
class UpdateCustomFieldsForConvertKitIntegration extends Command
{
    /**
     * Custom fields that we will add to the customers convert kit integrations
     *
     * @var array including label with field name, and member_field with matcher from group_members table
     */
    private const CUSTOM_FIELDS_TO_UPDATE = [
        [
            'label' => 'LAST NAME',
            'member_field' => 'l_name',
        ],
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convertkit-custom-fields:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all customers custom fields for ConvertKit integration';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $groups = FacebookGroups::whereHas('convertKitIntegration')->get();

        foreach ($groups as $group) {
            dispatch(new UpdateCustomFields($group->id, static::CUSTOM_FIELDS_TO_UPDATE));
        }
    }
}
