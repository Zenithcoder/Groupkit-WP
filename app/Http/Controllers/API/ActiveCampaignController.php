<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class ActiveCampaignController extends AbstractApiController
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
            /* List */
            $offset = 0;
            $limit = 0;
            $url = "https://{$this->request->host_name}.api-us1.com/api/3/lists?offset={$offset}&limit={$limit}";
            $client = new Client(['verify' => false]);
            $response = $client->get(
                $url,
                [
                    'headers' => [ "Api-Token" => $this->request->api_key ],
                ]
            );

            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $list = json_decode($body)->lists;
            } else {
                $list = [];
            }

            /* Tags */
            $offset = 0;
            $limit = 0;
            $url = "https://{$this->request->host_name}.api-us1.com/api/3/tags?offset={$offset}&limit={$limit}";
            $client = new Client(['verify' => false]);
            $response = $client->get(
                $url,
                [
                    'headers' => [ "Api-Token" => $this->request->api_key ],
                ]
            );

            if ($response->getStatusCode() == 200) {
                $body = $response->getBody();
                $tags = json_decode($body)->tags;
            } else {
                $tags = [];
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => [
                        'tags' => $tags,
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
