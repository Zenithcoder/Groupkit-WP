@extends('layouts.main')

@section('content')
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="container text-center pt-0">
                                <h2 class="mb-3 mt-0">Payment Unsuccessful</h2>                                
                                <p style="text-align: center;">{{ $message }}</p>
                                <a class="btn btn-sm btn-primary" href="{{route('home')}}">Home</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection