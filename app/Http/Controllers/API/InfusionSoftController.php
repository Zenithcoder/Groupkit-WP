<?php

namespace App\Http\Controllers\API;

use App\Services\MarketingAutomation\InfusionSoftService;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Arr;

/**
 * The API end-point for maintaining InfusionSoft members data
 *
 * @package App\Http\Controllers\API
 */
class InfusionSoftController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'verifyCredentials' => [
            'clientId' => 'required',
            'clientSecret' => 'required',
            'authorizeCode' => 'required',
        ],
    ];

    /**
     * Validates given credentials Eg : clientId, clientSecret, authorizeCode
     *
     * @return Response with a success message, accessToken, refreshToken when verification is completed successfully,
     *                  otherwise, an error message.
     */
    public function verifyCredentials(): Response
    {
        $requestedResponse = app(InfusionSoftService::class)->requestAccessToken(
            $this->request->clientId,
            $this->request->clientSecret,
            $this->request->authorizeCode
        );

        $response = ['message' => $requestedResponse['message']];

        if (isset($requestedResponse['body'])) {
            $response['accessToken'] = $requestedResponse['body']->access_token;
            $response['refreshToken'] = $requestedResponse['body']->refresh_token;
        }

        return response($response, $requestedResponse['code']);
    }

    /**
     * InfusionSoft oauth callback handle.
     *
     * @return View handles the generated auth code which will later be required for the verification process.
     */
    public function handleProviderCallback(): View
    {
        return view('infusionSoftCallback');
    }

    /**
     * Returns all available InfusionSoft client tags
     *
     * @param int $facebookGroupId The ID of the group for which we wish to retrieve the InfusionSoft tags
     *
     * @return Response with a success message, tags when the response is completed successfully,
     * otherwise, an error message.
     */
    public function getTags(int $facebookGroupId): Response
    {
        $requestedResponse = app(InfusionSoftService::class)->getTags($facebookGroupId);

        return response(Arr::except($requestedResponse, 'code'), $requestedResponse['code']);
    }
}
