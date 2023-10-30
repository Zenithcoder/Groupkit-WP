<?php

use App\GroupMembers;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Adds new enumerable value 'Invalid resource' to the 'group_members' table.
 * @see GroupMembers::RESPONSE_STATUSES
 */
class AddInvalidResourceEnumValueInGroupMembersTable extends Migration
{
    /**
     * Enumerable values for respond_status column.
     *
     * @var array
     */
    private array $enumValues;

    /**
     * Initialize $enumValues with the values from RESPONSE_STATUSES constant
     * in GroupMembers model, which is an associative array of enumerable values.
     */
    public function __construct()
    {
        $this->enumValues = array_values(GroupMembers::RESPONSE_STATUSES);
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        # Adds support for SQLite as test storage engine since SQLite doesn't have change function.
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        $this->alterTable();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        # Adds support for SQLite as test storage engine since SQLite doesn't have change function.
        if (env('DB_CONNECTION') === 'sqlite') {
            return;
        }

        /**
         * Removes the last element from the array of enumerable values.
         * @see GroupMembers::RESPONSE_STATUSES
         */
        array_pop($this->enumValues);
        $this->alterTable();
    }

    /**
     * Executes SQL statement.
     *
     * @return void
     */
    private function alterTable(): void
    {
        $enumValues = "'" . implode("','", $this->enumValues) . "'";

        DB::statement("ALTER TABLE `group_members` CHANGE `respond_status` `respond_status` ENUM($enumValues)");
    }
}
