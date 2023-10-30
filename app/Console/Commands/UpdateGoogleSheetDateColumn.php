<?php

namespace App\Console\Commands;

use App\FacebookGroups;
use App\Jobs\MarketingAutomation\GoogleSheet\FormatGoogleSheetDates;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Console\Command;

/**
 * Updates all sheet documents date column for all active groups with GoogleSheet integration
 */
class UpdateGoogleSheetDateColumn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customers-google-sheet-date-column:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates all customers google sheet document date field to the '
                            . self::DATE_FORMAT
                            . ' format';

    /**
     * Google Sheet date format that will apply for all Google Sheet documents
     *
     * @string
     */
    private const DATE_FORMAT = 'yyyy-mm-dd"T"HH:mm:ss';

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
        $groups = FacebookGroups::whereHas('googleSheetIntegration')->get();

        foreach ($groups as $group) {
            try {
                dispatch(new FormatGoogleSheetDates($group->id, static::DATE_FORMAT));
            } catch (\Exception $exception) {
                # We will log the problem with this failed job and continue processing the others
                Bugsnag::notifyException($exception);
            }
        }
    }
}
