<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class MailChimpController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'index' => [
            'api_key' => 'required',
            'host_name' => 'required',
        ],
    ];

    public function index()
    {
        try {
            /** List */
            $hostname = $this->request->host_name;
            $api_key = 'Basic ' . base64_encode("groupkit:" . $this->request->api_key);
            $url = "https://" . $hostname . ".api.mailchimp.com/3.0/lists/?count=100&offset=0";
            $client = new Client(['verify' => false]);
            $response = $client->get($url, [
                'headers' => [
                    "Authorization" => $api_key
                ]
            ]);
            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $list = json_decode($body)->lists;
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
