@extends('layouts.main')

@section('content')
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            @if(auth()->user()->activePlanDetails()->stripe_status=='trialing')
                                <div class="container text-center pt-0">
                                    <h2 class="mb-3 mt-0">Account Created Successfully</h2>                                
                                    <p style="text-align: center;">Your 14 days trial period begins today. You can start using Groupkit now.</p>
                                    <a class="btn btn-sm btn-primary" href="{{route('home')}}">Home</a>
                                </div>                                
                            @else
                                <div class="container text-center pt-0">
                                    <h2 class="mb-3 mt-0">Payment Successfully</h2>                                
                                    <p style="text-align: center;">your payment was successful! you can now continue using Groupkit.</p>
                                    <a class="btn btn-sm btn-primary" href="{{route('home')}}">Home</a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection