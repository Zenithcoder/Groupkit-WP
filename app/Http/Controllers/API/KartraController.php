<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class KartraController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'index' => [
            'api_key' => 'required',
            'password' => 'required',
            'app_id' => 'required',
        ],
    ];

    public function index()
    {
        try {
            /** List */
            $url = "https://app.kartra.com/api/";
            $client = new Client(['verify' => false]);

            $post = "app_id=" . $this->request->app_id;
            $post .= "&api_key=" . $this->request->api_key;
            $post .= "&api_password=" . $this->request->password;
            $post .= "&actions[0][cmd]=retrieve_account_lists";

            $response = $client->post($url, [
                'headers' => [
                    "Content-Type" => "application/x-www-form-urlencoded",
                ],
                'body' => $post
            ]);

            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $body = json_decode($body);
                if ($body->status == 'Success') {
                    $list = $body->account_lists;
                } else {
                    return response()->json(
                        [
                            'code' => Response::HTTP_BAD_REQUEST,
                            'message' => isset($body->message) ? $body->message : 'Invalid Request',
                            'data' => '',
                        ]
                    );
                }
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
