<?php

namespace App\Console\Commands;

use App\GroupMembers;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Removes group members CSV files older than specified hours
 *
 * @package App\Console\Commands
 */
class RemoveMembersOldCSVFiles extends Command
{
    /**
     * Amount of time in hours after which CSV files should be deleted.
     *
     * @var int
     */
    private const HOLD_CSV_FILES_HOURS = 24;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'members-csv-files:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes group member CSV files older than '
                            . self::HOLD_CSV_FILES_HOURS
                            . ' hours from the storage';

    /**
     * Executes the console command.
     */
    public function handle(): void
    {
        $csvFiles = Storage::disk('local')->listContents(GroupMembers::CSV_FILES_PATH);

        foreach ($csvFiles as $csvFile) {
            if (now()->diffInHours(Carbon::parse($csvFile['timestamp'])) > static::HOLD_CSV_FILES_HOURS) {
                Storage::disk('local')->delete($csvFile['path']);
            }
        }
    }
}
