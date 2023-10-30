<?php

namespace App\Http\Controllers\API;

use App\AutoResponder;
use App\Exceptions\Integrations\GoogleSheet\ColumnLimitExceededException;
use App\Exceptions\InvalidStateException;
use App\Services\MarketingAutomation\GoogleSheetService;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use Laravel\Socialite\Facades\Socialite;

class GoogleSheetController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'sendHeaders' => [
            'group_id' => 'required|numeric|exists:facebook_groups,id',
        ],
    ];

    /** google oauth redirect. */
    public function redirectToProvider()
    {
        $parameters = ['access_type' => 'offline', 'prompt' => 'consent select_account'];

        return Socialite::driver('google')
            ->setScopes(['https://www.googleapis.com/auth/spreadsheets', 'profile'])
            ->with($parameters)
            ->redirect();
    }

    /** google oauth callback handle. */
    public function handleProviderCallback()
    {
        $response = [];

        try {
            $user = Socialite::driver('google')->user();
            $response = [
                'code' => Response::HTTP_OK,
                'message' => 'Successfully',
                'data' => ['refreshToken' => $user->refreshToken, 'token' => $user->token],
            ];
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            \Log::error('Sorry! Something is wrong with this account!');
            $response = [
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Sorry! Something is wrong with this account!',
                'data' => '',
            ];
        }

        return view('callback', compact('response'));
    }

    /** google oauth get refreshToken. */
    public function googleRefreshToken($id)
    {
        $token = $this->getRefreshToken($id);

        return response()->json(
            [
                'code' => Response::HTTP_OK,
                'message' => 'Successfully',
                'data' => $token,
            ]
        );
    }

    public function getRefreshToken($groupid)
    {
        $responder = AutoResponder::where('group_id', $groupid)->first();
        $token = '';

        if (!$responder) {
            return $token;
        }

        $responderData = json_decode($responder->responder_json);
        $token = $responderData->token;

        try {
            $client = new Client(['verify' => false]);
            $response = $client->get(
                config('const')['GOOGLE_API_URL_TOKEN_INFO'] . urlencode($token),
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]
            );

            if ($response->getStatusCode() == 200) {
                $socialUser = json_decode($response->getBody());

                if ($socialUser->expires_in) {
                    return $token;
                } else {

                    $body = [
                        'client_id' => env('GOOGLE_CLIENT_ID'),
                        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                        'refresh_token' => $responderData->refreshToken,
                        'grant_type' => 'refresh_token',
                    ];

                    $clients = new Client(['verify' => false]);
                    $responses = $clients->post(
                        config('const')['GOOGLE_API_URL_TOKEN'],
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                            ],
                            'body' => json_encode($body),
                        ]
                    );

                    if ($responses->getStatusCode() == 200) {
                        $Refreshdata = json_decode($responses->getBody());
                        $responderData->token = $Refreshdata->access_token;
                        $responder->responder_json = json_encode($responderData);
                        $responder->save();

                        $token = $Refreshdata->access_token;
                        return $token;
                    } else {
                        return $token;
                    }
                }
            } else {
                return $token;
            }
        } catch (\Exception $e) {
            Bugsnag::notifyException($e);
            $body = [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'refresh_token' => $responderData->refreshToken,
                'grant_type' => 'refresh_token',
            ];
            $clients = new Client(['verify' => false]);
            $responses = $clients->post(
                config('const')['GOOGLE_API_URL_TOKEN'],
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($body),
                ]
            );

            if ($responses->getStatusCode() == 200) {
                $Refreshdata = json_decode($responses->getBody());
                $responderData->token = $Refreshdata->access_token;
                $responder->responder_json = json_encode($responderData);
                $responder->save();

                $token = $Refreshdata->access_token;
                return $token;
            } else {
                return $token;
            }
        }
    }

    /**
     * Send API request to add headers in the Google Sheet document
     *
     * todo: after moving connecting google sheet to {@see GoogleSheetService}, move this from http API to service
     */
    public function sendHeaders(): HttpResponse
    {
        try {
            GoogleSheetService::addHeaders($this->request->input('group_id'));
        } catch (GuzzleException | ColumnLimitExceededException | InvalidStateException $exception) {
            Bugsnag::notifyException($exception);

            return response(['message' => __('Something went wrong')], Response::HTTP_BAD_REQUEST);
        }

        return response(['message' => __('Successfully added Google Sheet headers')]);
    }
}
