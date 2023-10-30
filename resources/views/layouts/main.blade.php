<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
	<link rel="stylesheet" href="{{ asset('asset/vendors/iconfonts/font-awesome/css/font-awesome.min.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/iconfonts/mdi/font/css/materialdesignicons.min.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/vendor.bundle.base.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/vendor.bundle.addons.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/setting.css') }}">
	<!-- endinject -->
	<!-- plugin css for this page -->
	<!-- End plugin css for this page -->
	<!-- inject:css -->
	<link rel="stylesheet" href="{{ asset('asset/css/horizontal-layout/style.css') }}">
	<!-- endinject -->
	<link rel="shortcut icon" href="{{ asset('asset/images/favicon.png') }}" />
	<!-- plugins:js -->
	<script src="{{ asset('asset/vendors/js/vendor.bundle.base.js') }}"></script>
	<script src="{{ asset('asset/vendors/js/vendor.bundle.addons.js') }}"></script>
	<script src="{{ asset('asset/js/off-canvas.js') }}"></script>
	<script src="{{ asset('asset/js/hoverable-collapse.js') }}"></script>
	<script src="{{ asset('asset/js/template.js') }}"></script>
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/custome.css') }}">
</head>
<body>
	<input type="hidden" id="base_url" name="base_url" value="{{ URL('') }}" >
	<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
		@csrf
	</form>
	<div class="container-scroller">
		<!-- partial:partials/_horizontal-navbar.html -->
		<div class="horizontal-menu">
			<nav class="navbar top-menu top-navbar col-lg-12 col-12 p-0">
				<div class="nav-top flex-grow-1">
					<div class="container d-flex flex-row h-100 align-items-center">
						<div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
							<a class="navbar-brand brand-logo" href="{{ route('home') }}">
								<img src="{{ asset('asset/images/logo.png') }}" alt="logo" class="big-logo" />
								<img src="{{ asset('asset/images/groupkit_mobile_logo.png') }}" alt="profile" class="small-logo" />
							</a>
						</div>
						<div class="navbar-menu-wrapper d-flex align-items-center justify-content-end flex-grow-1">
							<ul class="navbar-nav navbar-nav-right">
								@if (auth()->user())
								<li class="nav-item nav-profile dropdown">
									<a href="{{ route('home') }}">
										<img src="{{ asset('asset/images/groupkit_mobile_logo.png') }}" alt="profile" />
									</a>
									<span class="menu-title profile_title cursor-pointer" href="#" data-toggle="dropdown" >Hello, {{ auth()->user()->name }}</span>
									<a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown"></a>
									<div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
										@if((strpos(strtolower(App\User::subscriptionsPlan(auth()->user()->id)),'pro')))
										<a class="dropdown-item" href="{{ route('teamMembers') }}"> <i class="fa fa-users"></i>Team Members</a>
										@endif
										<a class="dropdown-item" href="{{ route('giveaway') }}"> <i class="fa fa-trophy"></i>Giveaway</a>
										<div class="dropdown-divider"></div>
                                        @if(
                                            Route::currentRouteName() === 'plans.index'
                                            && $subscriptionIsPaused
                                        )
										    <a class="dropdown-item" href="{{ route('subscriptionOptions') }}">
                                                <i class="fa fa-gear"></i>Settings
                                            </a>
                                        @else
										    <a class="dropdown-item" href="{{ route('setting') }}"> <i class="fa fa-gear"></i>Settings</a>
                                        @endif
										<div class="dropdown-divider"></div>
										<a class="dropdown-item" href="{{ route('logout') }}" onclick="event.preventDefault();
										    localStorage.removeItem('current_session');
											document.getElementById('logout-form').submit();">
											<i class="fa fa-sign-out"></i> {{ __('Logout') }}
										</a>
									</div>
								</li>
								@endif
							</ul>
						</div>
					</div>
				</div>
			</nav>
		</div>
		<!-- partial -->
		@yield('content')
		<footer class="footer">
			<div class="w-100 clearfix text-center">
				<img height="80" src="{{ asset('asset/images/logo.png') }}" alt="logo" />
				<P class="mt-1">
					<a  target="_blank" href="https://groupkit.com/privacy?=">Privacy </a>|
					<a  target="_blank" href="https://groupkit.com/terms?="> Terms </a>|
					<a  target="_blank" href="https://members.groupkit.com/login"> Training Platform </a>|
					<a  target="_blank" href="https://groupkit.tapfiliate.com/"> Affiliates </a>|
					<a  target="_blank" href="https://support.groupkit.com/"> Support </a>
				</p>
				<P class="mt-1">©{{ date('Y')}} SME Publishing, LLC / All Rights Reserved.</p>
				<P class="mt-1">GroupKit is not affiliated by Facebook™ in any way. Facebook™ is a registered trademark Facebook Inc.</p>
			</div>
        </footer>
	</div>
    @if (session()->has('groupkit_auth'))
    <script type="text/javascript">
        localStorage.setItem('current_session', '{!! session()->get('groupkit_auth') !!}');
    </script>
    @endif
</body>
</html>
