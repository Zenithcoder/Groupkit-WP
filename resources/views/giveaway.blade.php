@extends('layouts.main')
@section('content')
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="wheel"></div>
                                </div>
                                <div class="col-md-6">
                                    <h2 id="rafle_title">
                                        HOST A GIVEAWAY
                                    </h2>
                                    <div id="text_wrapper">
                                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                    </div>
                                    </br>
                                    <div id="raffle_explain">Type the names below & then click the button to begin:</div>
                                    <div class="form-group" id="input_wrapper">
                                        <textarea class="form-control" rows="5" id="wheel_input_data"></textarea>
                                    </div>
                                    <div id="button_wrapper">
                                        <button id="wrapper_start_it" type="button" class="btn btn-primary">START THE GIVEAWAY</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<link href="{{ asset('asset/vendors/css/superwheel.min.css') }}" rel="stylesheet" />
<link href="{{ asset('asset/vendors/css/giveaway.css') }}" rel="stylesheet" />
<script src="{{ asset('asset/vendors/js/jquery.superwheel.min.js') }}"></script>
<script src="{{ asset('asset/vendors/js/giveaway.js') }}"></script>
@endsection