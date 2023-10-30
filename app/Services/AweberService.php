<?php

namespace App\Services;

use App\GroupkitMailingListCredential;
use App\User;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class AweberService provides methods to connect with the Aweber API
 * @package App\Services
 */
class AweberService
{
    /**
     * @var int id of the Aweber mailing list which corresponds to the customer's plan type
     */
    private int $mailingListId;

    /**
     * Subscribes a new customer to an Aweber mailing list
     *
     * @param User $customer to be added to a mailing list
     *
     * @return void
     */
    public function subscribeCustomer(User $customer): void
    {
        if (!app()->environment('production')) {
            return;
        }

        $groupKitMailingCredentials = GroupkitMailingListCredential::orderByDesc('id')->first();

        if ($groupKitMailingCredentials) {
            $client = new Client();

            try {
                if (Carbon::parse($groupKitMailingCredentials->expires_at)->isPast()) {
                    $groupKitMailingCredentials = $this->getNewToken($groupKitMailingCredentials);
                }

                $accessToken = $groupKitMailingCredentials->access_token;

                $url = "https://api.aweber.com/1.0/accounts/{$groupKitMailingCredentials->account_id}"
                    . "/lists/{$this->mailingListId}/subscribers";

                $client->post(
                    $url,
                    [
                        'headers' => [
                            'Authorization' => "Bearer {$accessToken}",
                            'Content-Type'  => 'application/x-www-form-urlencoded',
                        ],
                        'body'    => http_build_query([
                            'email' => $customer->email,
                            'name'  => $customer->name,
                        ]),
                    ]
                );
            } catch (GuzzleException $e) {
                logger()->error($e->getMessage());
            }
        }
    }

    /**
     * Gets new access token from Aweber
     *
     * @param GroupkitMailingListCredential $credential includes refresh token and client id for getting a new token.
     *                                                     The refresh token inside of this object is given by
     *                                                     Aweber response upon the access token creation call.
     *
     * @return GroupkitMailingListCredential with an Aweber API access token that is valid for two hours after creation
     *
     * @throws GuzzleException if post method fails
     */
    private function getNewToken(GroupkitMailingListCredential $credential)
    {
        $url = 'https://auth.aweber.com/oauth2/token';
        $client = new Client();

        $bodyData = [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $credential->refresh_token,
            'redirect_uri'  => 'urn:ietf:wg:oauth:2.0:oob',
            'client_id'     => $credential->client_id,
        ];

        $response = $client->post(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body'    => http_build_query($bodyData),
            ]
        );

        $responseData = json_decode($response->getBody());

        return GroupkitMailingListCredential::create([
            'client_id' => $credential->client_id,
            'account_id' => $credential->account_id,
            'access_token' => $responseData->access_token,
            'refresh_token' => $responseData->refresh_token,
            'expires_at' => Carbon::parse(Carbon::now()->timestamp + $responseData->expires_in),
        ]);
    }

    /**
     * Sets mailing list according to the specified plan
     *
     * @param string $planId of the customer
     *
     * @return AweberService $this
     */
    public function setMailingList(string $planId)
    {
        $this->mailingListId = config("services.aweber.list.$planId");

        return $this;
    }

    /**
     * Sets order bump for the {@see \App\Services\AweberService::$mailingListId}
     * Subscribes customer to the order bump list
     *
     * @param User $customer
     */
    public function subscribeToOrderBumpList(User $customer)
    {
        $this->mailingListId = config('services.aweber.list.order_bump');
        $this->subscribeCustomer($customer);
    }
}
