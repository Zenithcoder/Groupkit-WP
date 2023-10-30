@component('mail::message')

# Hello {{ $mailData->name }},<br></br><br></br>


Welcome to GroupKit. We are excited to help you transform your group into a high-powered,
client-generating machine.<br></br>


To get started, simply enter your purchase email address on the login page here: {{ config('app.url') }}<br></br>


Once you enter your purchase email on the login page, you will be prompted to create your password.
After logging in, you will find a quick-start video on the dashboard with instructions on how to get started.<br></br>


If you have any questions or require additional assistance, please visit https://support.groupkit.com/
or email support@groupkit.com<br></br><br></br>

See you inside,<br></br>
GroupKit Team<br></br>
    
@endcomponent
