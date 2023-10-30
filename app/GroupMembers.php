<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Class GroupMembers represents the individual members of a Facebook group
 * @package App
 */
class GroupMembers extends AbstractModel
{
    use SoftDeletes;
    use HasFactory;

    /**
     * File path for creating group members csv files
     *
     * @var string
     */
    public const CSV_FILES_PATH = 'group-members/csv/';

    /**
     * Indicates key of the {@see \App\GroupMembers::RESPONSE_STATUSES} Error
     */
    public const RESPONSE_STATUS_ERROR = 'ERROR';

    /**
     * The possible response statuses in the "respond_status" ENUM field.
     * The keys are used as the values in the corresponding React component
     */
    public const RESPONSE_STATUSES = [
        "ERROR" => 'Error',
        "ADDED" => 'Added',
        "NOT_ADDED" => 'Not Added',
        "NO_EMAIL" => 'No Email',
        "FAILED_TAGS" => 'Tags Not Added',
        "EXPIRED" => 'Authorization Expired',
        "G_SHEET_COLUMN_LIMIT_EXCEEDED" => 'Column Limit Exceeded',
        'ACTIVE_CAMPAIGN_PAYMENT_ISSUE' => 'Request Could Not Be Processed Due To Payment Issues',
        'ACTIVE_CAMPAIGN_RATE_LIMIT_EXCEEDED' => 'The Request Could Not Be Processed Due To Rate Limit Being Exceeded',
        'ACTIVE_CAMPAIGN_RESOURCE_NOT_EXIST' => 'The Contact Does Not Exist In The ActiveCampaign System',
        'ACTIVE_CAMPAIGN_AUTHORIZATION_ISSUE' =>
            'Request Could Not Be Processed Due To Authorization Or Authentication Issue',
        'ACTIVE_CAMPAIGN_REQUEST_UNPROCESSABLE' =>
            'The Contact Data Was Invalid To Be Added Or Updated To The ActiveCampaign System',
        'INVALID_RESOURCE' => 'Invalid resource',
    ];

    /**
     * AWeber Response Type.
     */
    public const AWEBER_RESPONSE_TYPE = [
        'WEB_SERVICE_ERROR' => 'WebServiceError',
    ];

    /**
     * Represents possible integration statuses without integrations specific errors and general error status
     * @see \App\GroupMembers::RESPONSE_STATUSES
     *
     * @var array
     */
    public static array $integrationFilterStatuses = [
        GroupMembers::RESPONSE_STATUSES['ADDED'],
        GroupMembers::RESPONSE_STATUSES['NOT_ADDED'],
        GroupMembers::RESPONSE_STATUSES['NO_EMAIL'],
        GroupMembers::RESPONSE_STATUSES['FAILED_TAGS'],
    ];

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'group_members';

    /**
     * @var string[] Allowed values to be saved via the web interface/API
     */
    protected $fillable = [
        'a1',
        'a2',
        'a3',
        'date_add_time',
        'email',
        'f_name',
        'fb_id',  # Facebook Group ID
        'l_name',
        'notes',
        'respond_status',
        'respond_date_time',
        'user_id',  # Groupkit account owner ID
        'img',
        'agreed_group_rules',
        'phone_number',
    ];

    /**
    * The "booted" method of the model GroupMembers.  We currently use it to:
    *    - Guess the phone number based on answers when needed
    *
    * @return void
    */
    protected static function booted()
    {
        static::saving(function (GroupMembers $groupMember) {
            if ($groupMember->phone_number || $groupMember->isDirty('phone_number')) {
                // A phone number is already set or is being set.  We shouldn't try to override set phone numbers.
                return;
            }

            // Otherwise we try to guess it from the fields (a1,a2,a3)
            $groupMember->phone_number = array_values(preg_grep(
                '/^\+?([0-9]*)?[-.\s]?\(?([0-9]{3})\)?[-.\s]?([0-9]{3})[-.\s]?([0-9]{4})$/',
                [$groupMember->a1, $groupMember->a2, $groupMember->a3]
            ))[0] ?? '';
        });
    }

    /**
     * Gets all tags added to the group member
     *
     * @return BelongsToMany tags of the group member
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'group_members_tags',
            'group_member_id',
            'tag_id'
        );
    }

    /**
     * Gets formatted date add time field from the database for Eloquent calls
     *
     * @param ?string $value of the date add time field from the database
     *
     * @return string formatted date time field as `02-20-2020 17:26`
     */
    public function getDateAddTimeAttribute(?string $value)
    {
        $dateAddTime = Carbon::parse($value);

        if (auth()->user() && auth()->user()->timezone) {
            $dateAddTime->setTimezone(auth()->user()->timezone);
        } else if (($owner = User::whereId($this->user_id)->first())) {
            $dateAddTime->setTimezone($owner->timezone);
        }

        return $dateAddTime->format('m-d-Y G:i:s');
    }

    /**
     * One to one relationship between `users` table and `group_members` table
     *
     * @return BelongsTo returns approvedBy users details who approved members request.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * Gets the group member that invited the current member into the group
     *
     * @return HasOne relation to the inviter group member
     */
    public function invited_by(): HasOne
    {
        return $this->hasOne(
            GroupMembers::class,
            'id',
            'invited_by_member_id',
        );
    }

    /**
     * Returns first and last name concatenated
     *
     * @return string full member name
     */
    public function getFullName(): string
    {
        return trim(($this->f_name ?? '') . ' '. ($this->l_name ?? ''));
    }

    /**
     * Filters @see \App\GroupMembers with provided $request
     *
     * @param Request|array $request which contains or not parameters for filtering
     *
     * @return Builder with filtered parameters
     */
    public static function filterBy($request): Builder
    {
        $query = GroupMembers::query();

        if (is_array($request)) {
            $request = json_decode(json_encode($request), false);
            $currentUser = auth()->user();
        } else {
            $currentUser = $request->user();
        }

        if ($request->startDate) {
            // Start date was received converted to the customer time zone.
            // Convert it back to UTC, as stored in the DB, to match the date_add_time column in query.
            $startDate = Carbon::parse($request->startDate, $currentUser->timezone)
                ->setTimezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
            $query->where('date_add_time', '>=', $startDate);
        }

        if ($request->endDate) {
            // End date was received converted to the customer time zone.
            // Convert it back to UTC, as stored in the DB, to match the date_add_time column in query.
            $endDate = Carbon::parse($request->endDate, $currentUser->timezone)
                ->setTimezone(config('app.timezone'))
                ->endOfDay()
                ->format('Y-m-d H:i:s');
            $query->where('date_add_time', '<', $endDate);
        }

        if ($request->tags) {
            $tagIds = explode(',', $request->tags);
            $query->whereHas('tags', function ($tagsQuery) use ($tagIds) {
                $tagsQuery->whereIn('tags.id', $tagIds);
            }, '=', count($tagIds)); # forces members to have all tags specified in the filter request
        }

        if (
            $request->autoResponder
            && !in_array($request->autoResponder, ['all', GroupMembers::RESPONSE_STATUS_ERROR])
        ) {
            $query->where(
                'respond_status',
                GroupMembers::RESPONSE_STATUSES[$request->autoResponder]
            );
        }

        if ($request->autoResponder && $request->autoResponder === GroupMembers::RESPONSE_STATUS_ERROR) {
            $query->whereIn(
                'respond_status',
                array_filter(
                    GroupMembers::RESPONSE_STATUSES,
                    function ($responseStatus) {
                        return !in_array($responseStatus, GroupMembers::$integrationFilterStatuses);
                    } #get rows where respond_status contains any error
                )
            );
        }

        if ($searchTerm = $request->searchText) {
            $query->whereRaw("(CONCAT(`f_name`, ' ', `l_name`) LIKE ? OR fb_id = ? OR email = ?)",
                ["%$searchTerm%", $searchTerm, $searchTerm]
            );
        }

        if ($sort = $request->sort) {
            $query->reorder($sort['sortName'], $sort['sortOrder']);
        }

        return $query;
    }
}
