@extends('layouts.main')

@section('content')
<link rel="stylesheet" href="{{ asset('asset/vendors/css/stripe.css') }}">
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="container text-center pt-0">
                                <h2 class="mb-3 mt-0">{{__('Select Your Plan To Get Started')}}...</h2>
                                <div class="row pricing-table justify-content-center">
                                    @foreach($plans as $key => $plan)
                                    <div class="col-md-5 grid-margin stretch-card pricing-card">
                                        <div class="card border-primary border pricing-card-body">
                                            <div class="text-center pricing-card-head">
                                                <h3 class="p-2">{{__("$plan->nickname")}}</h3>
                                                <h1 class="font-weight-normal mb-4">${{ $plan->amount/100 }}/{{__('Month')}}</h1>
                                            </div>
                                            <ul class="list-unstyled plan-features font-18">
                                                <li>{{$plan->product->metadata->trialLength}}
                                                    {{ (int)$plan->product->metadata->trialLength === 1 ? __('day') : __('days')}},
                                                    {{ __('free')}}
                                                    {{__('then just')}}
                                                    ${{$plan->amount/100}}/{{__('mo')}}. {{__('Cancel anytime')}}.
                                                </li>
                                                <li>{{__('Included With')}} {{__("$plan->nickname")}}</li>
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
                                                    {{__('Additional')}} {{(int)$plan->product->metadata->moderator_limit === 1 ? __('moderator') : __('moderators') }}
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
                                            <div class="wrapper">
                                                <a
                                                    href="{{ route('plans.show', base64_encode($plan->id)) }}"
                                                    class="btn btn-{{$plan->product->metadata->has_blue_submit_button ? 'primary' : 'outline-primary'}} btn-block"
                                                >
                                                    {{('Start My')}} {{$plan->product->metadata->trialLength}}-{{('Day Free Trial')}}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
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
@endsection
