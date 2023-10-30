@extends('layouts.main')

@section('content')
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper my-5">
                <div class="row">
                    <div class="col-md-6 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">{{__('Pause subscription')}}</h4>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            <th class="text-center">
                                                <p class="mb-2 planLabel">
                                                    @if ($isSubscriptionPauseScheduled)
                                                        {{
                                                            __('Your subscription will be paused after')
                                                            . ' '
                                                            . \Carbon\Carbon::parse($subscriptionEndDate)->subHour()->format('m-d-Y G:i:s')
                                                        }}
                                                    @elseif($isSubscriptionPaused)
                                                        {{__('Your subscription is paused.')}}
                                                    @else
                                                        {{__('Pause your subscription up to six months.')}}
                                                    @endif
                                                </p>
                                                <br/>
                                                <label class="switch">
                                                    <span class="switch-inactive">{{__('Pause')}}</span>
                                                    <input
                                                        class="pause-subscription"
                                                        type="checkbox"
                                                        @if (!$isSubscriptionPaused && !$isSubscriptionPauseScheduled)
                                                        checked
                                                        @endif
                                                    >
                                                    <span class="slider round"></span>
                                                    <span class="switch-active">{{__('Continue')}}</span>
                                                </label>
                                            </th>
                                        </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @if (!$isSubscriptionPaused && !$isSubscriptionPauseScheduled)
                    <div class="col-md-6 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">{{__('Change plan')}}</h4>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                        <tr>
                                            @if($user->hasProPlan())
                                                <label class="text-center">
                                                    <a href="javascript:void(0);"
                                                       data-toggle="modal"
                                                       data-target="#show-downgrade-plan-modal"
                                                       data-keyboard="false"
                                                       data-backdrop="static">
                                                        {{__('Downgrade Plan')}}
                                                    </a>
                                                </label>
                                            @else
                                                <label class="text-center">
                                                    <a href="javascript:void(0);" id="cancelSubscription">
                                                        {{__('Cancel your subscription')}}
                                                    </a>
                                                </label>
                                            @endif
                                        </tr>
                                        </thead>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Downgrade plan Model Start-->
    <div id="show-downgrade-plan-modal" class="modal fade" tabindex="-1" role="dialog"
         aria-labelledby="show-downgrade-plan-modal" aria-hidden="true">
        <div class="modal-dialog modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="show-downgrade-plan-modal-label">{{__('Downgrade Plan')}}</h5>
                    <button type="button" id="closeDowngradePlanModel" class="close" data-dismiss="modal"
                            aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="downgradePlan_form" name="downgradePlan_form" method="POST">
                        @csrf
                        <div class="alert alert-warning" role="alert">
                            {{__('After continuing this process you will only have access to one group, other groups will
 be deleted.')}} <br>{{__('Before proceeding export other groups data for future imports.')}}
                        </div>
                        @if($listOfActiveGroups->isNotEmpty())
                            <div class="row">
                                <div class="form-group col-md-12 listOfActiveGroups">
                                    <label for="listOfActiveGroups">Select group to remain active</label>
                                    <select class="form-control" id="listOfActiveGroups" name="listOfActiveGroups">
                                        <option value="">{{__('Select group')}}</option>
                                        @foreach($listOfActiveGroups as $activeGroup)
                                            <option value="{{$activeGroup->id}}">{{ $activeGroup->fb_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endif
                        <div class="modal-footer text-center">
                            <button type="button" class="btn btn-secondary" id="cancelDowngrade" data-dismiss="modal">
                                Cancel
                            </button>
                            <button type="button" id="saveDowngrade"
                                    class="btn btn-primary mr-2 float-right col-md-offset-8 col-md-4">
                                {{__('Save')}}
                                <i class="fa fa-spinner fa-spin color-white invisible absolute" id="loader"></i>
                            </button>
                        </div>
                        <label class="text-center">
                            <a href="javascript:void(0);" class="small" id="cancelSubscription">
                                {{__('Cancel your subscription')}}
                            </a>
                        </label>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Downgrade plan Model End-->
    <script src="{{ asset('asset/vendors/js/subscriptionOptions.js') }}"></script>
@endsection
