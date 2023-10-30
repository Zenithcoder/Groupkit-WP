<?php

use App\GroupMembers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddPhoneNumberToGroupMembersTable
 *
 * Add `phone_number` field in the `group_members` table
 */
class AddPhoneNumberToGroupMembersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->string('phone_number', 20)->nullable()->after('a3');
        });

        // Update the phone number field for all group members by
        // triggering the `saving` event
        foreach (GroupMembers::all() as $groupMember) {
            $groupMember->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
}
