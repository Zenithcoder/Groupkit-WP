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
                                <h6 class="font-weight-light">{{ __('Confirm Password') }}</h6>
                                <p>{{ __('There is already an account associated with your email address, please confirm your password in order to proceed.') }}</p>
                                @if (session('status'))
                                    <div class="alert alert-success" role="alert">
                                        {{ session('status') }}
                                    </div>
                                @endif
                                <form class="pt-3" method="POST"
                                      action="{{ route('social.login.confirmPasswordPost') }}">
                                    @csrf
                                    <input type="hidden" name="user" value="{{$user->id}}"/>
                                    <div class="form-group">
                                        <label class="col-form-label text-md-right">{{ __('Password') }}</label>

                                        <div class="input-group">
                                            <input id="password" type="password"
                                                   class="form-control @error('password') is-invalid @enderror"
                                                   name="password" required autocomplete="current-password">

                                            @error('password')
                                            <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="mt-1">
                                        <button type="submit"
                                                class="btn btn-block btn-primary btn-lg font-weight-medium auth-form-btn">{{ __('Confirm Password') }}</button>
                                    </div>
                                    <div class="text-center mt-1 font-weight-light">
                                        <a class="btn btn-link" href="{{ route('password.request') }}">
                                            {{ __('Forgot Your Password?') }}
                                        </a>
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
