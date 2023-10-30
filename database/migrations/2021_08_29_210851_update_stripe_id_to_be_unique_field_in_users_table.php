<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Class UpdateStripeIdToBeUniqueFieldInUsersTable finds duplicate values and sets them to null
 * except the first and stripe_id changes unique.
 */
class UpdateStripeIdToBeUniqueFieldInUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $duplicateStripeIdRows = DB::table('users')
            ->select('id', 'stripe_id')
            ->whereNotIn('id', function ($q) {
                $q->select(DB::raw('MIN(id)'))
                    ->from('users')
                    ->groupBy('stripe_id')
                    ->havingRaw('COUNT(*) >= 1');
            })
            ->whereNotNull('stripe_id')
            ->orderBy('id')
            ->get();

        if (count($duplicateStripeIdRows) > 0) {
            $output = new ConsoleOutput();

            foreach ($duplicateStripeIdRows as $duplicateStripeIdRow) {
                $output
                    ->writeln(
                        "<info>USER_ID: " . $duplicateStripeIdRow->id
                        . " STRIPE_ID: " . $duplicateStripeIdRow->stripe_id . "</info>"
                    );
            }

            DB::table('users')
                ->whereNotIn('id', function ($q) {
                    $q->select(DB::raw('MIN(id)'))
                        ->from('users')
                        ->groupBy('stripe_id')
                        ->havingRaw('COUNT(*) >= 1');
                })
                ->whereNotNull('stripe_id')
                ->update(['stripe_id' => null]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('stripe_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['stripe_id']);
        });
    }
}
