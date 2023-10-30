<?php

use App\AutoResponder;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds `dateAddTimeFormat` to each Google Sheet integration
 * for dynamic change of the `DATE ADDED` field in the GoogleSheet document
 */
class AddDateTimeFormatToAllGoogleSheetIntegrations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $googleSheetIntegrations = AutoResponder::withTrashed()->where('responder_type', 'GoogleSheet')->get();

        foreach ($googleSheetIntegrations as $googleSheetIntegration) {
            $integrationOptions = json_decode($googleSheetIntegration->responder_json);
            $integrationOptions->dateAddTimeFormat = config('const.DATE_FORMAT');
            $googleSheetIntegration->responder_json = json_encode($integrationOptions);
            $googleSheetIntegration->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $googleSheetIntegrations = AutoResponder::withTrashed()->where('responder_type', 'GoogleSheet')->get();

        foreach ($googleSheetIntegrations as $googleSheetIntegration) {
            $integrationOptions = json_decode($googleSheetIntegration->responder_json);
            unset($integrationOptions->dateAddTimeFormat);
            $googleSheetIntegration->responder_json = json_encode($integrationOptions);
            $googleSheetIntegration->save();
        }
    }
}
