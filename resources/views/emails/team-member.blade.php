@component('mail::message')
Hello, {{ $userData['name'] }}!
<br>

{{ $userData['acc_holder_name'] }}  has invited you to join their GroupKit team.
<br>


#1- {{ __('Below is your login information') }}:<br>
Username:  {{ $userData['user_name'] }}<br>

{{ __('Click the button below to create your password') }}:<br>
@component('mail::button', ['url' => route('setPassword', ['email' => $userData['user_name'], 'token' => $userData['token']])])
Set Your Password and Login
@endcomponent
<br>

#2- {{ __('Once you log in, please install the GroupKit Google Chrome Extension') }}: {{ config('app.GROUPKIT_GOOGLE_CHROME_EXTENSION_URL') }}

#3- {{ __('Request moderator access to the Facebook group that you will be moderating if you haven\'t already.') }}

See you inside,<br>
GroupKit Team<br><br>

PS- {{ __('If the login button doesn\'t work, copy and paste the URL listed below into your browser') }}:<br>
{{ route('setPassword', ['email' => $userData['user_name'], 'token' => $userData['token']]) }}<br>

@endcomponent
