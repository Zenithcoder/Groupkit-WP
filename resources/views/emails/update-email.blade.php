@component('mail::message')
# Hello, {{ $customerName }}!
<br>

You have requested to change your email address. To complete the process, please click the <b>Verify Email Address</b> button below.
<br>

@component('mail::button', ['url' => route('user.activateNewEmail', $activationCode)])
    Verify Email Address
@endcomponent

If you did not request to update your email address, no further action is required.
<br>

Thanks,<br>
GroupKit Team

<br>
<hr/>
<br>
If you’re having trouble clicking the "Verify Email Address" button,
copy and paste the URL below into your web browser:<br/>
{{route('user.activateNewEmail', $activationCode)}}
<br>
@endcomponent
