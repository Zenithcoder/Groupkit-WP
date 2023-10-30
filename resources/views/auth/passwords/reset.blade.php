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
                     <h4 class="font-weight-light">{{ __('Reset Password') }}</h4>
					 @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                     <form class="pt-3" method="POST" action="{{ route('password.update') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <div class="form-group">
                            <label>{{ __('E-Mail Address') }}</label>
                            <div class="input-group">
                                <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus readonly>
                                @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password">{{ __('Password') }}</label>
                            <div class="input-group">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password-confirm">{{ __('Confirm Password') }}</label>
                            <div class="input-group">
                                <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="mt-3">
                                <button type="submit" class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">{{ __('Reset Password') }}</button>
                            </div>
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
