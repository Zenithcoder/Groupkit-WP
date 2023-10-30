@extends('layouts.main')
@section('content')
<link rel="stylesheet" href="{{ asset('asset/vendors/css/stripe.css') }}">
<script src="https://js.stripe.com/v3/"></script>
<script> var stripe = Stripe('{{ env("STRIPE_KEY") }}'); </script>
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="card">
                    <h2 class="text-center mt-4">
                        {{__('Begin Your ')}} {{$plan->product->metadata->trialLength}}-{{__('Day Free Trial Now')}}...
                    </h2>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 col-lg-6 col-sm-12">
                                <div class="card">
                                    <p class="title_plan mt-3">
                                        {{__('Try')}} {{__("$plan->nickname")}}
                                        {{__('free for')}} {{$plan->product->metadata->trialLength}}
                                        {{ (int)$plan->product->metadata->trialLength === 1 ? __('day') : __('days')}},
                                        {{__('then just')}} ${{$plan->amount/100}}/{{__('month')}}. {{__('Cancel anytime')}}.
                                    </p>
                                    <div class="card-body stripefrom">
                                        @if (!session('access_token') && !auth()->user())
                                            <a
                                                class="facebook button"
                                                href="{{route('social.login', ['provider'=>'facebook', 'plan'=>base64_encode($plan->id)])}}"
                                            >
                                                {{__('Sign up with Facebook')}}
                                            </a>
                                            <h4 class="facebook or">
                                                <span>{{__('OR')}}</span>
                                            </h4>
                                        @endif
                                        <div class="cell example example1" id="example-1">
                                            @if (Request::get('token') != '')
                                                <?php
                                                    $response = json_decode(base64_decode(Request::get('token')));
                                                ?>
                                                <form action="{{ route('subscription.create') }}" method="post" id="payment-form">
                                                <input type="hidden" name="type" value="{{$plan->interval}}"/>
                                                <input type="hidden" name="product_purchase" id="purchase" value="{{ $response->purchase }}">
                                                <input type="hidden" name="plan" id="planid" value="{{ base64_decode(request()->route('plan')) }}">
                                                <input type="hidden" name="paymentMethod" id="token" value="{{ $response->paymentMethod }}">

                                                <input type="hidden" name="firstName" id="firstName" value="{{ $response->requestUser->firstName }}">
                                                <input type="hidden" name="lastName" id="lastName" value="{{ $response->requestUser->lastName }}">
                                                <input type="hidden" name="email" id="email" value="{{ $response->requestUser->email }}">
                                                <input type="hidden" name="password" id="password" value="{{ $response->requestUser->password }}">
                                                <input type="hidden" name="userData" id="userData" value="{{ $response->requestUser->userData }}">
                                            @else
                                                <form action="{{ route('subscription.upgradePlan') }}" method="post" id="payment-form">
                                                <input type="hidden" name="plan" id="planid" value="{{ $plan->id }}">
                                                <input type="hidden" name="paymentMethod" id="token" value="">
                                            @endif
                                                @csrf
                                                    @if (auth()->user())
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <input id="first_name" data-tid="elements_examples.form.name_placeholder" value="{{ auth()->user()->name }}" type="text" placeholder="FullName" required="" readonly autocomplete="name" class="form-control form-control-lg border-left-0">
                                                        </div>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="input-group">
                                                            <input id="example1-email" data-tid="elements_examples.form.email_placeholder" value="{{ auth()->user()->email }}" type="email" placeholder="EmailAddress" readonly required="" autocomplete="email" class="form-control form-control-lg border-left-0">
                                                        </div>
                                                    </div>
                                                @elseif(!Request::get('token'))
                                                <div class="row">
                                                    <div class="form-group col-md-6 col-md-4">
                                                        <label for="first_name">{{ __('First Name') }}</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend bg-transparent">
                                                                <span class="input-group-text bg-transparent border-right-0">
                                                                    <i class="mdi mdi-account-outline text-primary"></i>
                                                                </span>
                                                            </div>
                                                            <input
                                                                id="first_name"
                                                                name="first_name"
                                                                class="form-control form-control-lg border-left-0"
                                                                placeholder="{{__('First Name')}}"
                                                                type="text"
                                                                value="{{session('first_name') ?? old('first_name')}}"
                                                                maxlength="85"
                                                                required
                                                            />
                                                        </div>
                                                        @error('first_name')
                                                            <div class="alert alert-danger">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label for="last_name">{{ __('Last Name') }}</label>
                                                        <div class="input-group">
                                                            <div class="input-group-prepend bg-transparent">
                                                                <span class="input-group-text bg-transparent border-right-0">
                                                                    <i class="mdi mdi-account-outline text-primary"></i>
                                                                </span>
                                                            </div>
                                                            <input
                                                                id="last_name"
                                                                name="last_name"
                                                                class="form-control form-control-lg border-left-0"
                                                                placeholder="{{__('Last Name')}}"
                                                                type="text"
                                                                value="{{session('last_name') ?? old('last_name')}}"
                                                                maxlength="85"
                                                                required
                                                            />
                                                        </div>
                                                        @error('last_name')
                                                            <div class="alert alert-danger">{{ $message }}</div>
                                                        @enderror
                                                    </div>
                                                </div>
                                                    <div class="row">
                                                        <div class="form-group col-md-6 col-md-12">
                                                            <label for="email">Email</label>
                                                            <div class="input-group">
                                                                <div class="input-group-prepend bg-transparent">
                                                                    <span class="input-group-text bg-transparent border-right-0">
                                                                        <i class="mdi mdi-email-outline text-primary"></i>
                                                                    </span>
                                                                </div>
                                                                <input
                                                                    id="email"
                                                                    type="email"
                                                                    value="{{session('email') ?? old('email')}}"
                                                                    placeholder="Email"
                                                                    class="form-control form-control-lg border-left-0"
                                                                    name="email"
                                                                    autocomplete="email"
                                                                    required
                                                                />
                                                            </div>
                                                            @error('email')
                                                                <div class="alert alert-danger">{{ $message }}</div>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    @if(!session('access_token'))
                                                        <div class="row">
                                                            <div class="form-group col-md-6 col-md-12">
                                                                <label for="password">Password</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend bg-transparent">
                                                                        <span class="input-group-text bg-transparent border-right-0">
                                                                            <i class="mdi mdi-lock-outline text-primary"></i>
                                                                        </span>
                                                                    </div>
                                                                    <input
                                                                        id="password"
                                                                        type="password"
                                                                        class="form-control form-control-lg border-left-0"
                                                                        name="password"
                                                                        placeholder="Password"
                                                                        autocomplete="new-password"
                                                                        minlength="8"
                                                                        required
                                                                    />
                                                                </div>
                                                                @error('password')
                                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="form-group col-md-6 col-md-12">
                                                                <label for="password_confirmation">{{ __('Confirm Password') }}</label>
                                                                <div class="input-group">
                                                                    <div class="input-group-prepend bg-transparent">
                                                                        <span class="input-group-text bg-transparent border-right-0">
                                                                            <i class="mdi mdi-lock-outline text-primary"></i>
                                                                        </span>
                                                                    </div>
                                                                    <input
                                                                        id="password_confirmation"
                                                                        type="password"
                                                                        class="form-control form-control-lg border-left-0"
                                                                        name="password_confirmation"
                                                                        placeholder="Re-type Password"
                                                                        autocomplete="new-password"
                                                                        minlength="8"
                                                                        required
                                                                    />
                                                                </div>
                                                                @error('password_confirmation')
                                                                    <div class="alert alert-danger">{{ $message }}</div>
                                                                @enderror
                                                            </div>
                                                        </div>
                                                    @endif
                                                <input type="hidden" name="userData" id="userData" value="yes">
                                                @endif
                                                <div class="row">
                                                    <div class="form-group col-md-6 col-md-12">
                                                     <label>Card Number</label>
                                                     <div class="input-group">
                                                            <div id="example2-card-number" class="form-control form-control-lg border-left-1" >
                                                            </div>
                                                    </div>
                                                    </div>
                                                </div>
                                                <div class="row">
                                                    <div class="form-group col-md-6">
                                                        <label>CVC</label>
                                                        <div class="input-group">
                                                            <div id="example2-card-cvc" class="form-control form-control-lg border-left-1">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="form-group col-md-6">
                                                        <label>Card Expiry</label>
                                                        <div class="input-group">
                                                            <div id="example2-card-expiry" class="form-control form-control-lg border-left-1">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div class="error" role="alert">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17">
                                                            <path class="base" fill="#000" d="M8.5,17 C3.80557963,17 0,13.1944204 0,8.5 C0,3.80557963 3.80557963,0 8.5,0 C13.1944204,0 17,3.80557963 17,8.5 C17,13.1944204 13.1944204,17 8.5,17 Z"></path>
                                                            <path class="glyph" fill="#FFF" d="M8.5,7.29791847 L6.12604076,4.92395924 C5.79409512,4.59201359 5.25590488,4.59201359 4.92395924,4.92395924 C4.59201359,5.25590488 4.59201359,5.79409512 4.92395924,6.12604076 L7.29791847,8.5 L4.92395924,10.8739592 C4.59201359,11.2059049 4.59201359,11.7440951 4.92395924,12.0760408 C5.25590488,12.4079864 5.79409512,12.4079864 6.12604076,12.0760408 L8.5,9.70208153 L10.8739592,12.0760408 C11.2059049,12.4079864 11.7440951,12.4079864 12.0760408,12.0760408 C12.4079864,11.7440951 12.4079864,11.2059049 12.0760408,10.8739592 L9.70208153,8.5 L12.0760408,6.12604076 C12.4079864,5.79409512 12.4079864,5.25590488 12.0760408,4.92395924 C11.7440951,4.59201359 11.2059049,4.59201359 10.8739592,4.92395924 L8.5,7.29791847 L8.5,7.29791847 Z"></path>
                                                        </svg>
                                                        <span class="message"></span>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div
                                                        class="de clearfix elOrderProductOptionsWrapper elMargin0 ui-droppable de-editable"
                                                        style="margin-top: 15px; outline: none; cursor: pointer; font-family: 'Source Sans Pro', Helvetica, sans-serif !important;"
                                                        aria-disabled="false"
                                                        data-google-font="Source+Sans+Pro"
                                                    >
                                                        <div class="dashed orderFormBump">
                                                            <div class="sectioncontent">
                                                                <div style="">
                                                                    <div class="form-check form-check-success">
                                                                        <img src="https://assets.clickfunnels.com/templates/listhacking-sales/images/arrow-flash-small.gif" alt=""class="imgtag" data-cf-id="flashing-arrow" data-cf-note="flashing arrow" data-cf-editable-type="image"/>
                                                                        <label class="form-check-label">
                                                                            <input
                                                                                type="checkbox"
                                                                               class="form-check-input"
                                                                               name="purchase"
                                                                               id="bump-offer"
                                                                               data-cf-price="{{ $planPrice }}"
                                                                               data-cf-trial-length="{{ $trialLength }}"
                                                                               data-cf-template-price="{{ $templatePrice }}"
                                                                            >
                                                                            <i class="input-helper"></i>
                                                                        </label>
                                                                        <span class="bumpHeadline" data-cf-id="bump-headline" data-cf-note="bump headline" data-cf-editable-type="rich-text" size="4">Yes! I Want Group Launch Templates</span>
                                                                    </div>
                                                                </div>
                                                                <div class="text-center" style="text-align: center;">
                                                                    <p style="text-align: left;" data-cf-id="order-bump" data-cf-note="orderform bump" data-cf-editable-type="rich-text">
                                                                        <font style="font-size: 16px;" size="2">
                                                                            <u>
                                                                                <font style="font-size: 16px;" color="#CC3300"><b class="otoText">${{ $templatePrice }} ONE TIME OFFER</b></font>
                                                                            </u>
                                                                            :
                                                                            <span class="otoText2">
                                                                                Want to quickly surge your group with more qualified members? Then check *YES* to add our copy &amp; paste ‘Group Launch Templates’ to your order now - for only ${{ $templatePrice }} (not available anywhere at this price).
                                                                                <div
                                                                                    class="row bgCover borderSolid cornersAll radius0 shadow0 P0-top P0-bottom P0H noTopMargin borderLight border1px"
                                                                                    style="padding-top: 0px; padding-bottom: 10px; margin: 15px auto 0px; background-color: rgb(233, 255, 233); outline: none; border-color: rgb(123, 227, 123); width: 90%; max-width: 100%;"
                                                                                >
                                                                                    <div id="col-full-126" class="col-md-12 innerContent col_left" data-col="full" data-trigger="none" data-animate="fade" data-delay="500" data-title="1st column" style="outline: none;">
                                                                                        <div class="col-inner bgCover noBorder borderSolid border3px cornersAll radius0 shadow0 P0-top P0-bottom P0H noTopMargin" style="padding: 0 10px;">
                                                                                            <div
                                                                                                class="de elHeadlineWrapper ui-droppable de-editable"
                                                                                                style="margin-top: 10px; outline: none; cursor: pointer; font-family: 'Source Sans Pro', Helvetica, sans-serif !important;"
                                                                                                data-google-font="Source+Sans+Pro"
                                                                                                aria-disabled="false">
                                                                                            <div
                                                                                                class="ne elHeadline hsSize1 lh5 elMargin0 elBGStyle0 hsTextShadow0 deneg1pxLetterSpacing mfs_14"
                                                                                                data-bold="inherit"
                                                                                                style="text-align: center; font-size: 14px;"
                                                                                                data-gramm="false"
                                                                                                contenteditable="false"
                                                                                            >
                                                                                                    Almost <b>63%</b> of our most successful members <b>choose this upgrade</b>...
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </span>
                                                                        </font>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <br>
                                                <div class="col-12">
                                                    <div class="elOrderProductOptions" style="font-size: 18px;">
                                                        <div class="clearfix elOrderProductOptinLabel">
                                                            <div class="pull-left elOrderProductOptinItem">Today's Total:</div>
                                                            <div class="pull-right elOrderProductOptinLabelPrice">${{ $initialPrice }}</div>
                                                        </div>
                                                        <hr>
                                                        <div class="clearfix elOrderProductOptinProducts">
                                                            <div class="pull-left elOrderProductOptinProductName product-name" style="width: inherit;">
                                                                {{$plan->nickname}}
                                                            </div>
                                                            <div class="pull-right elOrderProductOptinPrice product-price">
                                                                {{__('Free for')}} {{$plan->product->metadata->trialLength}}-{{__('days')}}, {{__('then')}} ${{ $plan->amount/100 }}/{{__('month')}}
                                                            </div>
                                                        </div>
                                                        <div class="clearfix elOrderProductOptinProducts mt-2 oneTimePay" style="display:none;">
                                                            <div class="pull-left elOrderProductOptinProductName product-name" style="width: inherit;">Group Launch Templates</div>
                                                            <div class="pull-right elOrderProductOptinPrice product-price">${{ $templatePrice }} one-time</div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <br>
                                                <div class="col-12">
                                                    <button type="button" id="paybtn" data-tid="elements_examples.form.pay_button">Start My 14-Day Free Trial</button>
                                                </div>
                                                <div class="col-12 mt-3 text-right">
                                                    <a class="text-primary back_link" href="{{ route('plans.index') }}"> Back to plans list </a>
                                                </div>
                                                <button type="submit" id="subscription" style="display:none;"></button>
                                            </form>
                                            <div class="success" id="loderbnt" style="display:none;">
                                                <div class="loader-demo-box mt-5">
                                                    <div class="circle-loader"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-lg-6 col-sm-12 mt-3">
                                <ul class="list-unstyled plan-features font-18">
                                    <li class="li-title">
                                        {{__('Included With')}} {{__('Included With')}} {{__("$plan->nickname")}}...
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                        @if (isset($plan->product->metadata->group_limit))
                                            <b>{{$plan->product->metadata->group_limit}}</b>
                                            {{(int)$plan->product->metadata->group_limit === 1 ? __('group') : __('groups') }}
                                        @else
                                            <b>{{__('Unlimited')}}</b> {{__('groups')}}
                                        @endif
                                    </li>
                                    <li>
                                        <i class="@if((int)$plan->product->metadata->moderator_limit)
                                            mdi mdi-check-circle-outline
                                    @else
                                            remove mdi mdi-close-circle-outline
                                    @endif
                                            font-weight-bold
                                    ">
                                        </i>
                                        <b>{{$plan->product->metadata->moderator_limit}}</b>
                                        {{__('Additional')}}
                                        {{
                                            (int)$plan->product->metadata->moderator_limit === 1
                                                ? __('moderator')
                                                : __('moderators')
                                        }}
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                        @if (isset($plan->product->metadata->members_limit))
                                            <b>{{$plan->product->metadata->members_limit}}</b> {{__('member approvals per month')}}
                                        @else
                                            <b>{{__('Unlimited')}}</b> {{__('member approvals')}}
                                        @endif
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                        {{__('Private Facebook™ community')}}
                                    </li>
                                    <li>
                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                        {{__('Members-only group trainings')}}
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- content-wrapper ends -->
    </div>
    <!-- main-panel ends -->
</div>
<script src="{{ asset('asset/vendors/js/stripe_element.js') }}"></script>
@endsection
