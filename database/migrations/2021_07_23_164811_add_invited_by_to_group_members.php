<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class AddInvitedByToGroupMembers adds invited_by_member_id column to group members and makes it a foreign key
 * that internally references the groupmembers.id
 */
class AddInvitedByToGroupMembers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->unsignedInteger('invited_by_member_id')->nullable()->after('user_id');
            $table->foreign('invited_by_member_id')->references('id')->on('group_members')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('group_members', 'invited_by_member_id')) {
            Schema::table('group_members', function (Blueprint $table) {
                # Adds support for SQLite as test storage engine since SQLite doesn't have foreign keys
                if (env('DB_CONNECTION') === 'sqlite') {
                    return;
                }
                $table->dropForeign(['invited_by_member_id']);
                $table->dropColumn('invited_by_member_id');
            });
        }
    }
}
