<?php

namespace App\Http\Controllers\API;

use App\Services\MarketingAutomation\ConvertKitService;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class ConvertKitController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'index' => [
            'api_key' => 'required',
            'api_secret' => 'required',
        ],
    ];

    public function index()
    {
        try {
            /** List */
            $url = sprintf(
                '%sforms?api_key=%s',
                ConvertKitService::URL,
                $this->request->api_key
            );
            $client = new Client(['verify' => false]);
            $response = $client->get($url, []);

            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $list = json_decode($body)->forms;
            } else {
                $list = [];
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => [
                        'list' => $list,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid Request',
                    'data' => '',
                ]
            );
        }
    }
}
