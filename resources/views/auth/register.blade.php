@extends('layouts.app')

@section('content')
<div class="container-scroller">
	<div class="container-fluid page-body-wrapper full-page-wrapper">
		<div class="content-wrapper d-flex align-items-stretch auth auth-img-bg">
			<div class="row flex-grow">
				<div class="col-lg-6 d-flex align-items-center justify-content-center">
					<div class="auth-form-transparent text-left p-3">
						<div class="brand-logo">
							<img src="{{ asset('asset/images/logo.png') }}" alt="logo">
						</div>
						<h4>New here?</h4>
						<h6 class="font-weight-light">Join us today! It takes only few steps</h6>
						<form class="pt-3" method="POST" action="{{ route('register') }}">
							@csrf
							<div class="row">
								<div class="form-group col-6">
									<label>{{ __('First Name') }}</label>
									<div class="input-group">
										<div class="input-group-prepend bg-transparent"> 
											<span class="input-group-text bg-transparent border-right-0">
													<i class="mdi mdi-account-outline text-primary"></i>
											</span>
										</div>									
										<input id="first_name" placeholder="First Name" type="text" class="form-control form-control-lg border-left-0 @error('first_name') is-invalid @enderror" name="first_name" value="{{ old('first_name') }}" required>
										@error('first_name')
											<span class="invalid-feedback" role="alert">
												<strong>{{ $message }}</strong>
											</span>
										@enderror
									</div>
								</div>
								<div class="form-group col-6">
									<label>{{ __('Last Name') }}</label>
									<div class="input-group">
										<div class="input-group-prepend bg-transparent"> 
											<span class="input-group-text bg-transparent border-right-0">
													<i class="mdi mdi-account-outline text-primary"></i>
											</span>
										</div>									
										<input id="last_name" placeholder="Last Name" type="text" class="form-control form-control-lg border-left-0 @error('last_name') is-invalid @enderror" name="last_name" value="{{ old('last_name') }}" required>
										@error('last_name')
											<span class="invalid-feedback" role="alert">
												<strong>{{ $message }}</strong>
											</span>
										@enderror
									</div>
								</div>
							</div>
							<div class="form-group">
								<label>Email</label>
								<div class="input-group">
									<div class="input-group-prepend bg-transparent"> 
										<span class="input-group-text bg-transparent border-right-0">
											<i class="mdi mdi-email-outline text-primary"></i>
										  </span>
									</div>									
									<input id="email" type="email" placeholder="Email" class="form-control form-control-lg border-left-0 @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">
									@error('email')
										<span class="invalid-feedback" role="alert">
											<strong>{{ $message }}</strong>
										</span>
									@enderror
								</div>
							</div>							
							<div class="form-group">
								<label>Password</label>
								<div class="input-group">
									<div class="input-group-prepend bg-transparent"> 
										<span class="input-group-text bg-transparent border-right-0">
											<i class="mdi mdi-lock-outline text-primary"></i>
										</span>
									</div>									
									<input id="password" type="password" placeholder="Password" class="form-control form-control-lg border-left-0 @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
									@error('password')
										<span class="invalid-feedback" role="alert">
											<strong>{{ $message }}</strong>
										</span>
									@enderror
								</div>
							</div>
							<div class="form-group">
								<label>{{ __('Confirm Password') }}</label>
								<div class="input-group">
									<div class="input-group-prepend bg-transparent"> 
										<span class="input-group-text bg-transparent border-right-0">
											<i class="mdi mdi-lock-outline text-primary"></i>
										</span>
									</div>									
									<input placeholder="Re-type Password"  id="password-confirm" type="password" class="form-control form-control-lg border-left-0" name="password_confirmation" required autocomplete="new-password">									
								</div>
							</div>
							<div class="mb-4">
								<div class="form-check">
									<label class="form-check-label text-muted">
										<input type="checkbox" class="form-check-input" required>I agree to all Terms & Conditions</label>
								</div>
							</div>
							<div class="mt-3"> 
								<button class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn" type="submit" >SIGN UP</button>
							</div>
							<div class="text-center mt-4 font-weight-light">Already have an account? <a href="{{ route('login') }}" class="text-primary">Login</a>
							</div>
						</form>
					</div>
				</div>
				<div class="col-lg-6 register-half-bg d-flex flex-row">
					<p class="text-white font-weight-medium text-center flex-grow align-self-end">Copyright &copy; {{ date('Y')}} All rights reserved.</p>
				</div>
			</div>
		</div>
		<!-- content-wrapper ends -->
	</div>
	<!-- page-body-wrapper ends -->
</div>
@endsection
