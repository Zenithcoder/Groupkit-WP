<?php

namespace App\Jobs\MarketingAutomation\GoogleSheet;

use App\Services\MarketingAutomation\GoogleSheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Updates existing Google Sheet document date field value to the new date format
 */
class UpdateDateAddTimeValue implements ShouldQueue
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
     * Create a new job instance.
     *
     * @param int $facebookGroupId of the group connected to the Google Sheet integration
     */
    public function __construct(int $facebookGroupId)
    {
        $this->facebookGroupId = $facebookGroupId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        app(GoogleSheetService::class)->updateExistingDateAddedValue($this->facebookGroupId);
    }
}
