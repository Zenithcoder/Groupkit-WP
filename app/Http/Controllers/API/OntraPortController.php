<?php

namespace App\Http\Controllers\API;

use App\Services\MarketingAutomation\OntraPortService;
use GuzzleHttp\Exception\GuzzleException;

/**
 * The API end-point for maintaining OntraPort members data
 *
 * @package App\Http\Controllers\API
 */
class OntraPortController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'verifyCredentials' => [
            'app_id' => 'required',
            'app_key' => 'required',
        ],
    ];

    /**
     * Validates given credentials Eg : app_id,app_key
     *
     * @throws GuzzleException if there is a problem with calling the OntraPort API
     */
    public function verifyCredentials()
    {
        $response = app(OntraPortService::class)->verifyCredentials($this->request->app_key, $this->request->app_id);

        return response(
            [
                'message' => __($response['message']),
            ],
            $response['code'],
        );
    }
}
