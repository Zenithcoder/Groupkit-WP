@component('mail::message')
Hello, {{ $user->name }}!

Below is your login information:<br>
Username:  {{ $user->email }}<br>

@component('mail::button', ['url' => route('login')])
Login Here
@endcomponent

See you inside,<br>
GroupKit Team<br><br>

PS- If the login button doesn't work, copy and paste the below URL into your browser: {{ route('login') }}<br>

@endcomponent
