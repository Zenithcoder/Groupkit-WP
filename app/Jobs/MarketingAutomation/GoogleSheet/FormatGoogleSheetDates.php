<?php

namespace App\Jobs\MarketingAutomation\GoogleSheet;

use App\Exceptions\InvalidStateException;
use App\Services\MarketingAutomation\GoogleSheetService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Updates existing Google Sheet document date field of the provided Facebook group to the new date format
 */
class FormatGoogleSheetDates implements ShouldQueue
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
     * New date format for the Google Sheet date column
     *
     * @var string
     */
    private string $dateFormat;

    /**
     * Create a new job instance.
     *
     * @param int $facebookGroupId of the group connected to the Google Sheet integration
     * @param string $dateFormat Google Sheet format that will apply for all Google Sheet documents
     */
    public function __construct(int $facebookGroupId, string $dateFormat)
    {
        $this->facebookGroupId = $facebookGroupId;
        $this->dateFormat = $dateFormat;
    }

    /**
     * Execute the job.
     *
     * @throws GuzzleException if there is an error connecting to the Google API
     * @throws InvalidStateException if the Google sheet is not found or if we are missing API connection info
     * @throws RequestException if there is an error connecting to the Google API
     */
    public function handle()
    {
        app(GoogleSheetService::class)->updateExistingDocumentDateColumn(
            $this->facebookGroupId,
            $this->dateFormat
        );
    }
}
