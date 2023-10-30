<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('gmailAuth', 'API\GoogleSheetController@redirectToProvider');
Route::get('gmailAuth/callback', 'API\GoogleSheetController@handleProviderCallback');
Route::get('infusionSoftAuth/callback', 'API\InfusionSoftController@handleProviderCallback')
    ->name('infusionSoftAuthCallback');

Auth::routes(['verify' => true]);
Route::get('setPassword/{email}/{token}', 'Auth\LoginController@setPassword')->name('setPassword');
Route::Post('setPassword', 'Auth\LoginController@updatePassword')->name('newPassword');
Route::get('auto_login/{token}', 'Auth\LoginController@autoLogin')->name('autoLogin');
Route::post('verify', 'Auth\LoginController@verifyEmail')->name('verify');
Route::get('logout', 'Auth\LoginController@logout');

Route::group(['middleware' => 'guest'], function () {
    Route::get('login/{provider}', 'Auth\SocialController@redirectToProvider')->name('social.login');
    Route::get('login/{provider}/callback', 'Auth\SocialController@handleProviderCallback');
    Route::get('login/{provider}/confirmPassword', 'Auth\SocialController@showPasswordConfirmation')
        ->name('social.login.confirmPassword');
    Route::post('login/confirmPasswordPost', 'Auth\SocialController@confirmPasswordPost')
        ->name('social.login.confirmPasswordPost');
});

/** User and guest access */
Route::group(['middleware' => ['user']], function () {
    Route::get('noGroupsAssigned', 'HomeController@noGroupsAssigned')->name('noGroupsAssigned');
    /** Plan routes */
    Route::get('webinar', 'PlanController@webinar')->name('webinar');
    Route::get('plans', 'PlanController@index')->name('plans.index');
    Route::get('plan/{plan}', 'PlanController@show')->name('plans.show');
    Route::get('getPlanDetails', 'PlanController@getPlanDetails')->name('getPlanDetails');
    /** Subscription routes */
    Route::post('subscription/upgradePlan', 'SubscriptionController@redirectToUpgradePlan')
        ->name('subscription.upgradePlan');
    Route::post('subscription/create', 'SubscriptionController@create')->name('subscription.create');
    Route::get('autorenewplan', 'SubscriptionController@autoRenewPlan')->name('subscription.autorenewplan');
    Route::get('cancelSubscription', 'SubscriptionController@cancelSubscription')
        ->name('subscription.cancelSubscription');
    Route::get('subscriptionOptions', 'HomeController@subscriptionOptions')->name('subscriptionOptions');
    Route::post('upgradeToProPlan', 'SubscriptionController@upgradeToProPlan')
        ->name('subscription.upgradeToProPlan');
    Route::post('subscription/pauseOrContinueSubscription', 'SubscriptionController@pauseOrContinueSubscription')
        ->name('subscription.pauseOrContinueSubscription');
    Route::post('subscription/downgradeToBasicPlan', 'SubscriptionController@downgradeToBasicPlan')
        ->name('subscription.downgradeToBasicPlan');
});
Route::get('gkthanks', 'HomeController@gkthanks')->name('gkthanks');
Route::get('wait', 'HomeController@wait')->name('wait');
Route::post('validateEmail', 'PlanController@validateEmail')->name('validateEmail');

/** Authenticated access */
Route::group(['middleware' => 'auth'], function () {
    /** Team Member  */
    Route::group(['middleware' => ['team.member']], function () {
        Route::get('teamMembers/getData', 'TeamMembersController@getData')->name('getData');
        Route::get('teamMembers/getTeamMember/{id}', 'TeamMembersController@getTeamMember')->name('getTeamMember');
        Route::get('teamMembers', 'TeamMembersController@teamMembers')->name('teamMembers');
        Route::put('teamMembers/{id}', 'TeamMembersController@update')->name('teamMembers.update');
        Route::post('teamMembers', 'TeamMembersController@store')->name('teamMembers.store');
        Route::post('teamMembers/remove', 'TeamMembersController@destroyTeamMembers')->name('remove');
        Route::post('teamMembers/checkTeamMembersEmail', 'TeamMembersController@checkTeamMembersEmail')
            ->name('checkTeamMembersEmail');
        Route::post('teamMembers/getEmail', 'TeamMembersController@getEmail')->name('getEmail');
        Route::post('team-members/re-send-invitation', 'TeamMembersController@reSendInvitation')
            ->name('teammembers.reSendInvitation');
    });

    /** Team User access */
    Route::group(['middleware' => ['team.user']], function () {
        Route::get('/', 'HomeController@index')->name('home');
        Route::get('home', 'HomeController@index')->name('home');
        Route::get('setting', 'HomeController@setting')->name('setting');
        Route::post('user/update', 'HomeController@update')->name('user.update');
        Route::post('user/sendNewEmailActivationLink', 'HomeController@sendNewEmailActivationLink')
            ->name('user.sendNewEmailActivationLink');
        Route::get('appLogout/{token}', 'Auth\LoginController@appLogout')->name('appLogout');
        Route::get('group-members/csv/{fileName}', 'GroupMemberController@downloadCSV');
    });

    Route::get('groups/{id}', 'GroupController@show')->where('id', '[0-9]+');
    Route::get('giveaway', 'HomeController@giveaway')->name('giveaway');

    Route::post('settings/updateCard', 'HomeController@updateCard')->name('settings.updateCard');
    Route::get('settings/getClientSecret', 'HomeController@getClientSecret')->name('settings.getClientSecret');
});

Route::get('user/active/{code}', 'HomeController@activateNewEmail')->name('user.activateNewEmail');
Route::post('stripe/webhook', '\App\Http\Controllers\WebhookController@index');
