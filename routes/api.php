<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\DisabledGroupController;
use App\Http\Controllers\API\GroupController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/login', 'API\AuthController@login')->middleware(['validate.ajax.request']);
Route::group(['namespace' => 'API', 'middleware' => ['auth:api', 'validate.ajax.request']], function () {
    Route::get('getUser', 'ScrapingController@index')->name('getUser');
    Route::post('recordSave', 'ScrapingController@store')->name('recordSave');
    Route::post('saveAutoresponder', 'ScrapingController@saveAutoresponder')->name('saveAutoresponder');
    Route::post('deleteAutoresponder', 'ScrapingController@deleteAutoresponder')->name('deleteAutoresponder');

    /* Group */
    Route::get('getGroups', 'GroupController@index')->name('getGroups');
    Route::get('groupsByID/{id}', 'GroupController@groupFilterByID')->name('groupsByID');
    Route::get('groups/{id}', 'GroupController@groupDetails')->name('groups');
    Route::get('groupsDelete/{id}', 'GroupController@destroy')->name('groupsDelete');
    Route::post('groups/import', 'GroupController@importTextFile')->name('groups.import');
    Route::post('groups/importCsv', 'GroupController@importCsv')->name('groups.importCsv');
    Route::post('groups/addMembers', 'GroupController@addMembers')->name('groups.addMembers');
    Route::post('groups/setColumnsVisibility', [GroupController::class, 'setColumnsVisibility'])
        ->name('groups.setColumnsVisibility');
    Route::get('groups/getColumnsVisibility/{facebookGroup}', [GroupController::class, 'getColumnsVisibility'])
        ->name('groups.getColumnsVisibility');
    Route::post('getMembersNames', [GroupController::class, 'getMembersNames'])
        ->name('groups.getMembersNames');
    Route::get('groups/settings/{facebookGroup}', [GroupController::class, 'getGroupSettings'])
        ->name('groups.getGroupSettings');
    Route::post('groups/columns-width', [GroupController::class, 'setColumnsWidth'])
        ->name('groups.setGroupColumnsWidth');

    Route::get('disabled-groups', [DisabledGroupController::class, 'index'])
        ->name('disabledGroups.index');
    Route::post('disabled-groups', [DisabledGroupController::class, 'store'])
        ->name('disabledGroups.store');
    Route::post('disabled-groups/destroy', [DisabledGroupController::class, 'destroy'])
        ->name('disabledGroups.destroy');

    /* Member */
    Route::get('getGroupsTag/{id}', 'MemberController@getGroupsTag')->name('getGroupsTag');
    Route::post('memberUpdate', 'MemberController@update')->name('memberUpdate');
    Route::post('member', 'MemberController@index')->name('member');
    Route::post('removeGroupMembers', 'MemberController@removeGroupMembers')->name('removeGroupMembers');
    Route::post('members/bulkManageTags', 'MemberController@bulkManageTags')->name('members.bulkManageTags');
    Route::post('members/getMembersTagsList', 'MemberController@getMembersTagsList')->name('members.tags.list');
    Route::post('members/buildCsv', 'MemberController@buildCsv')->name('members.buildCsv');

    /* ActiveCampaign */
    Route::post('activeCampaign', 'ActiveCampaignController@index')->name('activeCampaign');

    /* Mailerlite */
    Route::post('mailerlite', 'MailerliteController@index')->name('mailerlite');

    /* GetResponse */
    Route::post('getresponse', 'GetResponseController@index')->name('getresponse');

    /* ConvertKit */
    Route::post('getConvertKit', 'ConvertKitController@index')->name('getConvertKit');

    /* MailChimp */
    Route::post('getMailchimp', 'MailChimpController@index')->name('getMailchimp');

    /* GoHighLevel */
    Route::post('getGoHighLevel', 'GoHighLevelController@index')->name('getGoHighLevel');

    /* Kartra */
    Route::post('getKartra', 'KartraController@index')->name('getKartra');

    /* Aweber */
    Route::post('getToken', 'AweberController@getToken')->name('getToken');
    Route::post('getRefreshToken', 'AweberController@getRefreshToken')->name('getRefreshToken');
    Route::post('getAweberAccount', 'AweberController@getAweberAccount')->name('getAweberAccount');
    Route::post('getaweber', 'AweberController@index')->name('getaweber');

    /* Google */
    Route::get('googleRefreshToken/{id}', 'GoogleSheetController@googleRefreshToken')->name('googleRefreshToken');
    Route::post('google-sheet/send-headers', 'GoogleSheetController@sendHeaders')->name('googleSheet.sendHeaders');

    Route::post('sendToIntegration', 'MemberController@sendToIntegration')->name('sendToIntegration');

    /* OntraPort */
    Route::post('ontraPort', 'OntraPortController@verifyCredentials')->name('ontraPort');

    /* InfusionSoft */
    Route::post('infusionSoft', 'InfusionSoftController@verifyCredentials')->name('infusionSoft');
    Route::get('infusionSoft/getTags/{facebookGroupId}', 'InfusionSoftController@getTags')->name('getTags');

    Route::get('checkJobs', 'JobController@check')->name('checkJobs');
});

/* Admin API */
Route::group([
    'prefix'     => 'admin/v1',
    'namespace'  => 'Admin\Api\V1',
    'middleware' => ['admin.request', 'validate.ajax.request'],
], function () {
    Route::get('getUsersList', 'AdminController@getUsersList')->name('getUsersList');
    Route::put('updateUserStatus', 'AdminController@updateUserStatus')->name('updateUserStatus');
    Route::delete('removeUser', 'AdminController@removeUser')->name('removeUser');
    Route::get('getUserDetails', 'AdminController@getUserDetails')->name('getUserDetails');
    Route::put('updateUsersPassword', 'AdminController@updateUsersPassword')->name('updateUsersPassword');
    Route::post('addTeamMember', 'AdminController@addTeamMember')->name('addTeamMember');
    Route::post('createUser', 'AdminController@createUser');
    Route::get('getSubscriptions', 'AdminController@getSubscriptions')->name('getSubscriptions');
    Route::get('getApproveMembersCount', 'AdminController@getApproveMembersCount')->name('getApproveMembersCount');
    Route::post('resetMonthlyApproval', 'AdminController@resetMonthlyApproval')->name('resetMonthlyApproval');
    Route::put('sendNewEmailActivationLink', 'AdminController@sendNewEmailActivationLink')->name('sendNewEmailActivationLink');
});
