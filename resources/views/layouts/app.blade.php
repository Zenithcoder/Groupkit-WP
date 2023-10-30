<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

	<link rel="stylesheet" href="{{ asset('asset/vendors/iconfonts/mdi/font/css/materialdesignicons.min.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/vendor.bundle.base.css') }}">
	<link rel="stylesheet" href="{{ asset('asset/vendors/css/vendor.bundle.addons.css') }}">
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
	<script src="{{ asset('asset/js/settings.js') }}"></script>
	<script src="{{ asset('asset/js/todolist.js') }}"></script>
	<style>
	.btn-primary, .wizard > .actions a {
		color: #fff;
		background-color: #3769B3;
		border-color: #3769B3;
	}
	.btn-primary:hover, .wizard > .actions a:hover {
		color: #fff;
		background-color: #3a6cb7e8;
		border-color: #3a6cb7e8;
	}
	.btn-primary:not(:disabled):not(.disabled):active, .wizard > .actions a:not(:disabled):not(.disabled):active, .btn-primary:not(:disabled):not(.disabled).active, .wizard > .actions a:not(:disabled):not(.disabled).active, .show > .btn-primary.dropdown-toggle, .wizard > .actions .show > a.dropdown-toggle {
		color: #fff;
		background-color: #3769B3;
		border-color: #3769B3;
	}
	.btn-primary, .wizard > .actions a, .btn-primary:hover, .wizard > .actions a:hover {
		box-shadow: 0 2px 2px 0 rgba(104, 142, 200, 0.48), 0 3px 1px -2px rgba(104, 142, 200, 0.55), 0 1px 5px 0 rgba(55, 105, 179, 0.55);
	}
	a.text-primary:hover, .list-wrapper .completed a.remove:hover, .horizontal-menu .bottom-navbar .page-navigation > .nav-item.mega-menu .submenu a.category-heading:hover, a.text-primary:focus, .list-wrapper .completed a.remove:focus, .horizontal-menu .bottom-navbar .page-navigation > .nav-item.mega-menu .submenu a.category-heading:focus{
		color: #3769B3 !important;
	}
	.text-primary:hover, .list-wrapper .completed a.remove:hover, .horizontal-menu .bottom-navbar .page-navigation > .nav-item.mega-menu .submenu a.category-heading:hover, a.text-primary:focus, .list-wrapper .completed a.remove:focus, .horizontal-menu .bottom-navbar .page-navigation > .nav-item.mega-menu .submenu a.category-heading:focus {
		color: #3769B3 !important;
	}
	.container-scroller{
		overflow-y: auto;
    	overflow-x: hidden;
	}
	</style>
</head>
<body>
    @php /* todo: Remove next line after refactoring chrome extension authentication */ @endphp
    <input type="hidden" name="groupkit_auth_token" value="">
    <input type="hidden" id="base_url" name="base_url" value="{{ URL('') }}" >
    @yield('content')
</body>
</html>
