<?php

use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFacebookTokenUserColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'users',
            function (Blueprint $table) {
                /** @link https://developers.facebook.com/blog/post/45/ */
                $table->unsignedBigInteger(User::getUserIdFieldForSocialProvider('facebook'))->nullable();
                $table->text(User::getAccessTokenFieldForSocialProvider('facebook'))->nullable();
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'users',
            function (Blueprint $table) {
                $table->dropColumn(
                    [
                        User::getUserIdFieldForSocialProvider('facebook'),
                        User::getAccessTokenFieldForSocialProvider('facebook')
                    ]
                );
            }
        );
    }
}
