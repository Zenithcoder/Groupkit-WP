<?php

namespace App\Services\MarketingAutomation;

use App\AutoResponder;
use App\Exceptions\Integrations\GoogleSheet\ColumnLimitExceededException;
use App\Exceptions\Integrations\GroupLimitExceededException;
use App\Exceptions\Integrations\NoMembersToSendException;
use App\Exceptions\InvalidStateException;
use App\GroupMembers;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

/**
 * Used to interact with Google Sheets
 * @link https://developers.google.com/sheets/api
 *
 * @package App\Services\MarketingAutomation
 */
class GoogleSheetService extends AbstractMarketingService
{
    /**
     * Determines if the service requires a group member email for that member's addition to this service
     *
     * @var bool
     */
    public const EMAIL_IS_REQUIRED = false;

    /**
     * The list of supported date time formats for the integration
     * Keys represents PHP date time format @link https://www.php.net/manual/en/datetime.format.php
     * while values represents Google Sheet date time formats
     * @link https://developers.google.com/sheets/api/guides/formats
     *
     * @var array
     */
    public const DATE_FORMATS = [
        'c' => 'yyyy-mm-dd"T"HH:mm:ss', #ISO-8601 Format (Zapier)
        'j-n-Y G:i:s' => 'dd-mm-yyyy HH:mm:ss', #Day-Month-Year
        'n-j-Y G:i:s' => 'mm-dd-yyyy HH:mm:ss', #Month-Day-Year
        'Y-n-j G:i:s' => 'yyyy-mm-dd HH:mm:ss', #Year-Month-Day
    ];

    /**
     * Default PHP date time format for GoogleSheet date columns
     * @link https://www.php.net/manual/en/datetime.format.php
     *
     * @var string
     */
    public const DEFAULT_DATE_TIME_FORMAT = 'n/j/Y G:i:s';

    /**
     * The kinds of value that a cell in a spreadsheet can have.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/other#extendedvalue
     *
     * @var array
     */
    public const EXTENDED_VALUE = [
        'numberValue' => 'numberValue',  #number,
        'stringValue' => 'stringValue',  #string,
        'boolValue' => 'boolValue',  #boolean,
        'formulaValue' => 'formulaValue',  #string,
        'errorValue' => 'errorValue',  #{object (ErrorValue)}
    ];

    /**
     * The format of a cell.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/cells#CellFormat
     *
     * @var array
     */
    public const CELL_FORMAT = [
        /**
         * [object (NumberFormat)]
         * @see \App\Services\MarketingAutomation\GoogleSheetService::NUMBER_FORMAT
         */
        'numberFormat' => 'numberFormat',
        'backgroundColor' => 'backgroundColor', # [object (Color)],
        'backgroundColorStyle' => 'backgroundColorStyle', # [object (ColorStyle)],
        'borders' => 'borders', # [object (Borders)],
        'padding' => 'padding', # [object (Padding)],
        /**
         * enum (HorizontalAlign)
         * @see \App\Services\MarketingAutomation\GoogleSheetService::HORIZONTAL_ALIGN
         */
        'horizontalAlignment' => 'horizontalAlignment',
        'verticalAlignment' => 'verticalAlignment', # enum (VerticalAlign),
        'wrapStrategy' => 'wrapStrategy', # enum (WrapStrategy),
        'textDirection' => 'textDirection', # enum (TextDirection),
        /**
         * [object (TextFormat)]
         * @see \App\Services\MarketingAutomation\GoogleSheetService::TEXT_FORMAT
         */
        'textFormat' => 'textFormat',
        'hyperlinkDisplayType' => 'hyperlinkDisplayType', # enum (HyperlinkDisplayType),
        'textRotation' => 'textRotation', # [object (TextRotation)]
    ];

    /**
     * The horizontal alignment of text in a cell.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/other#horizontalalign
     *
     * @var array
     */
    public const HORIZONTAL_ALIGN = [
        'LEFT' => 'LEFT',	    #The text is explicitly aligned to the left of the cell.
        'CENTER' => 'CENTER',	#The text is explicitly aligned to the center of the cell.
        'RIGHT' => 'RIGHT',	    #The text is explicitly aligned to the right of the cell.
    ];

    /**
     * The number format of a cell.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/cells#numberformat
     *
     * @var array
     */
    public const NUMBER_FORMAT = [
        /**
         * enum (NumberFormatType)
         * @see \App\Services\MarketingAutomation\GoogleSheetService::NUMBER_FORMAT_TYPE
         */
        'type' => 'type',
        'pattern' => 'pattern',  # string
    ];

    /**
     * The number format of the cell
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/cells#NumberFormatType
     *
     * @var array
     */
    public const NUMBER_FORMAT_TYPE = [
        'NUMBER_FORMAT_TYPE_UNSPECIFIED' => 'NUMBER_FORMAT_TYPE_UNSPECIFIED',
         # The number format is not specified and is based on the contents of the cell. Do not explicitly use this.
        'TEXT' => 'TEXT', # Text formatting, e.g 1000.12
        'NUMBER' => 'NUMBER', # Number formatting, e.g, 1,000.12
        'PERCENT' => 'PERCENT', # Percent formatting, e.g 10.12%
        'CURRENCY' => 'CURRENCY', # Currency formatting, e.g $1,000.12
        'DATE' => 'DATE', # Date formatting, e.g 9/26/2008
        'TIME' => 'TIME', # Time formatting, e.g 3:59:00 PM
        'DATE_TIME' => 'DATE_TIME', # Date+Time formatting, e.g 9/26/08 15:59:00
        'SCIENTIFIC' => 'SCIENTIFIC', # Scientific number formatting, e.g 1.01E+03
    ];

    /**
     * The format of a run of text in a cell. Absent values indicate that the field isn't specified.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/other#TextFormat
     *
     * @var array
     */
    public const TEXT_FORMAT = [
        'foregroundColor' => 'foregroundColor', # [object (Color)],
        'foregroundColorStyle' => 'foregroundColorStyle', # [object (ColorStyle)],
        'fontFamily' => 'fontFamily', # string,
        'fontSize' => 'fontSize', # integer,
        'bold' => 'bold', # boolean,
        'italic' => 'italic', # boolean,
        'strikethrough' => 'strikethrough', # boolean,
        'underline' => 'underline', # boolean,
        /**
         * [object (Link)]
         * @see \App\Services\MarketingAutomation\GoogleSheetService::LINK
         */
        'link' => 'link',
    ];

    /**
     * A run of a text format. The format of this run continues until the start index of the next run.
     * When updating, all fields must be set.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/cells#textformatrun
     *
     * @var array
     */
    public const TEXT_FORMAT_RUN = [
        'startIndex' => 'startIndex', # integer,
        /**
         * { object (TextFormat) }
         * @see \App\Services\MarketingAutomation\GoogleSheetService::TEXT_FORMAT
         */
        'format' => 'format',
    ];

    /**
     * An external or local reference.
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/other#link
     *
     * @var array
     */
    public const LINK = [
        'uri' => 'uri', # string
    ];

    /**
     * @var string shows this text for the Google Sheet URL if an exception
     *             is thrown from that service.
     */
    public const GOOGLE_SHEET_URL = 'GOOGLE SHEET URL';

    /**
     * The name of the service which is used as `responder_type` in the auto_responder table of the database
     * @see AutoResponder::SERVICE_TYPES
     *
     * @var string
     */
    protected static string $serviceName = 'GoogleSheet';

    /**
     * Contains all spreadsheet rows for determining group member will be updated or added
     *
     * @var array|null
     */
    private static ?array $sheetDocument = null;

    /**
     * Common details about spreadsheet document
     *
     * @var array|null
     */
    private static ?array $spreadSheetDetails = null;

    /**
     * Represents time when access token will expire
     *
     * @var Carbon|null
     */
    private static ?Carbon $tokenExpiration = null;

    /**
     * Resets the caching variables on the load
     */
    public function __construct()
    {
        parent::__construct();
        self::$sheetDocument = null;
        self::$spreadSheetDetails = null;
        self::$tokenExpiration = null;
    }

    /**
     * Subscribes an individual group member to the mailing list for this marketing service
     *
     * @param GroupMembers $groupMember
     *          The group member that will be subscribed to this marketing platform
     *
     * @throws GuzzleException|RequestException
     *      if there is a problem connecting with the marketing service
     * @throws InvalidStateException
     *      if there is a problem with the group member data that will be sent to the marketing service
     * @throws ColumnLimitExceededException
     *      if google sheet document column limit exceeded above supported range
     */
    public static function subscribe(GroupMembers $groupMember): void
    {
        $apiInfo = static::getApiInfo($groupMember->group_id);
        $sheetId = static::getSheetId($apiInfo->sheetURL);
        $columnHeaders = static::addColumnHeader($sheetId, $apiInfo->token);
        $sheetDocument = static::getSheetDocument($sheetId, $apiInfo->token);

        [$memberToAdd, $memberToUpdate] = static::getMembersForAddAndUpdate(
            GroupMembers::where('id', $groupMember->id)->get(),
            $sheetDocument,
            $columnHeaders
        );

        if ($memberToAdd) {
            $response = static::bulkAddGroupMembers($memberToAdd, $columnHeaders, $sheetId);
        } else {
            $response = static::bulkUpdateGroupMembers($memberToUpdate, $columnHeaders, $sheetId);
        }

        if (!static::isSuccessResponseCode($response->status())) {
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    $response->status(),
                    null,
                    $groupMember->id
                )
            );
        }
    }

    /**
     * Gets the info necessary to access the Google API
     *
     * @param int $facebookGroupId
     *          The Groupkit Facebook group ID associated with this mailing list
     *
     * @return object A valid Aweber API access information
     *
     * @throws GuzzleException  if there is an error connecting to GoogleApis
     * @throws Exception If there is an unknown error after connecting with GoogleApis
     */
    protected static function getApiInfo(int $facebookGroupId): object
    {
        return static::getRefreshedApiInfo(parent::getApiInfo($facebookGroupId), $facebookGroupId);
    }

    /**
     * Gets the cached API information if it exists and if the token is not expired, otherwise - fresh API access information.
     * If the token has expired, an attempt will be made for a new one with this new data being updated in the database.
     *
     * @param object $currentApiInfo
     *          Current credentials needed to login to the Google API
     * @param int $facebookGroupId
     *          The Groupkit Facebook group ID associated with this mailing list
     *
     * @return object Valid Google API access information
     *
     * @throws GuzzleException  if there is an error connecting to the Google API
     * @throws Exception If there is an unknown error after connecting with the Google API
     */
    private static function getRefreshedApiInfo(object $currentApiInfo, int $facebookGroupId): object
    {
        if (static::$tokenExpiration && !static::$tokenExpiration->isPast()) {
            return $currentApiInfo;
        }

        $client = new Client(['verify' => false]);
        $googleApiEndPoints = config('const');

        if ($currentApiInfo->token) {
            # Since we have a token, we first check that it has not expired
            $response = $client->get(
                $googleApiEndPoints['GOOGLE_API_URL_TOKEN_INFO'] . $currentApiInfo->token,
                ['http_errors' => false],
            );

            if (static::isSuccessResponseCode($response->getStatusCode())) {
                $tokenInfo = json_decode($response->getBody());

                if ($tokenInfo->expires_in) {
                    # This represents seconds left until expiration, so the current credentials are still good
                    static::$tokenExpiration = now()->addSeconds($tokenInfo->expires_in);

                    return $currentApiInfo;
                }
            }
        }

        if (!@$currentApiInfo->refreshToken) {
            # We don't have a non-expired token, nor do we have a refresh token, so we have to throw an error
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['EXPIRED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['EXPIRED'],
                    $response->getStatusCode(),
                    null,
                    null,
                    null,
                    [self::FACEBOOK_GROUP_ID => $facebookGroupId]
                )
            );
        }

        # Finally, we attempt to use the refresh token to get a new access token and then save it
        $body = [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'refresh_token' => $currentApiInfo->refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $response = $client->post(
            $googleApiEndPoints['GOOGLE_API_URL_TOKEN'],
            [
                'json' => $body,
                'http_errors' => false,
            ],
        );

        if (!static::isSuccessResponseCode($response->getStatusCode())) {
            # retrieving the new access token failed, likely due to an expired refresh token
            throw new InvalidStateException(
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['EXPIRED'],
                    $response->getStatusCode(),
                    null,
                    null,
                    null,
                    [self::FACEBOOK_GROUP_ID => $facebookGroupId]
                )
            );
        }

        $responseData = json_decode($response->getBody());
        $currentApiInfo->token = $responseData->access_token;

        $responder = AutoResponder::where('group_id', $facebookGroupId)->first();
        $responder->responder_json = json_encode($currentApiInfo);
        $responder->save();

        return $currentApiInfo;
    }

    /**
     * getSheetConstants returns sheet name with start range & end range.
     *
     * @param string $sheetId of the Google Sheet document that the record will be sent to
     * @param string $token represents access token for authentication with google API
     *
     * @return string for generating dynamic google sheet range.
     *
     * @throws RequestException if there is an error connecting to GoogleApis
     */
    public static function getSheetConstants(string $sheetId, string $token): string
    {
        $spreadSheetDetails = static::getSpreadSheetDetails($sheetId, $token);

        return $spreadSheetDetails['sheetName'] .
            config('const.SHEET_RANGE.START_RANGE') . ':' .
            config('const.SHEET_RANGE.END_RANGE');
    }

    /**
     * Checks if expected column headers exist in google sheet.
     * If they do not exist, then it updates column headers, otherwise does nothing
     * and returns all columns headers & column addresses .
     *
     * @param string $sheetId of the Google Sheet document that will be searched
     * @param string $token represents access token for authentication with google API
     *
     * @return array returns columns headers of google sheet
     *
     * @throws GuzzleException if there is an error connecting to the Google API
     * @throws Exception If there is an unknown error after connecting with GoogleApis
     * @throws ColumnLimitExceededException if google sheet document column limit exceeded above supported range
     */
    public static function addColumnHeader(string $sheetId, string $token): array
    {
        $sheetHeaders = static::returnGoogleSheetsColumnsHeaders($sheetId, $token);

        $configHeaders = config('const.COLUMNS_HEADERS');

        /**
         * merging google sheets columns and configHeaders columns
         * to find out missing columns which are not in google sheet
         */
        $missingColumns = [];
        foreach ($configHeaders as $configHeader) {
            if (!in_array($configHeader, $sheetHeaders)) {
                array_push($missingColumns, $configHeader);
            }
        }
        /**
         * If middle columns are empty then first filling missing columns header there.
         */
        foreach ($missingColumns as $missingColumn) {
            foreach ($sheetHeaders as $sheetHeaderKey => $sheetHeader) {
                if ($sheetHeader == "") {
                    $sheetHeaders[$sheetHeaderKey] = $missingColumn;
                    break;
                }
            }
        }

        //Adding default missing columns of constants which are not available in google sheet
        foreach ($configHeaders as $configHeader) {
            if (!in_array($configHeader, $sheetHeaders)) {
                array_push($sheetHeaders, $configHeader);
            }
        }
        $totalSizeOfSheetHeader = sizeof($sheetHeaders);
        $newColumnsPositionInDigit = $totalSizeOfSheetHeader + 1;

        $newColumnsPositionInRange = static::getColumnsHeadersEndRange($newColumnsPositionInDigit);
        try {
            $spreadSheetDetails = static::getSpreadSheetDetails($sheetId, $token);

            $constantSheetRange = $spreadSheetDetails['sheetName'] .
                config('const.SHEET_RANGE.START_RANGE') . '1' . ':' . $newColumnsPositionInRange;

            $batchUpdateUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values"
                . ":batchUpdate?valueInputOption=USER_ENTERED&access_token={$token}";

            $bodyParam = [
                'data' => [
                    [
                        "majorDimension" => "ROWS",
                        "range" => $constantSheetRange,
                        "values" => [$sheetHeaders]
                    ],
                ], "valueInputOption" => "USER_ENTERED",
            ];

            $client = new Client(['verify' => false]);

            $client->post($batchUpdateUrl, ['json' => $bodyParam]);
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());
        }
        return [
            'columnHeaders' => $sheetHeaders,
        ];
    }

    /**
     * Gets total count of columns of sheet and based on total columns header range gets occupied columns header
     * and returns columns headers.
     *
     * @param string $sheetId of the Google Sheet document that will be searched
     * @param string $token represents access token for authentication with google API
     *
     * @return array contains columns headers names.
     *
     * @throws GuzzleException  if there is an error connecting to GoogleApis
     * @throws Exception while google sheet having columns greater than AZ range
     * @throws ColumnLimitExceededException if google sheet document column limit exceeded above supported range
     */
    public static function returnGoogleSheetsColumnsHeaders(string $sheetId, string $token): array
    {
        $sheetStartRange = 'A1';

        $configHeaders = config('const.COLUMNS_HEADERS');

        $constantSheetRange = GoogleSheetService::getSheetConstants($sheetId, $token);

        $spreadSheetDetails = static::getSpreadSheetDetails($sheetId, $token);

        $columnMaxRange = static::getColumnsHeadersLocation($spreadSheetDetails['totalColumnCount'] - 1);

        if ($columnMaxRange) {
            $sheetEndRange = static::getColumnsHeadersEndRange($spreadSheetDetails['totalColumnCount']);

            $defaultGoogleSheetRange = $sheetStartRange . ':' . $sheetEndRange;

            $customColumnsHeaders = static::getCustomColumnsHeaders($sheetId, $defaultGoogleSheetRange, $token);
            if (empty($customColumnsHeaders)) {
                //appending all columns headers as there were no columns found (sheet is new.)
                $body = [
                    'range'  => $constantSheetRange,
                    'values' => [array_values($configHeaders)],
                ];
                try {
                    $updateColumnLabel = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/"
                        . "{$constantSheetRange}:append?valueInputOption=USER_ENTERED&access_token={$token}";

                    $client = new Client(['verify' => false]);

                    $responseData = $client->post($updateColumnLabel, ['json' => $body]);

                    $returnData = json_decode($responseData->getBody())->updates->updatedColumns;

                    if ($returnData) {
                        $customColumnsHeaders = static::getCustomColumnsHeaders(
                            $sheetId,
                            $defaultGoogleSheetRange,
                            $token
                        );
                    }
                } catch (Exception $e) {
                    Bugsnag::notifyException($e);
                    logger()->info($e->getMessage());
                }
            }
            return $customColumnsHeaders;
        } else {
            throw new ColumnLimitExceededException();
        }
    }

    /**
     * Converting total number of columns count to column header range Eg: 1 to A, 52 to AZ etc.
     * and binding 1 to generate column address Eg:(A1)
     * max limit to cover columns are AZ, can be change.
     *
     * @param int $totalNumberOfColumnsInSheet contains total number of columns header in sheet
     *
     * @return string $sheetEndRange contains new columns header range
     */
    public static function getColumnsHeadersEndRange(int $totalNumberOfColumnsInSheet): string
    {
        $lastColumnInBound = config('const.SHEET_RANGE.MAX_RANGE'); # column name limit in A1 notation (e.g. 'AZ')
        $firstColumnOutOfBound = ++$lastColumnInBound;
        $sheetEndRange = '';
        $columnCounter = 1;

        for ($column = "A"; $column != $firstColumnOutOfBound; $column++) {
            if ($totalNumberOfColumnsInSheet == $columnCounter) {
                $sheetEndRange = $column . '1';
            }
            $columnCounter++;
        }
        return $sheetEndRange;
    }

    /**
     * Gets all occupied columns headers of sheet and returns columns headers if found otherwise return empty array.
     *
     * @param string $sheetId of the Google Sheet document that will be searched
     * @param string $defaultGoogleSheetRange represents address of last column header
     * @param string $token represents access token for authentication with google API
     *
     * @return array contains columns headers names or empty array.
     *
     * @throws GuzzleException  if there is an error connecting to GoogleApis
     * @throws Exception If there is an unknown error after connecting with GoogleApis
     */
    public static function getCustomColumnsHeaders(
        string $sheetId,
        string $defaultGoogleSheetRange,
        string $token
    ): array {
        try {
            $sheetHeadersUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}/values/"
                . "{$defaultGoogleSheetRange}?access_token={$token}";

            $sheetHeadersClient = new Client(['verify' => false]);

            $sheetHeadersResult = $sheetHeadersClient->get($sheetHeadersUrl);

            if (isset(json_decode($sheetHeadersResult->getBody())->values)) {
                return json_decode($sheetHeadersResult->getBody())->values[0];
            }
            return [];
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            logger()->info($e->getMessage());
        }
    }

    /**
     * Converting total number of columns count to column header range Eg: 0 to A, 51 to AZ etc.
     * max limit to cover columns are AZ, can be change.
     *
     * @param int $columnHeaderNumber contains total number of columns header in sheet
     *
     * @return string $columnLocation contains new columns header range
     */
    public static function getColumnsHeadersLocation(int $columnHeaderNumber): string
    {
        $lastColumnInBound = config('const.SHEET_RANGE.MAX_RANGE'); # column name limit in A1 notation (e.g. 'AZ')
        $firstColumnOutOfBound = ++$lastColumnInBound;
        $columnLocation = '';
        $columnCounter = 0;

        for ($column = "A"; $column != $firstColumnOutOfBound; $column++) {
            if ($columnHeaderNumber == $columnCounter) {
                $columnLocation = $column;
            }
            $columnCounter++;
        }
        return $columnLocation;
    }

    /**
     * Gets sheet name, all columns & rows count of google sheet which used to get a range of columns.
     *
     * @param string $sheetId of the Google Sheet document that will be searched
     * @param string $token represents access token for authentication with Google API
     *
     * @return array contains sheetName, totalColumnCount, totalRowCount values
     *
     * @throws RequestException if there is an error connecting to GoogleApis
     */
    public static function getSpreadSheetDetails(string $sheetId, string $token): array
    {
        if (static::$spreadSheetDetails) {
            return static::$spreadSheetDetails;
        }

        $getSheetRangeUrl = "https://sheets.googleapis.com/v4/spreadsheets/{$sheetId}?access_token={$token}";
        $getSheetRangeResult = Http::get($getSheetRangeUrl);

        // Throw an exception if a client or server error occurred...
        $getSheetRangeResult->throw();

        $getSheetRangeResponse = $getSheetRangeResult->object();
        $totalNumberOfRowsAndColumnsCount = $getSheetRangeResponse->sheets[0]->properties->gridProperties ?? null;

        return static::$spreadSheetDetails = [
            'totalColumnCount' => $totalNumberOfRowsAndColumnsCount->columnCount ?? null,
            'totalRowCount' => $totalNumberOfRowsAndColumnsCount->rowCount ?? null,
            'sheetName' => $getSheetRangeResponse->sheets[0]->properties->title . '!',
        ];
    }

    /**
     * Adds/updates provided group members to the Google Sheet document
     * If there is no group members or group members belong to more than one group, stops the request
     *
     * @param Collection $groupMembers to be added/updated to the integration
     * @param bool $requestIsFromExtension true if group members comes from Google Chrome extension, otherwise false
     *
     * @throws GroupLimitExceededException if count of groups exceeded supported value
     * @throws NoMembersToSendException if provided group members are empty
     */
    public static function subscribeAll(Collection $groupMembers, bool $requestIsFromExtension): void
    {
        static::validateBeforeSubscribeAll($groupMembers);

        try {
            $apiInfo = static::getApiInfo($groupMembers->first()->group_id);
            $sheetId = static::getSheetId($apiInfo->sheetURL);
            $columnHeaders = static::addColumnHeader($sheetId, $apiInfo->token);
            $sheetDocument = static::getSheetDocument($sheetId, $apiInfo->token);

            [$membersToAdd, $membersToUpdate] = static::getMembersForAddAndUpdate(
                $groupMembers,
                $sheetDocument,
                $columnHeaders
            );
        } catch (ColumnLimitExceededException $e) {
            GroupMembers::whereIn('id', $groupMembers->pluck('id'))
                ->update(['respond_status' => $e->getResponseStatus()]);
            # known issue doesn't need to be logged
            return;
        } catch (InvalidStateException $e) {
            GroupMembers::whereIn('id', $groupMembers->pluck('id'))
                ->update(['respond_status' => $e->getMessage()]);
            Bugsnag::notifyException($e);
            return;
        } catch (Exception | GuzzleException $e) {
            GroupMembers::whereIn('id', $groupMembers->pluck('id'))
                ->update(['respond_status' => GroupMembers::RESPONSE_STATUSES['ERROR']]);
            Bugsnag::notifyException($e);
            return;
        }

        static::updateMembers($membersToUpdate, $columnHeaders, $sheetId, $requestIsFromExtension);
        static::addMembers($membersToAdd, $columnHeaders, $sheetId, $requestIsFromExtension);
    }

    /**
     * Returns sheet id from the provided sheet URL if is found, otherwise throws {@see InvalidStateException}
     *
     * @param string $sheetUrl for determining sheet id
     *
     * @return string containing google sheet id
     *
     * @throws InvalidStateException if sheet id has not found in the provided sheet URL
     */
    private static function getSheetId(string $sheetUrl): string
    {
        if (!preg_match('~/spreadsheets/d/([a-zA-Z0-9-_]+)~', $sheetUrl, $matches)) {
            # The sheet ID was not found
            throw new InvalidStateException(
                GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                self::formatExceptionDetails(
                    GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
                    HttpFoundationResponse::HTTP_NOT_IMPLEMENTED,
                    null,
                    null,
                    null,
                    [self::GOOGLE_SHEET_URL => $sheetUrl]
                )
            );
        }

        return $matches[1];
    }

    /**
     * Gets cached sheet document if exists, otherwise gets it from Google Sheet API
     *
     * @param string $sheetId for determining google sheet name
     * @param string $token for Google Sheet API authorization
     *
     * @return array containing google sheet document rows
     *
     * @throws RequestException if there is an error connecting to GoogleApis
     */
    private static function getSheetDocument(string $sheetId, string $token): array
    {
        if (static::$sheetDocument) {
            return static::$sheetDocument;
        }

        $spreadSheetDetails = static::getSpreadSheetDetails($sheetId, $token);
        $columnEndPosition = static::getColumnsHeadersLocation($spreadSheetDetails['totalColumnCount'] - 1);

        $sheetRange = $spreadSheetDetails['sheetName'] .
            config('const.SHEET_RANGE.START_RANGE') . ':' . $columnEndPosition;

        $readSheetUrl = sprintf(
            "https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?access_token=%s",
            $sheetId,
            $sheetRange,
            $token
        );

        $response = Http::get($readSheetUrl);

        // Throw an exception if a client or server error occurred...
        $response->throw();

        $sheetDocument = $response->object()->values;

        return static::$sheetDocument = $sheetDocument;
    }

    /**
     * Returns string range for updating group member on row index
     *
     * @param int $facebookGroupId for getting {@see \App\Services\MarketingAutomation\GoogleSheetService::getApiInfo}
     * @param int $rowIndex in Google Sheet document where found matched group member
     * @param array $columnHeaders of the Google Sheet document
     *
     * @return string containing formatted string for updating row range
     *
     * @throws GuzzleException|RequestException if there is an error connecting to GoogleApis
     * @throws InvalidStateException if sheet id has not found in the provided sheet URL
     */
    private static function getRange(int $facebookGroupId, int $rowIndex, array $columnHeaders): string
    {
        $sheetEndRange = static::getColumnsHeadersLocation(sizeof($columnHeaders['columnHeaders']));
        $apiInfo = static::getApiInfo($facebookGroupId);
        $sheetId = static::getSheetId($apiInfo->sheetURL);
        $spreadSheetDetails = static::getSpreadSheetDetails($sheetId, $apiInfo->token);

        return sprintf(
            "%s%s%d:%s%d",
            $spreadSheetDetails['sheetName'],
            config('const.SHEET_RANGE.START_RANGE'),
            $rowIndex,
            $sheetEndRange,
            $rowIndex
        );
    }

    /**
     * Filters which group members will be added/updated in the Google Sheet document
     *
     * @param Collection $groupMembers to be filtered for adding/updating
     * @param array $sheetDocument data for determining which group members should be updated
     * @param array $columnHeaders of the Google Sheet document
     *
     * @return array[] including members to add in the Google Sheet and members to update in the Google Sheet
     */
    private static function getMembersForAddAndUpdate(
        Collection $groupMembers,
        array $sheetDocument,
        array $columnHeaders
    ): array {
        $userIdColumnPosition = array_search(config('const.COLUMNS_HEADERS')['fb_id'], $sheetDocument[0]);
        $existingUserFbIds = array_column($sheetDocument, $userIdColumnPosition);
        array_shift($existingUserFbIds); // remove header

        /**
         * @var Collection $membersToAdd in the GoogleSheet document
         * @var Collection $membersToUpdate in the GoogleSheet document
         */
        [$membersToAdd, $membersToUpdate] = $groupMembers->partition(function ($groupMember) use ($existingUserFbIds) {
            return !in_array($groupMember->fb_id, $existingUserFbIds);
        });

        return [
            $membersToAdd->all(),
            $membersToUpdate->map(
                function ($groupMember) use ($existingUserFbIds, $columnHeaders) {
                    return [
                        'majorDimension' => 'ROWS',
                        'range' => static::getRange(
                            $groupMember->group_id,
                            /** One for header one for non-zero indexed system */
                            array_search($groupMember->fb_id, $existingUserFbIds) + 2,
                            $columnHeaders
                        ),
                        'values' => $groupMember,
                    ];
                }
            )->all()
        ];
    }

    /**
     * Appends group members in the GoogleSheet document via appendCells request
     * @link https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/request#appendcellsrequest
     *
     * @param array $groupMembers to be added in the Google Sheet document
     * @param array $columnHeaders of the Google Sheet document
     * @param string $sheetId of the Google Sheet document where group members will be added
     *
     * @return Response containing response from the Google Sheet API
     *
     * @throws GuzzleException if there is an error connecting to Google API
     */
    private static function bulkAddGroupMembers(
        array $groupMembers,
        array $columnHeaders,
        string $sheetId
    ): Response {
        $apiInfo = static::getApiInfo($groupMembers[array_key_first($groupMembers)]->group_id);

        $requests = [
            'requests' => [
                'appendCells' => [
                    'sheetId' => 0,
                    'rows' => static::prepareMembers($groupMembers, $columnHeaders, true),
                    'fields' => '*',
                ],
            ],
        ];

        $batchUpdateUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate?access_token=%s',
            $sheetId,
            $apiInfo->token,
        );

        return Http::post($batchUpdateUrl, $requests);
    }

    /**
     * Sends group members to the Google Sheet API to be updated in the document
     *
     * @param array $groupMembers to be updated in the Google Sheet document
     * @param array $columnHeaders of the Google Sheet document
     * @param string $sheetId of the Google Sheet document where group members will be added
     *
     * @return Response containing response from the Google Sheet API
     *
     * @throws GuzzleException if there is an error connecting to Google API
     */
    private static function bulkUpdateGroupMembers(
        array $groupMembers,
        array $columnHeaders,
        string $sheetId
    ): Response {
        $apiInfo = static::getApiInfo(array_values($groupMembers)[0]['values']->group_id);

        $groupMembersFormatted = array_map(function ($groupMember) use ($columnHeaders) {
            return [
                'majorDimension' => $groupMember['majorDimension'],
                'range' => $groupMember['range'],
                'values' => static::prepareMembers([$groupMember['values']], $columnHeaders),
            ];
        }, array_values($groupMembers));

        $batchUpdateUrl = sprintf(
            "https://sheets.googleapis.com/v4/spreadsheets/%s/values"
            . ":batchUpdate?valueInputOption=USER_ENTERED&access_token=%s",
            $sheetId,
            $apiInfo->token
        );

        $bodyParam = [
            'data' => [$groupMembersFormatted],
            'valueInputOption' => 'USER_ENTERED',
        ];

        return Http::post($batchUpdateUrl, $bodyParam);
    }

    /**
     * Formats provided group members according to the Google Sheet columns
     *
     * @param array $groupMembers that will be formatted in Google Sheet column array
     * @param array $columnHeaders of the Google Sheet document
     * @param bool $isForAppend true if members needs to be formatted for appendCells request,
     *                          otherwise, false. False by default.
     *
     * @return array of formatted group members
     *
     * @throws GuzzleException if there is an error connecting to the Google API
     */
    private static function prepareMembers(
        array $groupMembers,
        array $columnHeaders,
        bool $isForAppend = false
    ): array {
        $configHeaders = config('const.COLUMNS_HEADERS');
        $formattedGroupMembers = [];
        $apiInfo = static::getApiInfo($groupMembers[array_key_first($groupMembers)]->group_id);

        $userDateAddTimeFormat = static::DATE_FORMATS[$apiInfo->dateAddTimeFormat];
        $dateTimeFormat = static::getDateAddFormat($apiInfo, $userDateAddTimeFormat);

        foreach ($groupMembers as $groupMember) {
            $formattedMember = [];
            foreach ($columnHeaders['columnHeaders'] as $columnHeader) {
                switch ($columnHeader) {
                    case $configHeaders['date_add_time']:
                        if (
                            $groupMember->approvedBy
                            && $groupMember->approvedBy->id !== $apiInfo->owner->id
                            && $apiInfo->owner->timezone
                        ) { #if group member is approved by team member we are applying owner timezone
                            /**
                             * we need to first convert back to the default timezone because of group member accessor
                             * @see \App\GroupMembers::getDateAddTimeAttribute
                             */
                            $dateAddTime = Carbon::createFromFormat(
                                'm-d-Y G:i:s',
                                $groupMember->date_add_time,
                                $groupMember->approvedBy->timezone
                            )
                                ->setTimezone($apiInfo->owner->timezone);
                        } else {
                            $dateAddTime = Carbon::createFromFormat(
                                'm-d-Y G:i:s',
                                $groupMember->date_add_time,
                                $apiInfo->owner->timezone
                            );
                        }

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend(
                                [
                                    static::EXTENDED_VALUE['numberValue'] =>
                                        floatval(25569 + ($dateAddTime->timestamp / 86400))
                                ],
                                [
                                    static::CELL_FORMAT['numberFormat'] => [
                                        static::NUMBER_FORMAT['type'] => static::NUMBER_FORMAT_TYPE['DATE_TIME'],
                                        static::NUMBER_FORMAT['pattern'] => $dateTimeFormat,
                                    ],
                                    static::CELL_FORMAT['horizontalAlignment'] => static::HORIZONTAL_ALIGN['LEFT'],
                                ]
                            )
                            : $dateAddTime->format(static::DEFAULT_DATE_TIME_FORMAT);
                        break;
                    case $configHeaders['f_name']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->f_name])
                            : $groupMember->f_name;
                        break;
                    case $configHeaders['l_name']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->l_name])
                            : $groupMember->l_name;
                        break;
                    case $configHeaders['email']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->email])
                            : $groupMember->email;
                        break;
                    case $configHeaders['fb_id']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend(
                                [static::EXTENDED_VALUE['numberValue'] => $groupMember->fb_id],
                                [static::CELL_FORMAT['horizontalAlignment'] => static::HORIZONTAL_ALIGN['LEFT']]
                            )
                            : $groupMember->fb_id;
                        break;
                    case $configHeaders['a1']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->a1])
                            : $groupMember->a1;
                        break;
                    case $configHeaders['a2']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->a2])
                            : $groupMember->a2;
                        break;
                    case $configHeaders['a3']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->a3])
                            : $groupMember->a3;
                        break;
                    case $configHeaders['messenger_url']:
                        $messengerUrl = 'https://www.messenger.com/t/' . $groupMember->fb_id;

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend(
                                [static::EXTENDED_VALUE['stringValue'] => $messengerUrl],
                                null,
                                [
                                    static::TEXT_FORMAT_RUN['format'] => [
                                        static::TEXT_FORMAT['link'] => [
                                            static::LINK['uri'] => $messengerUrl,
                                        ],
                                    ],
                                ]
                            )
                            : $messengerUrl;
                        break;
                    case $configHeaders['approved_by']:
                        $approvedBy = $groupMember->approvedBy ? $groupMember->approvedBy->name : '';

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $approvedBy])
                            : $approvedBy;
                        break;
                    case $configHeaders['invited_by']:
                        $invitedBy = $groupMember->invited_by ? $groupMember->invited_by->getFullName() : '';

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $invitedBy])
                            : $invitedBy;
                        break;
                    case $configHeaders['lives_in']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->lives_in])
                            : $groupMember->lives_in;
                        break;
                    case $configHeaders['agreed_group_rules']:
                        $agreedGroupRules = $groupMember->agreed_group_rules ? 'Yes' : 'No';

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $agreedGroupRules])
                            : $agreedGroupRules;
                        break;
                    case $configHeaders['id']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['numberValue'] => $groupMember->id])
                            : $groupMember->id;
                        break;
                    case $configHeaders['tags']:
                        $tags = $groupMember->tags->isNotEmpty()
                            ? implode(', ', $groupMember->tags->pluck('label')->toArray())
                            : '';

                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $tags])
                            : $tags;
                        break;
                    case $configHeaders['notes']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => $groupMember->notes])
                            : $groupMember->notes;
                        break;
                    case $configHeaders['phone_number']:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend(
                                [static::EXTENDED_VALUE['stringValue'] => $groupMember->phone_number]
                            )
                            : $groupMember->phone_number;
                        break;
                    default:
                        $formattedMember[] = $isForAppend
                            ? self::prepareForAppend([static::EXTENDED_VALUE['stringValue'] => null])
                            : []; // avoid updating data of those columns which are not ours
                }
            }

            if (!$isForAppend) {
                /* Convert all nullable fields to empty string */
                foreach ($formattedMember as $index => $value) {
                    $formattedMember[$index] = is_null($value) ? '' : $value;
                }
            }

            $formattedGroupMembers[] = $isForAppend
                ? ['values' => $formattedMember]
                : $formattedMember;
        }

        return $formattedGroupMembers;
    }

    /**
     * Saves respond_status for group members from the bulkAddGroupMembers method response
     * @see \App\Services\MarketingAutomation\GoogleSheetService::bulkAddGroupMembers
     *
     * @param array $groupMembers to be added in the Google Sheet document
     * @param array $columnHeaders of the Google Sheet document
     * @param string $sheetId of the Google Sheet document where group members will be added
     * @param bool $requestIsFromExtension true if group members comes from Google Chrome extension, otherwise false
     */
    private static function addMembers(
        array $groupMembers,
        array $columnHeaders,
        string $sheetId,
        bool $requestIsFromExtension
    ): void {
        if (!$groupMembers) {
            return;
        }

        try {
            $response = static::bulkAddGroupMembers($groupMembers, $columnHeaders, $sheetId);
            $respondStatus = static::isSuccessResponseCode($response->status())
                ? GroupMembers::RESPONSE_STATUSES['ADDED']
                : GroupMembers::RESPONSE_STATUSES['NOT_ADDED'];
        } catch (GuzzleException $exception) {
            $respondStatus = GroupMembers::RESPONSE_STATUSES['ERROR'];
        }

        $updateData = ['respond_status' => $respondStatus];
        if ($requestIsFromExtension) {
            $updateData['respond_date_time'] = now();
        }

        GroupMembers::whereIn('id', array_column($groupMembers, 'id'))->update($updateData);
    }

    /**
     * Saves respond_status for group members from the bulkUpdateGroupMembers method response
     * @see \App\Services\MarketingAutomation\GoogleSheetService::bulkUpdateGroupMembers
     *
     * @param array $groupMembers to be updated in the Google Sheet document
     * @param array $columnHeaders of the Google Sheet document
     * @param string $sheetId of the Google Sheet document where group members will be added
     * @param bool $requestIsFromExtension true if group members come from Google Chrome extension, otherwise false
     */
    private static function updateMembers(
        array $groupMembers,
        array $columnHeaders,
        string $sheetId,
        bool $requestIsFromExtension
    ): void {
        if (!$groupMembers) {
            return;
        }

        try {
            $response = static::bulkUpdateGroupMembers($groupMembers, $columnHeaders, $sheetId);
            $respondStatus = static::isSuccessResponseCode($response->status())
                ? GroupMembers::RESPONSE_STATUSES['ADDED']
                : GroupMembers::RESPONSE_STATUSES['NOT_ADDED'];
        } catch (GuzzleException $e) {
            Bugsnag::notifyException($e);
            $respondStatus = GroupMembers::RESPONSE_STATUSES['ERROR'];
        }

        $updateData = ['respond_status' => $respondStatus];
        if ($requestIsFromExtension) {
            $updateData['respond_date_time'] = now();
        }

        GroupMembers::whereIn('id', array_column(array_column($groupMembers, 'values'), 'id'))->update($updateData);
    }

    /**
     * Updates format to provided $dateFormat for all rows of the date column in the existing document
     *
     * @param int $groupId which members should be updated
     * @param string $dateFormat which sheet document column will update to
     *
     * @throws GuzzleException if there is an error connecting to the Google API
     * @throws InvalidStateException if the Google sheet is not found or if we are missing API connection info
     * @throws RequestException if there is an error connecting to the Google API
     */
    public static function updateExistingDocumentDateColumn(int $groupId, string $dateFormat)
    {
        $apiInfo = static::getApiInfo($groupId);

        $sheetId = static::getSheetId($apiInfo->sheetURL);
        $sheetDocument = static::getSheetDocument($sheetId, $apiInfo->token);
        $sheetHeaders = $sheetDocument[0];

        $dateColumnIndex = array_search(config('const.COLUMNS_HEADERS.date_add_time'), $sheetHeaders);
        $dateFormat = static::getDateAddFormat($apiInfo, $dateFormat);

        $bodyParam = [
            'requests' => [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => 0,
                        'startRowIndex' => 1,
                        'endRowIndex' => count($sheetDocument),
                        'startColumnIndex' => $dateColumnIndex,
                        'endColumnIndex' => $dateColumnIndex + 1,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            static::CELL_FORMAT['numberFormat'] => [
                                static::NUMBER_FORMAT['type'] => static::NUMBER_FORMAT_TYPE['DATE_TIME'],
                                static::NUMBER_FORMAT['pattern'] => $dateFormat,
                            ],
                            static::CELL_FORMAT['horizontalAlignment'] => static::HORIZONTAL_ALIGN['LEFT'],
                        ],
                    ],
                    'fields' => 'userEnteredFormat(numberFormat, horizontalAlignment)',
                ],
            ],
        ];

        $batchUpdateUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s:batchUpdate?access_token=%s',
            $sheetId,
            $apiInfo->token,
        );

        Http::post($batchUpdateUrl, $bodyParam);
    }

    /**
     * Normalizes date add time rows value.
     * Updates date add time format value for all rows in the existing document.
     *
     * @param int $groupId which members should be updated
     *
     * @throws GuzzleException if there is an error connecting to the Google API
     * @throws InvalidStateException if the Google sheet is not found or if we are missing API connection info
     * @throws RequestException if there is an error connecting to the Google API
     */
    public function updateExistingDateAddedValue(int $groupId)
    {
        $apiInfo = static::getApiInfo($groupId);

        $sheetId = static::getSheetId($apiInfo->sheetURL);
        $sheetDocument = static::getSheetDocument($sheetId, $apiInfo->token);

        $dateColumnToUpdate = array_map(function ($sheetDocument) {
            if (Carbon::hasFormat($sheetDocument[0], 'c')) {
                $formattedDate = Carbon::createFromFormat('c', $sheetDocument[0])
                    ->format(static::DEFAULT_DATE_TIME_FORMAT);
            } elseif (Carbon::hasFormat($sheetDocument[0], 'Y-j-m H:i:s')) {
                $formattedDate = Carbon::createFromFormat('Y-j-m H:i:s', $sheetDocument[0])
                    ->format(static::DEFAULT_DATE_TIME_FORMAT);
            } else {
                $formattedDate = $sheetDocument[0];
            }

            return [$formattedDate];
        }, $sheetDocument);

        $range = 'Sheet1!A2:A' . (count($dateColumnToUpdate) + 1);
        $batchUpdateUrl = sprintf(
            'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?valueInputOption=USER_ENTERED&access_token=%s',
            $sheetId,
            $range,
            $apiInfo->token,
        );

        $bodyParam = [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $dateColumnToUpdate,
        ];

        Http::put($batchUpdateUrl, $bodyParam);
    }

    /**
     * Adds owners timezone to the provided format if ISO-8601 format is provided
     *
     * @param object $apiInfo containing owner and api information for the integration
     * @param string $dateTimeFormat selected by the user
     *
     * @return string containing full date add format for the integration
     */
    private static function getDateAddFormat(object $apiInfo, string $dateTimeFormat): string
    {
        if (static::DATE_FORMATS['c'] === $dateTimeFormat) {
            $timeZoneOffset = Carbon::parse($apiInfo->owner->timezone)->getOffsetString();
            $dateTimeFormat = $dateTimeFormat . "\"$timeZoneOffset\"";
        }

        return $dateTimeFormat;
    }

    /**
     * Prepares provided group member value ($userEnteredValue) for appendCells request
     *
     * @param array $userEnteredValue include type as key,
     *                                and group member field {@see GroupMembers::$fillable} value as value
     * @param array|null $userEnteredFormat represents the format of the cell
     * @param array|null $textFormatRuns Runs of rich text applied to subsections of the cell.
     *                                   Runs are only valid on user entered strings, not formulas, bools, or numbers.
     *                                   Properties of a run start at a specific index in the text
     *                                   and continue until the next run.
     *                                   Runs will inherit the properties of the cell unless explicitly changed.
     *                                   When writing, the new runs will overwrite any prior runs.
     *                                   When writing a new userEnteredValue , previous runs are erased.
     *
     *
     * @return string[][] containing userEnteredValue, userEnteredFormat if is provided
     *                    and textFormatRuns if is provided
     */
    private static function prepareForAppend(
        array $userEnteredValue,
        ?array $userEnteredFormat = null,
        ?array $textFormatRuns = null
    ): array {
        $formattedMemberData = [
            'userEnteredValue' => $userEnteredValue,
        ];

        if ($userEnteredFormat) {
            $formattedMemberData['userEnteredFormat'] = $userEnteredFormat;
        }

        if ($textFormatRuns) {
            $formattedMemberData['textFormatRuns'] = $textFormatRuns;
        }

        return $formattedMemberData;
    }

    /**
     * Adds headers to the provided group sheet document
     *
     * @param int $groupId of the group on which integration headers will be added
     * @throws ColumnLimitExceededException if google sheet document column limit exceeded above supported range
     * @throws GuzzleException if there is a problem connecting with the marketing service
     * @throws InvalidStateException if there is a problem with the group member data that will be sent to the marketing service
     */
    public static function addHeaders(int $groupId): void
    {
        $apiInfo = static::getApiInfo($groupId);
        $sheetId = static::getSheetId($apiInfo->sheetURL);

        GoogleSheetService::addColumnHeader($sheetId, $apiInfo->token);
    }
}
