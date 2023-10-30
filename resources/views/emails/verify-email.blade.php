@component('mail::message')
# Hello, {{$user->name}}!
<br>

Please click the button below to verify your email address and activate your GroupKit account.

@component('mail::button', ['url' => $verifyUrl])
Verify Email Address
@endcomponent

If you did not create a GroupKit account, no further action is required.
<br>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
