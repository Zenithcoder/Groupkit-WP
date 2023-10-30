<?php
namespace App\Http\Controllers\API;

use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\User;

class AuthController extends AbstractApiController
{
    /**
     * @var array The rules used for validation for each action with the action name used as the key
     */
    protected array $ajaxValidatorRules = [
        'login' => [
            'email' => 'required|email',
            'password' => 'required|string',
        ],
    ];

    public function login()
    {
        if (Auth::attempt(['email' => $this->request->email, 'password' => $this->request->password])) {
            $user = User::where('email', $this->request->email)->first();

            return response()->json(
                [
                    'code' => Response::HTTP_OK,
                    'message' => 'success',
                    'data' => [
                        'user' => $user->getDetailsByUser($user),
                        'access_token' => $user->createToken($this->request->email)->accessToken
                    ],
                ]
            );
        } else {
            return response()->json(
                [
                    'code' => Response::HTTP_NOT_FOUND,
                    'message' => 'Please try again! Those credentials do not match our records.',
                    'data' => '',
                ]
            );
        }
    }
}
