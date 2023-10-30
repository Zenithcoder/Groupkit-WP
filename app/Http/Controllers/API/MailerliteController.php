<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class MailerliteController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'index' => [
            'api_key' => 'required',
        ],
    ];

    public function index()
    {
        try {
            /** List */
            $url = "https://api.mailerlite.com/api/v2/groups?limit=10000";
            $client = new Client(['verify' => false]);
            $response = $client->get($url, [
                'headers' => [
                    "X-MailerLite-ApiKey" => $this->request->api_key
                ]
            ]);
            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $list = json_decode($body);
            } else {
                $list = array();
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
