<?php

return [
    "GOOGLE_API_URL_TOKEN_INFO" => "https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=",
    "GOOGLE_API_URL_TOKEN"      => "https://www.googleapis.com/oauth2/v4/token",
    'GROUP_LINK'                => "https://www.facebook.com/groups/",
    /* SHEET_RANGE contains SHEET_START_RANGE, SHEET_END_RANGE, MAX_RANGE
     * SHEET_START_RANGE represents google sheet column headers starting columns location.
     * SHEET_END_RANGE represents google sheet column headers last columns location
     * SHEET_MAX_RANGE represents google sheet columns max range which we supports.
     */
    'SHEET_RANGE'               => [
        'START_RANGE' => 'A',
        'END_RANGE'   => 'O',
        'MAX_RANGE'   => 'AZ',

    ],
    /* COLUMNS_HEADERS represents google sheet headers that will be exported to the connected google sheet document*/
    'COLUMNS_HEADERS' => [
        'date_add_time' => 'Date Added',
        'f_name' => 'First Name',
        'l_name' => 'Last Name',
        'email' => 'Email Address',
        'fb_id' => 'User ID',
        'a1' => 'Q1 Answer',
        'a2' => 'Q2 Answer',
        'a3' => 'Q3 Answer',
        'messenger_url' => 'Messenger URL',
        'approved_by' => 'Approved By',
        'invited_by' => 'Invited By',
        'lives_in' => 'Lives In',
        'agreed_group_rules' => 'Agreed To Group Rules',
        'id' => 'ID',
        'tags' => 'Tags',
        'notes' => 'Notes',
        'phone_number' => 'Phone Number',
    ],
    'COLUMN_LIMIT_EXCEEDED' => 'Sheets columns range should not be greater then AZ.',
    'DATE_FORMAT' => 'c',
    'MAXIMUM_MEMBERS_NUMBER_TO_SEND' => env('MIX_GOOGLE_SHEET_MAXIMUM_MEMBERS_NUMBER_TO_SEND'),
];
