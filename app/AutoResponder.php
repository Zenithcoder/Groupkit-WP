<?php

namespace App;

use App\Services\MarketingAutomation\ActiveCampaignService;
use App\Services\MarketingAutomation\AweberService;
use App\Services\MarketingAutomation\ConvertKitService;
use App\Services\MarketingAutomation\GetResponseService;
use App\Services\MarketingAutomation\GoHighLevelService;
use App\Services\MarketingAutomation\GoogleSheetService;
use App\Services\MarketingAutomation\KartraService;
use App\Services\MarketingAutomation\MailChimpService;
use App\Services\MarketingAutomation\MailerLiteService;
use App\Services\MarketingAutomation\OntraPortService;
use App\Services\MarketingAutomation\InfusionSoftService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AutoResponder extends Model
{
    use SoftDeletes;
    use HasFactory;

    /**
     * @var string[] List of database autoresponders as keys associated with the service class for handling them
     */
    public const SERVICE_TYPES = [
        'Aweber'         => AweberService::class,
        'ActiveCampaign' => ActiveCampaignService::class,
        'ConvertKit'     => ConvertKitService::class,
        'Getresponse'    => GetResponseService::class,  # TODO: Change this to 'GetResponse'
        'GoHighLevel'    => GoHighLevelService::class,
        'GoogleSheet'    => GoogleSheetService::class,
        'Kartra'         => KartraService::class,
        'MailChimp'      => MailChimpService::class,
        'Mailerlite'     => MailerLiteService::class, # TODO: Change this to 'MailerLite'
        'OntraPort'      => OntraPortService::class,
        'InfusionSoft'   => InfusionSoftService::class,
    ];

    protected $table = 'auto_responder';

    protected $fillable = [
        'user_id',
    ];

    /**
     * Returns connected Facebook Group to the autoresponder
     *
     * @return BelongsTo Facebook group
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(FacebookGroups::class, 'group_id');
    }
}
