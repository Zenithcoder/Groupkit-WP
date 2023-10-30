<?php

use App\FacebookGroups;
use App\Jobs\MarketingAutomation\GoogleSheet\FormatGoogleSheetDates;
use App\Jobs\MarketingAutomation\GoogleSheet\UpdateDateAddTimeValue;
use App\Services\MarketingAutomation\GoogleSheetService;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Bus;

/**
 * Change format value of the date add time column to
 * {@see \App\Services\MarketingAutomation\GoogleSheetService::DEFAULT_DATE_TIME_FORMAT}
 * for existing Google Sheet documents
 */
class NormalizeGoogleSheetDateAddTimeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $groups = FacebookGroups::whereHas('googleSheetIntegration')->get();

        foreach ($groups as $group) {
            try {
                Bus::chain([
                    new UpdateDateAddTimeValue($group->id),
                    new FormatGoogleSheetDates($group->id, GoogleSheetService::DATE_FORMATS['c']),
                ])->dispatch();
            } catch (Exception $exception) {
                # We will log the problem with this failed job and continue processing the others
                Bugsnag::notifyException($exception);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // We can't revert this action since we haven't, reference what formats have applied to which rows
    }
}
