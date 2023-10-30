@extends('layouts.main')

@section('content')
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper my-5">
                <div class="row">
                    <div class="col-md-12 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <ul class="nav nav-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a
                                            class="nav-link"
                                            id="profile-tab"
                                            data-toggle="tab"
                                            href="#profile-1"
                                            role="tab"
                                            aria-controls="profile-1"
                                            aria-selected="false"
                                        >
                                            {{__('My Profile')}}</a>
                                    </li>
                                    @if(!$userPlanIsNotAvailable)
                                        <li class="nav-item">
                                            <a
                                                class="nav-link"
                                                id="plan-tab"
                                                data-toggle="tab"
                                                href="#plan-1"
                                                role="tab"
                                                aria-controls="plan-1"
                                                aria-selected="true"
                                            >
                                                {{__('My Subscription')}}</a>
                                        </li>
                                    @endif
                                    @if($card)
                                        <li class="nav-item">
                                            <a
                                                id="card-tab"
                                                class="nav-link"
                                                data-toggle="tab"
                                                href="#my-card"
                                                role="tab"
                                                aria-controls="my-card"
                                                aria-selected="false"
                                            >
                                                {{__('My Card')}}
                                            </a>
                                        </li>
                                    @endif
                                </ul>
                                <div class="tab-content">
                                    <div class="tab-pane fade" id="profile-1" role="tabpanel"
                                         aria-labelledby="profile-tab">
                                        <form class="forms-sample" id="user_form" name="user_form" method="post">
                                            @csrf
                                            <h4 class="card-title">Your Profile Details</h4>
                                            <div class="row">
                                                <div class="form-group col-6">
                                                    <label for="firstName">First Name</label>
                                                    <input
                                                        type="text"
                                                        name="first_name"
                                                        class="form-control"
                                                        value="{{ $user->first_name }}"
                                                        placeholder="First Name"
                                                        id="firstName"
                                                    >
                                                </div>
                                                <div class="form-group col-6">
                                                    <label for="lastName">Last Name</label>
                                                    <input
                                                        type="text"
                                                        name="last_name"
                                                        class="form-control"
                                                        value="{{ $user->last_name }}"
                                                        placeholder="Last Name"
                                                        id="lastName"
                                                    >
                                                </div>
                                                <div class="form-group col-6">
                                                    <label for="emailAddress">Email address</label>
                                                    <div class="input-group">
                                                        <input type="email"
                                                               name="email"
                                                               class="form-control"
                                                               id="emailAddress"
                                                               value="{{$user->email}}"
                                                               placeholder="Email"
                                                               readonly
                                                        />
                                                        <div class="input-group-append">
                                                            <i data-toggle="modal"
                                                               data-target="#show-update-email-modal"
                                                               class="fa fa-pencil p-3"
                                                               data-keyboard="false"
                                                               data-backdrop="static">
                                                            </i>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="form-group col-6">
                                                    <input type="hidden" id="isSet_time" value="{{$user->timezone}}">
                                                    <label for="user_timezone_change_default">Time Zone
                                                        <i class="fa fa-refresh autoCheck" aria-hidden="true"></i>
                                                    </label>
                                                    <select class="js-example-basic-multiple" name="timeZone"
                                                            id="user_timezone_change_default"></select>
                                                    </br>
                                                    </br>
                                                    <label id="timezone_text_default">{{$user->timezone}}</label>
                                                </div>
                                            </div>
                                            <hr>
                                            <h4 class="card-title">Add New Password</h4>
                                            <div class="row">
                                                <div class="form-group col-6">
                                                    <label for="password">Password</label>
                                                    <input
                                                        type="password"
                                                        name="password"
                                                        class="form-control"
                                                        id="password"
                                                        placeholder="**************"
                                                    >
                                                </div>
                                                <div class="form-group col-6">
                                                    <label for="confirmPassword">Confirm Password</label>
                                                    <input
                                                        id="confirmPassword"
                                                        type="password"
                                                        name="confirmed"
                                                        class="form-control"
                                                        placeholder="**************"
                                                    >
                                                </div>
                                            </div>
                                            <button type="button" id="save_user" class="btn btn-primary mr-2">Save
                                            </button>
                                        </form>
                                        <!-- Show "Upgrade your plan" option only for Moderators (team members) role -->
                                        @if($userPlanIsNotAvailable)
                                            <hr>
                                            <div class="row">
                                                <div class="col-12">
                                                    <a class="btn btn-primary"
                                                       id="upgradePlan"
                                                       href="{{route('plans.index')}}">
                                                        {{__('Upgrade Your Plan')}}
                                                    </a>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    @if(!$userPlanIsNotAvailable)
                                        <div class="tab-pane fade" id="plan-1" role="tabpanel"
                                             aria-labelledby="plan-tab">
                                            <div class="{{ !$userHasProPlan ? 'row' : '' }}">
                                                <div
                                                    class="{{ $userHasProPlan ? 'col-lg-12' : 'col-lg-6' }} grid-margin stretch-card">
                                                    <div class="card">
                                                        <div class="card-body">
                                                            <h4 class="card-title">Active Plans</h4>
                                                            <div class="table-responsive">
                                                                <table class="table">
                                                                    <thead>
                                                                    <tr>
                                                                        <th>NAME</th>
                                                                        <th>START DATE</th>
                                                                        <th>RENEWAL DATE</th>
                                                                    </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                    <tr>
                                                                        <td class="text-left">
                                                                            {{ $plan->name }}
                                                                        </td>
                                                                        <td>
                                                                            {{ $plan->current_period_start }}
                                                                        </td>
                                                                        <td>
                                                                            {{ $price->unit_amount ? $plan->current_period_end : '/' }}
                                                                        </td>
                                                                    </tr>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                @if(!$userHasProPlan && $isRecurringPlan)
                                                    <div class="col-lg-6 grid-margin stretch-card">
                                                        <!-- Show "Upgrade to Pro" option only For Basic Plan customers -->
                                                        <div class="card box-shadow">
                                                            <div class="card-header">
                                                                <h4 class="my-0 font-weight-normal">
                                                                    {{__('Upgrade to Pro Plan')}}
                                                                </h4>
                                                            </div>
                                                            <div class="card-body">
                                                                <ul class="list-unstyled">
                                                                    <li>
                                                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                                                        <b>{{__('Unlimited')}}</b> {{__('groups')}}
                                                                    </li>
                                                                    <li>
                                                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                                                        <b>10</b> {{__('Additional moderators')}}
                                                                    </li>
                                                                    <li>
                                                                        <i class="mdi mdi-check-circle-outline font-weight-bold"></i>
                                                                        <b>{{__('Unlimited')}}</b> {{__('member approvals')}}
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
                                                                <a
                                                                    class="btn btn-primary btn-lg"
                                                                    id="upgradeToProPlan"
                                                                    href="javascript:void(0);"
                                                                >
                                                                    {{__('Upgrade Your Plan')}}
                                                                    <i
                                                                        class="fa fa-spinner fa-spin color-white invisible absolute"
                                                                        id="loader"
                                                                    ></i>
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                @if(!$userPlanIsNotAvailable && $isRecurringPlan)
                                                    <div class="col-lg-6">
                                                        <label class="text-center">
                                                            <a href="{{route('subscriptionOptions')}}">
                                                                {{__('Subscription options')}}
                                                            </a>
                                                        </label>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                    @if($card)
                                        <div class="tab-pane fade" id="my-card" role="tabpanel"
                                             aria-labelledby="bill-tab">
                                            <div class="col-lg-12 grid-margin stretch-card">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <h4 class="card-title">{{__('Card Details')}}</h4>
                                                        <div class="table-responsive">
                                                            <table class="table">
                                                                <thead>
                                                                <tr>
                                                                    <th>{{__('Card Type')}}</th>
                                                                    <th>{{__('Last 4 Digits')}}</th>
                                                                    <th>{{__('Expiration')}}</th>
                                                                    <th>{{__('Action')}}</th>
                                                                </tr>
                                                                </thead>
                                                                <tbody>
                                                                <tr>
                                                                    <td>{{ $card->brand }}</td>
                                                                    <td>{{ $card->last4 }}</td>
                                                                    <td>{{ $card->exp_month }}
                                                                        / {{ $card->exp_year }}</td>
                                                                    <td>
                                                                        <i data-toggle="modal"
                                                                           data-target="#show-update-card-modal"
                                                                           class="fa fa-pencil p-3">
                                                                        </i>
                                                                    </td>
                                                                </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($user->hasStripeId() && $card)
        <!-- Update Users Card Model Start-->
        <div
            id="show-update-card-modal"
            class="modal fade cell example example1"
            tabindex="-1"
            role="dialog"
            aria-labelledby="show-update-card-modal"
            aria-hidden="true"
        >
            <div class="modal-dialog modal-md" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="show-update-email-modal-label">{{__('Update Card')}}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="setup-form">
                            <div id="card-element"></div>
                            <div id="stripe_errors" class="color-red hidden text-center p-2"></div>
                            <div class="modal-footer text-center">
                                <button type="submit" class="btn btn-primary">
                                    {{__('Save')}}
                                    <i class="fa fa-spinner fa-spin color-white invisible absolute" id="loader"></i>
                                </button>
                                <button type="button" class="btn btn-light" data-dismiss="modal">
                                    {{__('Cancel')}}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Update Users Card Model End-->
        <form method="post" action="{{route('settings.updateCard')}}" id="updateCartForm">
            @csrf
            <input name="paymentMethod" id="payment_method_id" type="hidden"/>
        </form>
    @endif

    <!-- Update Users email Model Start-->
    <div id="show-update-email-modal" class="modal fade" tabindex="-1" role="dialog"
         aria-labelledby="show-update-email-modal" aria-hidden="true">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="show-update-email-modal-label">Update Users Email</h5>
                    <button type="button" id="closeUpdateEmailModel" class="close" data-dismiss="modal"
                            aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="" id="update_email_form" name="update_email_form" method="post">
                        @csrf
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="user_email">Email Address</label>
                                <input
                                    id="user_email"
                                    type="email"
                                    name="email"
                                    class="form-control"
                                    value="{{$user->email}}"
                                    placeholder="Email Address"
                                    readonly
                                />
                            </div>
                        </div>
                        <div class="row">
                            <div class="form-group col-md-12">
                                <label for="new_email">New Email Address</label>
                                <input
                                    id="new_email"
                                    type="email"
                                    name="new_email"
                                    class="form-control"
                                    placeholder="New Email Address"
                                    required
                                />
                            </div>
                        </div>
                        <div class="modal-footer text-center">
                            <button type="button" id="save_email"
                                    class="btn btn-primary mr-2 float-right col-md-offset-8 col-md-4">
                                {{__('Save')}}
                                <i class="fa fa-spinner fa-spin color-white invisible absolute" id="loader"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Users email Model End-->
    <script src="{{ mix('js/moment_min.js') }}"></script>
    <script src="{{ asset('js/global.js') }}"></script>
    <script src="{{ asset('asset/vendors/js/setting.js') }}"></script>
    @if($card)
        <script src="https://js.stripe.com/v3/"></script>
        <script type="application/javascript">
            if ($("#card-element").length) {
                const stripe = Stripe('{{config('services.stripe.default.key')}}');
                let elements = stripe.elements({
                    fonts: [
                        {
                            cssSrc: 'https://fonts.googleapis.com/css?family=Source+Code+Pro',
                        },
                    ],
                    // Stripe's examples are localized to specific languages, but if
                    // you wish to have Elements automatically detect your user's locale,
                    // use `locale: 'auto'` instead.
                    locale: 'auto',
                });
                // Stripe card styles
                let elementStyles = {
                    base: {
                        color: '#32325D',
                        fontWeight: 500,
                        fontFamily: 'Source Code Pro, Consolas, Menlo, monospace',
                        fontSize: '16px',
                        fontSmoothing: 'antialiased',
                        '::placeholder': {
                            color: '#ced4da',
                        },
                        ':-webkit-autofill': {
                            color: '#e39f48',
                        },
                    },
                    invalid: {
                        color: '#E25950',
                        '::placeholder': {
                            color: '#FFCCA5',
                        },
                    },
                };
                // Stripe card classes
                let elementClasses = {
                    focus: 'focused',
                    empty: 'empty',
                    invalid: 'invalid',
                };
                // Create Stripe card
                let cardElement = elements.create('card', {style: elementStyles, classes: elementClasses});
                cardElement.mount('#card-element');
                let cardholderName = '{{$user->name}}';
                let setupForm = document.getElementById('setup-form');
                /**
                 * Binds event listener to the Stripe card submit
                 */
                setupForm.addEventListener('submit', function (e) {
                    let submitButton = e.submitter;
                    showLoader();
                    submitButton.disabled = true; //disable submit button to prevent double confirm card submits
                    e.preventDefault();
                    stripe.confirmCardSetup(
                        setupForm.dataset.secret,
                        {
                            payment_method: {
                                card: cardElement,
                                billing_details: {
                                    name: cardholderName.value,
                                },
                            },
                        }
                    ).then(function (result) {
                        if (result.error) {
                            hideLoader();
                            submitButton.disabled = false;
                            let stripeErrors = document.getElementById('stripe_errors')
                            stripeErrors.innerHTML = result.error.message;
                            return window.setTimeout(function () {
                                stripeErrors.innerHTML = ''; // Removes Stripe card error after 5 seconds
                            }, 5000);
                        }
                        // submit update cart for customer form after success card confirmation
                        document.getElementById('payment_method_id').value = result.setupIntent.payment_method;
                        document.getElementById('updateCartForm').submit();
                    }).catch(() => {
                        hideLoader();
                        errorMessageToast('{{__('Something went wrong, please try again')}}');
                        submitButton.disabled = false;
                    });
                });
                // Shows error message from the session
                if ('{{session('error')}}') {
                    errorMessageToast('{{session('error')}}');
                }
                $(document).ready(function () {
                    const showUpdateCardModal = $('#show-update-card-modal');
                    showUpdateCardModal.on('hide.bs.modal', function () {
                        cardElement.clear(); // clears card data on hide modal event
                        document.getElementById('stripe_errors').innerHTML = ''; // clears stripe errors if exists
                    });
                    showUpdateCardModal.on('show.bs.modal', setCardUpdateFormSecret);
                    // Shows success message from the session
                    @if(session('success'))
                    successMessageToast('{{session('success')}}');
                    $('#card-tab').tab('show');
                    @endif
                });

                /**
                 * Sets card update form secret if that form doesn't have client secret
                 */
                function setCardUpdateFormSecret() {
                    const cardUpdateForm = document.getElementById('setup-form');
                    if (!cardUpdateForm.getAttribute('data-secret')) {
                        $.ajax({
                            headers: {
                                'X-CSRF-TOKEN': '{{csrf_token()}}',
                            },
                            type: 'GET',
                            url: '{{route('settings.getClientSecret')}}',
                            success: function (res) {
                                cardUpdateForm.setAttribute('data-secret', res.clientSecret);
                            }
                        });
                    }
                }
            }
        </script>
    @endif
@endsection
