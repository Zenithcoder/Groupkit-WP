<?php

namespace App\Http\Controllers\API;

use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use App\AutoResponder;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;

class AweberController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'index' => [
            'access_token' => 'required',
            'account_id' => 'required',
        ],
        'getAweberAccount' => [
            'access_token' => 'required',
        ],
        'getToken' => [
            'auth_code' => 'required',
            'client_id' => 'required',
            'code_verifier' => 'required',
        ],
    ];

    /** get list */
    public function index()
    {
        try {
            /* List */
            $access_token = $this->aweberRefreshToken($this->request->group_id, $this->request->access_token);
            if (trim($access_token) == "") {
                return response()->json(
                    [
                        'code' => Response::HTTP_UNAUTHORIZED,
                        'message' => 'Invalid Request',
                        'data' => [],
                    ]
                );
            }

            $userid = $this->request->account_id;
            $url = "https://api.aweber.com/1.0/accounts/{$userid}/lists";
            $client = new Client(['verify' => false]);
            $response = $client->get(
                $url,
                [
                    'headers' => [
                        "Authorization" => "Bearer {$access_token}",
                        "Content-Type" => "application/x-www-form-urlencoded"
                    ],
                ]
            );

            if ($response->getStatusCode() == 200) {
                $body = $response->getBody();
                $list = json_decode($body)->entries;
            } else {
                $list = array();
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => [
                        'list' =>$list,
                    ],
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => $e->getCode(),
                    'message' => 'Invalid Request',
                    'data' => [],
                ]
            );
        }
    }

    /** get account details */
    public function getAweberAccount()
    {
        try {
            $access_token = $this->request->access_token;
            $url = "https://api.aweber.com/1.0/accounts/";
            $client = new Client(['verify' => false]);

            $response = $client->get(
                $url,
                [
                    'headers' => [
                        "Authorization" => "Bearer {$access_token}",
                        "Content-Type" => "application/x-www-form-urlencoded",
                    ],
                ]
            );

            if ($response->getStatusCode() == 200) {
                $body = $response->getBody();
                $data = json_decode($body);
            } else {
                $data = array();
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => $e->getCode(),
                    'message' => 'Invalid Request',
                    'data' => []
                ]
            );
        }
    }

    /** get token */
    public function getToken()
    {
        try {
            $post = "grant_type=authorization_code"
                . "&code=" . $this->request->auth_code
                . "&redirect_uri=urn:ietf:wg:oauth:2.0:oob"
                . "&client_id=" . $this->request->client_id
                . "&code_verifier=" . $this->request->code_verifier;

            $url = "https://auth.aweber.com/oauth2/token";
            $client = new Client(['verify' => false]);
            $response = $client->post(
                $url,
                [
                    'headers' => [
                        "Content-Type" => "application/x-www-form-urlencoded",
                    ],
                    'body' => $post,
                ]
            );

            if ($response->getStatusCode()) {
                $body = $response->getBody();
                $data = json_decode($body);
            } else {
                $data = array();
            }

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'data' => $data,
                ]
            );
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            return response()->json(
                [
                    'code' => $e->getCode(),
                    'message' => 'Invalid Request',
                    'data' => []
                ]
            );
        }
    }

    /** generate new token */
    public function aweberRefreshToken($groupid, $token)
    {
        $responder = AutoResponder::where('group_id', $groupid)->first();

        if (!$responder) {
            return $token;
        }

        $responderData = json_decode($responder->responder_json);
        $token = $responderData->access_token;

        try {

            $currentTimestamp = \Carbon\Carbon::now()->timestamp;

            if (isset($responderData->expires_in) && $responderData->expires_in > $currentTimestamp) {
                return $token;
            }

            $post = "grant_type=refresh_token"
                . "&refresh_token=" . $responderData->refresh_token
                . "&redirect_uri=urn:ietf:wg:oauth:2.0:oob"
                . "&client_id=" . $responderData->client_id;

            $url = "https://auth.aweber.com/oauth2/token";
            $client = new Client(['verify' => false]);
            $response = $client->post(
                $url,
                [
                    'headers' => [
                        "Content-Type" => "application/x-www-form-urlencoded",
                    ],
                    'body' => $post,
                ]
            );

            if ($response->getStatusCode()) {
                $responseData = json_decode($response->getBody());
                $token = $responseData->access_token;

                $responderData->access_token = $responseData->access_token;
                $responderData->refresh_token = $responseData->refresh_token;
                $responderData->expires_in = $currentTimestamp + $responseData->expires_in;
                $responder->responder_json = json_encode($responderData);
                $responder->save();
            }

            return $token;

        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            $response = json_decode($e->getResponse()->getBody()->getContents());
            if (isset($response->error_description) && strtolower($response->error_description) == 'invalid refresh_token') {
                $token = '';
            }

            return $token;
        }
    }
}
