<?php

namespace App\Jobs\MarketingAutomation\ConvertKit;

use App\FacebookGroups;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Updates custom fields with provided custom fields for provided facebook group
 */
class UpdateCustomFields implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Facebook group id of group which members will be updated
     *
     * @var int
     */
    private int $facebookGroupId;

    /**
     * Custom fields that we will add to the customers convert kit integrations
     *
     * @var array
     */
    private array $customFields;

    /**
     * Create a new job instance.
     *
     * @param int $facebookGroupId of the group connected to the ConvertKit integration
     * @param array $customFields to be updated in the group's convert kit integrations
     */
    public function __construct(int $facebookGroupId, array $customFields)
    {
        $this->facebookGroupId = $facebookGroupId;
        $this->customFields = $customFields;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $facebookGroup = FacebookGroups::whereHas('convertKitIntegration')
            ->with('convertKitIntegration')
            ->find($this->facebookGroupId);

        foreach ($facebookGroup->convertKitIntegration as $convertKitIntegration) {
            $integrationDetails = json_decode($convertKitIntegration->responder_json);
            $integrationDetails->custom_labels = !isset($integrationDetails->custom_labels)
                ? array_column($this->customFields, 'label')
                : array_merge( #if there are already custom label we are merging existing with new labels
                    $integrationDetails->custom_labels,
                    array_diff($integrationDetails->custom_labels, array_column($this->customFields, 'label'))
                );

            $integrationDetails->custom_labels_mapper = !isset($integrationDetails->custom_labels_mapper)
                ? $this->customFields
                : array_merge(
                    $integrationDetails->custom_labels_mapper,
                    array_filter( #get only new added custom labels mappers
                        $this->customFields,
                        function (array $customField) use ($integrationDetails) {
                            return is_bool( #catching false to determine that new label mapper is unique
                                array_search(
                                    $customField['label'],
                                    array_column($integrationDetails->custom_labels_mapper, 'label')
                                )
                            );
                        }
                    )
                );
            $convertKitIntegration->responder_json = json_encode($integrationDetails);

            $convertKitIntegration->save();
        }
    }
}
