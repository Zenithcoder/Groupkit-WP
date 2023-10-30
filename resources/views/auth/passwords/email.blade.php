@extends('layouts.app')

@section('content')
<div class="container-scroller">
   <div class="container-fluid page-body-wrapper full-page-wrapper">
      <div class="main-panel">
         <div class="content-wrapper d-flex align-items-center auth">
            <div class="row w-100">
               <div class="col-lg-4 mx-auto">
                  <div class="auth-form-light text-left p-5">
                     <div class="brand-logo">
                        <img src="{{ asset('asset/images/logo.png') }}" alt="logo">
                     </div>
                     <h6 class="font-weight-light">{{ __('Reset Password') }}.</h6>
					 @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                     <form class="pt-3" method="POST" action="{{ route('password.email') }}">
                        @csrf
                        <div class="form-group">
                           <label for="email" class="col-form-label text-md-right">{{ __('E-Mail Address') }}</label>

							<div class="input-group">
								<input placeholder="Email" id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>

								@error('email')
									<span class="invalid-feedback" role="alert">
										<strong>{{ $message }}</strong>
									</span>
								@enderror
							</div>
                        </div>
                        <div class="mt-3">
                           <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">{{ __('Send Password Reset Link') }}</button>
                        </div>
                     </form>
                  </div>
               </div>
            </div>
         </div>
      </div>
      <!-- content-wrapper ends -->
   </div>
   <!-- page-body-wrapper ends -->
</div>
<!-- container-scroller -->
@endsection
