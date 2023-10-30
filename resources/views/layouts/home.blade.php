<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>


	<!-- Styles -->
	@if (config('app.env') == 'local')
	<link href="{{ asset('css/app.css') }}" rel="stylesheet">
	@else
	<link rel="stylesheet" href="{{asset(mix('css/app.css'), true)}}" rel="stylesheet">
	@endif
    <!-- <link href="{{ asset('css/app.css') }}" rel="stylesheet"> -->
	<link rel="stylesheet" href="{{ asset('asset/vendors/iconfonts/font-awesome/css/font-awesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/iconfonts/mdi/font/css/materialdesignicons.min.css') }}">

	<link rel="stylesheet" href="{{ asset('asset/css/horizontal-layout/style.css') }}">
	<!-- endinject -->
	<link rel="shortcut icon" href="{{ asset('asset/images/favicon.png') }}" />
	<!-- Scripts -->
    <script src="{{ asset('js/moment_min.js') }}"></script>
    <script src="{{ asset('js/global.js') }}"></script>

	@if (config('app.env') == 'local')
	<script src="{{ asset('js/app.js') }}" defer></script>
	@else
	<script src="{{asset(mix('js/app.js'), true)}}" defer></script>
	@endif
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/custome.css') }}">
</head>
<body>
	<input type="hidden" id="app_url" name="app_url" value="{{ env('APP_URL') }}" >
	<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
		@csrf
	</form>
    @yield('content')
    @if (session()->has('groupkit_auth'))
    <script type="text/javascript">
        localStorage.setItem('current_session', '{!! session()->get('groupkit_auth') !!}');
    </script>
    @endif
</body>
	<button type="button" id="back2Top" onClick="scrolltoTOP()" style="position: fixed;right:10px;bottom: 20px;display:none;" class="btn btn-primary btn-rounded btn-icon"><i class="fa fa-angle-double-up"></i></button>
	<script>
		window.addEventListener('scroll', function() {
			var height = $(window).scrollTop();
			if (height > 100) {
				$('#back2Top').fadeIn();
			} else {
				$('#back2Top').fadeOut();
			}
		});
		function scrolltoTOP(){
			window.scroll({
				top: 0,
				left: 0,
				behavior: 'smooth',
			});
		}
	</script>
</html>
