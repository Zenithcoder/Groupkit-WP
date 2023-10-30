<?php

namespace App;

use App\Mail\UpdateEmail;
use Bugsnag\BugsnagLaravel\Facades\Bugsnag;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

/**
 * Class EmailUpdateRequest maintains requests to change email addresses by users
 * @package App
 */
class EmailUpdateRequest extends Model
{
    use HasFactory;

    /** @var int represents verification emails expiration time in hours */
    public const EXPIRATION_TIME = 24;

    /**
     * @var string The underlying database table for this model
     */
    protected $table = 'email_update_requests';

    /**
     * @var string[] Allowed values to be saved via the web App
     */
    protected $fillable = [
        'current_email', 'new_email', 'activation_code', 'expires_at', 'ip_address'
    ];

    /**
     * Sends an activation link to the users new email address
     *
     * @param string $currentEmail represent the current email of the {@see User}
     * @param string $newEmail that will be set for the {@see User}
     * @param string $clientIp address
     *
     * @return array return success message when activation link sent to new email address,
     * otherwise an error message
     */
    public static function sendActivationLink(string $currentEmail, string $newEmail, string $clientIp): array
    {
        $user = app(User::class)::where('email', $currentEmail)->first();

        $currentUsersHavingRequestedEmail = User::where('email', $newEmail)->count();

        if ($currentUsersHavingRequestedEmail) {
            return [
                'message' => __('There is already an account using this email address.'),
                'success' => false,
            ];
        }

        $emailUpdateRequest = new EmailUpdateRequest();
        $emailUpdateRequest->current_email = $user->email;
        $emailUpdateRequest->new_email = $newEmail;
        $emailUpdateRequest->activation_code = Crypt::encryptString($newEmail . rand());
        $emailUpdateRequest->ip_address = $clientIp;
        $emailUpdateRequest->expires_at = now()->addHours(self::EXPIRATION_TIME);

        DB::beginTransaction();
        try {
            $emailUpdateRequest->save();
            Mail::to($emailUpdateRequest->new_email)
                ->send(new UpdateEmail($user->name, $emailUpdateRequest->activation_code));
            DB::commit();
        } catch (Exception $e) {
            Bugsnag::notifyException($e);
            DB::rollBack();
            logger()->error($e->getMessage());
            return [
                'message' => __('Invalid Request'),
                'success' => false,
            ];
        }

        return [
            'message' => __('Confirmation email has been sent to your new email address') . ' ' . $newEmail,
            'success' => true,
        ];
    }
}
