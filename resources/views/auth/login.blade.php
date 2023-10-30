@extends('layouts.app')
@section('content')
<style>
	.auth .brand-logo img {padding: 10px;margin: 0px;width: 68%;}
	.invalid-feedback{margin-top: 10px;}
</style>
<div class="container-scroller">
	<div class="container-fluid page-body-wrapper full-page-wrapper">
		<div class="content-wrapper d-flex align-items-stretch auth auth-img-bg">
			<div class="row flex-grow">
				<div class="col-lg-3 d-flex align-items-center justify-content-center"></div>
				<div class="col-lg-6 d-flex align-items-center justify-content-center">
					<div class="auth-form-transparent text-left p-3">
						<div class="brand-logo text-center">
							<img src="{{ asset('asset/images/logo.png') }}" alt="logo">
						</div>
						<h4>Welcome back!</h4>
						<h6 class="font-weight-light">Ready to grow & monetize your group?</h6>
						<a class="facebook button" href="{{route('social.login', ['provider' => 'facebook'])}}">
							Log in with Facebook
						</a>
						@error('facebook_login')
							<span class="invalid-feedback" role="alert" style="display: block;">
								<strong>{{ $message }}</strong>
							</span>
						@enderror
						<h4 class="facebook or">
							<span>OR</span>
						</h4>
						<form class="pt-3" method="POST" action="{{ route('login') }}" id="login_form">@csrf
							<div class="form-group">
								<label for="exampleInputEmail">Email Address</label>
								<div class="input-group">
									<div class="input-group-prepend bg-transparent">
										<span class="input-group-text bg-transparent border-right-0">
											<i class="mdi mdi-account-outline text-primary"></i>
										</span>
									</div>
									<input id="email" type="email" placeholder="Enter your email address..." class="form-control form-control-lg border-left-0" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
								</div>
							</div>
							<div id="showUserName">
								<div class="my-3">
									<button type="button" id="next_login" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">{{ __('Next') }}</button>
									<span class="invalid-feedback" role="alert" id="customeError" style="display:none">
										<strong></strong>
									</span>
								</div>
								<p class="text-center">
									<b>Not a client yet?</b>
									<a target="_blank" href="{{ route('plans.index') }}">Start your FREE 14-day trial now!</a>
								</p>
							</div>
							<div id="showPassword" style="display:none;">
								<div class="form-group">
									<label for="exampleInputPassword">Password</label>
									<div class="input-group">
										<div class="input-group-prepend bg-transparent"> <span class="input-group-text bg-transparent border-right-0">
											<i class="mdi mdi-lock-outline text-primary"></i>
											</span>
										</div>
										<input id="password" type="password" placeholder="Password" class="form-control form-control-lg border-left-0 @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">
										@error('password')
											<span class="invalid-feedback" role="alert">
											<strong>{{ $message }}</strong>
											</span>
										@enderror
									</div>
								</div>
								<div class="my-2 d-flex justify-content-between align-items-center">
									<div class="form-check">
										<label class="form-check-label text-muted">
										<input type="checkbox" class="form-check-input" name="remember" id="remember" {{ old( 'remember') ? 'checked' : '' }}>Keep me signed in</label>
									</div>
									<a href="{{ route('password.request') }}" class="auth-link text-black">Forgot password?</a>
								</div>
								<div class="my-3">
									<button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">{{ __('Login') }}</button>
									@error('email')
									<span class="invalid-feedback" role="alert" style="{{ $message ? 'display:block' : 'display:none' }}">
										<strong>{{ $message }}</strong>
									</span>
									@enderror
								</div>
							</div>
						</form>
					</div>
				</div>
				<div class="col-lg-3 d-flex align-items-center justify-content-center"></div>
			</div>
		</div>
		<!-- content-wrapper ends -->
	</div>
	<!-- page-body-wrapper ends -->
</div>
@if($errors->any())
	<script>
		$('#email').attr('readonly',true);
		$('#showUserName').hide();
		$('#showPassword').show();
	</script>
@endif
<script>
$(document).ready(function(){
    localStorage.removeItem('current_session');
	$('body').on('click','#next_login',function(){
		CallBack()
	})
	$('body').on('keypress','#email',function(event){
		var keycode = (event.keyCode ? event.keyCode : event.which);
		if(keycode == '13'){
			CallBack()
		}
	})
})
function CallBack(){
	var URL=$('#base_url').val();
	var email=$('#email').val()
	var formdata=$('#login_form').serializeArray()
	if(IsEmail(email)){
		$.ajax({
			type: "post",
			data: formdata,
			url: URL + '/verify',
			success: function (data) {
				if (data.status == 'success') {
					$('#email').attr('readonly',true);
					$('#showUserName').hide();
					$('#showPassword').show();
				}else{
					if(data.data){
						location.href =URL+data.data
					}else{
						$('#customeError strong').html(data.message)
						$('#customeError').show()
						setTimeout(() => {
							$('#customeError').hide()
						}, 3000);
					}
				}
			}
		})
	}else{
		$('#customeError strong').html('Enter your valid email address')
		$('#customeError').show()
		setTimeout(() => {
			$('#customeError').hide()
		}, 3000);
	}
}
function IsEmail(email) {
  	var regex = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;
  	if(!regex.test(email)) {
    	return false;
  	}else{
    	return true;
	}
}
</script>
@endsection
