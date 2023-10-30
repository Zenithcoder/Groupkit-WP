@extends('layouts.main')

@section('content')

<link rel="stylesheet" type="text/css" href="{{ asset('asset/vendors/jqueryui/jquery-ui.min.css')}}">
<style>
    i {display: inline-block;text-align: center;cursor: pointer;}
    .filter_btn i {color: #ffffff;font-size: 15px;width: 30px;}
    .add_team_member i {color: #ffffff;font-size: 15px;width: 30px;}
    .showTeamMember i {font-size: 20px;}
    .changePassword i {font-size: 20px;}
    .select2-search.select2-search--dropdown {display: none;}
    .removeTeamMember i,.removGroup i,.deActivate i {color: red;font-size: 20px;}
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 26px;
        position: absolute;
        top: 9px;
        right: 1px;
        width: 30px;
    }
    .ui-autocomplete {
        z-index: 10000;
        height: 200px;
        overflow-y: scroll;
        overflow-x: hidden;
    }
</style>
<div class="container-fluid page-body-wrapper">
    <div class="main-panel">
        <div class="content-wrapper my-5">
            <div class="row">
                <div class="col-md-12 grid-margin stretch-card">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <h4 class="card-title col-6" id="user_count"></h4>
                                <div class="col-6 text-right mb-2">
                                    @if($user->canAddTeamMembers())
                                    <button type="button" class="btn btn-primary add_team_member" data-toggle="modal" data-target="#addTeamMember" id="addTeamMemberButton"><i class="fa fa-user"></i>Add</button>
                                    @endif
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="table-responsive">
                                        <table id="order-listing" class="table">
                                            <thead>
                                                <tr>
                                                    <th>FULL NAME</th>
                                                    <th>EMAIL ADDRESS</th>
                                                    <th>GROUP ACCESS</th>
                                                    <th>MANAGE</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
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
<div class="modal fade" id="addTeamMember" tabindex="-1" role="dialog" aria-labelledby="addTeamMember_modal" aria-hidden="true">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel-2">Add Team Member</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="" id="team_member_form" name="user_form" method="post">
                    @csrf
                    <div class="form-group row p-2 mb-0">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" class="form-control" id="email" placeholder="Email Address">
                    </div>
                    <div class="form-group row p-2 mb-0" id="full_name">
                        <label for="name">Full Name</label>
                        <input type="text" name="name" class="form-control" id="name" placeholder="Full Name">
                    </div>
                    <div class="form-group row p-2 mb-0">
                        <label for="facebook_groups_id">Group Access</label>
                        <select
                            id="facebook_groups_id"
                            name="facebook_groups_id[]"
                            class="form-control"
                            placeholder="Select Group Access"
                            multiple="multiple"
                        >
                            @foreach($group as $groupData)
                            <option value="{{$groupData->id}}">{{ $groupData->fb_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="id" id="id">
                    <button type="button" id="save_team_member" class="btn btn-primary mr-2">
                        Submit
                        <i class="fa fa-spinner fa-spin" id="loading-image" style="color: white; display: none;"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="{{ asset('asset/vendors/jqueryui/jquery-ui.min.js') }}"></script>
<script src="{{ asset('asset/vendors/js/teammembers.js') }}"></script>
@endsection
