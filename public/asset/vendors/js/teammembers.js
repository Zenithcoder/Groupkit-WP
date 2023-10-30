const URL = $('#base_url').val();

/**
 * DOM Selector for the Facebook groups select element
 *
 * @type {string}
 */
const FACEBOOK_GROUPS_DROPDOWN_SELECTOR = '#facebook_groups_id';

$(document).ready(function(){
    /**
     * Handles click on the save team member button
     * If a team member id is provided, it calls the update team member API, otherwise, the store team member API
     */
    $('body').on('click', '#save_team_member', function () {
        if (!teamMemberDataIsValid()) {
            return;
        }

        const isUpdate = jQuery('#id').val();

        if (isUpdate) {
            updateTeamMember(isUpdate);
        } else if (!$(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).val().length) {
            // if group owner wants to create team member without group
            swal({
                text: 'Are you sure you want to add a new team member without assigning them a group?',
                buttons: {
                    cancel: {
                        text: 'NO',
                        value: null,
                        visible: true,
                        className: 'btn btn-primary',
                        closeModal: true,
                    },
                    confirm: {
                        text: 'YES',
                        value: true,
                        visible: true,
                        className: 'btn btn-danger',
                        closeModal: true,
                    },
                }
            }).then((confirm) => {
                if (confirm) {
                    storeTeamMember();
                }
            })
        } else {
            storeTeamMember();
        }
    });

    $('body').on('click', '.removeTeamMember', function () {
		var id=$(this).attr('data_id')
		swal({
			text: "Are you sure you want to do this?",
			icon: "warning",
			buttons: true,
			dangerMode: true,
		})
		.then((willDelete) => {
			if (willDelete) {
				removeTeamMember(id)
			}
		})
	});

    $('body').on('click', '.showTeamMember', function () {
      $('#full_name').show();
		$(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).prop("disabled", false);
		enableSaveTeamMemberButton();
		var id=$(this).attr('data_id');
		$("h5#exampleModalLabel-2").text("Edit Team Member");
      getTeamMember(id);
      disableTeamMemberEmailInput();
      disableTeamMemberNameInput();
	});

    $('body').on('click', '.re-send-team-member-email', reSendTeamMemberEmail);

    /**
     * Sends team member id to the resend team member invitation API
     *
     * @param {PointerEvent} event fired on the url click
     */
    function reSendTeamMemberEmail(event) {
        event.preventDefault();

        $.ajax({
            type: 'post',
            data: {
                '_token': $('input[name="_token"]').val(),
                'team_member_id': event.target.getAttribute('data-id'),
            },
            url: URL + `/team-members/re-send-invitation`,
            success: function (data) {
                showSuccessMessage(data.message);
                table.ajax.reload();
            },
            error: function (data) {
                showErrorMessage(data.responseJSON.message);
            },
        });
    }

	$(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).select2({
        placeholder: 'Select groups for this team member',
    });

	loadDatatable()
    /**
     * Determine if team member exist or not and fetch name
     */
    $("#email").blur(function () {
        /* error validation message*/
        const validationError = {
            email_team_member: "The member already exists in your team",
        };
        let email = $("#email").val();
        if (emailIsValid()) {
            $.ajax({
                type: "post",
                data: {
                    "_token": $('input[name="_token"]').val(),
                    "email": email,
                },
                url: URL + '/teamMembers/checkTeamMembersEmail',
                success: function (data) {
                    if (data.count) {
                        emailErrorAlert(validationError['email_team_member'])
                        return disableSaveTeamMemberButton();
                    } else {
                        if (!$.trim(data.data)) {
                            $("#name").val('');
                            $("#name").prop("readonly", false);
                        } else {
                            $("#name").val(data.data.name);
                            $("#name").prop("readonly", true);
                            $('#full_name').show();
                        }
                        $("#full_name").focus();
                        $('#full_name').show();
                        enableSaveTeamMemberButton();
                        $(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).prop("disabled", false);
                        // sets wrapper width for #facebook_groups_id placeholder so ti can be entire visible
                        document.querySelector('.select2-search__field').style.width = "300px";
                    }
                },
                error: function (data) {
                    emailErrorAlert(data.responseJSON.message)
                    return disableSaveTeamMemberButton();
                }
            });
        }
    });
})
$("#addTeamMemberButton").click(function(){
      enableTeamMemberEmailInput();
      disableSaveTeamMemberButton();
      $(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).prop("disabled", true);
      $('#full_name').hide();
      $('#name').prop('disabled', false);
  	$('#team_member_form')[0].reset();
  	$(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).val('').trigger('change');
  	$("#id").val('');
  	$("h5#exampleModalLabel-2").text("Add Team Member");
});
var table
function loadDatatable(){
	table=$('#order-listing').DataTable({
		"bPaginate": false,
	    "bFilter": false,
	   	"bInfo": false,
	   	"oLanguage": {
        "sEmptyTable": "You have not added anyone to your team yet"
    	},
        processing: true,
		serverSide: true,
		ajax: {
            url: URL+"/teamMembers/getData",
        },
        columns: [
            {
				data: 'name',
				name: 'name',
                render: function (name, type, row) {
                    return `
                        <div class="flex flex-col">
                            <span>${name}</span>
                            <span class="italic text-xs">
                                ${
                                    !row.has_password
                                        ? row.token_expired
                                            ? '(Pending - <a class="re-send-team-member-email" data-id="' + row.id + '" href="#">Resend Invitation</a>)'
                                            : '(Pending)'
                                    : ''
                                }
                            </span>
                        </div>
                    `;
                },
			},
			{
				data: 'email',
				name: 'email',
			},
            {
				data: 'facebook_groups_id',
				name: 'facebook_groups_id',
				"render":function(data, type, row, meta) {
					return data
				}
			},
			// {
			// 	data: 'status',
			// 	name: 'status',
   //              "render":function(data, type, row, meta) {
			// 		return data;
			// 	}
   //          },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                "render": function (data, type, row, meta) {
                    return `<a data_id="` + data + `" class="showTeamMember" ><i class="fa fa-pencil"></i></a>
							  <a data_id="` + data + `" class="removeTeamMember"><i class="fa fa-trash-o"></i></a>`
                }
            }
		],
		drawCallback: function(settings) {
			if(settings.json.recordsTotal == 0)
			{
				$('#user_count').html(settings.json.recordsTotal+' Team Members');
			}
			else if(settings.json.recordsTotal == 1)
			{
				$('#user_count').html(settings.json.recordsTotal+' Team Member');
			}
			else
			{
				$('#user_count').html(settings.json.recordsTotal+' Team Members');
			}

		}
	})
}

/**
 * Sends POST API request to store/create a new team member for the owner
 */
function storeTeamMember() {
    $.ajax({
        type: "post",
        data: getTeamMemberData(),
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: URL + '/teamMembers',
        beforeSend: setUpBeforeSaveAjaxRequest(),
        success: onSuccessSaveTeamMemberCallback,
        error: onErrorSaveTeamMemberCallback,
    });
}

/**
 * Sends PUT API request to update owners team member
 *
 * @param {int} id of the team member that will be updated
 */
function updateTeamMember(id) {
    $.ajax({
        type: 'put',
        data: getTeamMemberData(),
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: URL + '/teamMembers/' + id,
        beforeSend: setUpBeforeSaveAjaxRequest(),
        success: onSuccessSaveTeamMemberCallback,
        error: onErrorSaveTeamMemberCallback,
    });
}

/**
 * Updates the UI upon an error response from a save or an update call to the team member API
 *
 * @param {object} response represents the error reply from a save or an update call to the team member API
 */
function onErrorSaveTeamMemberCallback(...response) {
    const responseData = response[0];
    hideLoader();
    enableSaveTeamMemberButton();
    showErrorMessage(responseData.responseJSON.message);
}

/**
 * Shows the success response from a save or an update call to the team member API endpoint.
 * Hides addTeamMember modal for create/update team member
 * Hides addTeamMemberButton if the owner has reached the limit for adding new team members
 *
 * @param {object} response is the 200 OK reply after calling the team member API. It will contain an indicator of success or failure and a corresponding message.
 */
function onSuccessSaveTeamMemberCallback(...response) {
    const responseData = response[0];
    showSuccessMessage(responseData.message);
    $('#addTeamMember').modal('hide');
    table.ajax.reload();
    $(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).val('');

    if (responseData.data.hide_create_button) {
        $("#addTeamMemberButton").hide();
    }
    hideLoader();
    enableSaveTeamMemberButton();
}

/**
 * Shows provided success message in the top right corner of the screen
 *
 * @param {string} message that will be displayed
 */
function showSuccessMessage(message) {
    $.toast({
        heading: 'Success',
        text: message,
        showHideTransition: 'slide',
        icon: 'success',
        loaderBg: '#f96868',
        position: 'top-right'
    })
}

/**
 * Shows provided error message in the top right corner of the screen
 *
 * @param {string} message that will be displayed
 */
function showErrorMessage(message) {
    $.toast({
        heading: 'Error',
        text: message,
        showHideTransition: 'slide',
        icon: 'error',
        loaderBg: '#f2a654',
        position: 'top-right'
    })
}

/**
 * Shows spin loader on the screen
 */
function showLoader() {
    $("#loading-image").show();
}

/**
 * Hides spin loader on the screen
 */
function hideLoader() {
    $("#loading-image").hide();
}

/**
 * Sets next actions that will be triggered before ajax request is sent:
 * 1. Shows spin loader on the display
 * 2. Disables save team member button clickable function
 */
function setUpBeforeSaveAjaxRequest() {
    showLoader();
    disableSaveTeamMemberButton();
}

/**
 * Disables clickable function of the save team member button upon request duration
 */
function disableSaveTeamMemberButton() {
    $("#save_team_member").prop('disabled', true);
}

/**
 * Enables clickable function of the save team member button when request is finished
 */
function enableSaveTeamMemberButton() {
    $("#save_team_member").prop('disabled', false);
}

/**
 * Allow change team member email address
 */
function enableTeamMemberEmailInput() {
    $('#email').prop('disabled', false);
}

/**
 * Prevent change team member email address
 */
function disableTeamMemberEmailInput() {
    $('#email').prop('disabled', true);
}

/**
 * Prevent change name of the team member
 */
function disableTeamMemberNameInput() {
    $('#name').prop('disabled', true);
}

function getTeamMember(id){
	$.ajax({
		type: "get",
		data: {},
		url: URL + '/teamMembers/getTeamMember/'+id,
		success: function (data) {
			if (data.code == 200) {
				$('#name').val(data.user.name);
				$('#email').val(data.user.email);
				$('#id').val(data.user.id);
				$(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).val(data.fb_id).trigger('change');
				$('#addTeamMember').modal('show');
			} else {
                showErrorMessage(data.message);
			}
		},
		error: function(data){
			console.log(data)
		}
	});
}

function removeTeamMember(id){
	$.ajax({
		type: "post",
		data: {
			"_token":$('input[name="_token"]').val(),
			"id":id
		},
		url: URL + '/teamMembers/remove',
		success: function (data) {
			if (data.code == 200) {
				$.toast({
					heading: 'Success',
					text: data.message,
					showHideTransition: 'slide',
					icon: 'success',
					loaderBg: '#f96868',
					position: 'top-right'
				})

				if (data.data.hide_create_button) {
                    $("#addTeamMemberButton").hide();
                }
				table.ajax.reload()

			} else {
				$.toast({
					heading: 'Error',
					text: data.message,
					showHideTransition: 'slide',
					icon: 'error',
					loaderBg: '#f2a654',
					position: 'top-right'
				})
			}
		},
		error: function(data){
			console.log(data)
		}
	});
}

function updateStatus(id,status){
	$.ajax({
		type: "post",
		data: {
			"_token":$('input[name="_token"]').val(),
			"id":id,
			"status":status
		},
		url: URL + '/teamMembers/updateStatus',
		success: function (data) {
			if (data.code == 200) {
				$.toast({
					heading: 'Success',
					text: data.message,
					showHideTransition: 'slide',
					icon: 'success',
					loaderBg: '#f96868',
					position: 'top-right'
				})
				table.ajax.reload()

			} else {
				$.toast({
					heading: 'Error',
					text: data.message,
					showHideTransition: 'slide',
					icon: 'error',
					loaderBg: '#f2a654',
					position: 'top-right'
				})
			}
		},
		error: function(data){
			console.log(data)
		}
	});
}

/**
 * Gets the team member data for a save/update call to the team member API endpoint
 *
 * @returns {object} formatted data for save/update request to the team member API
 */
function getTeamMemberData() {
    return {
        name: $('#name').val(),
        email: $('#email').val(),
        facebook_groups_id: $(FACEBOOK_GROUPS_DROPDOWN_SELECTOR).val(),
    };
}

/**
 * Determine is team member data valid
 *
 * @return boolean true if requested data is valid, otherwise false
 */
function teamMemberDataIsValid() {
    let valid = true;

    if (!emailIsValid()) {
        valid = false;
    }

    return valid;
}

/**
 * Determine if input email is valid
 *
 * @return {boolean} true if input email is valid, otherwise false
 */
function emailIsValid() {
    let valid = true;
    const data = getTeamMemberData();

    const emailRegex = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    if (!data.email.match(emailRegex)) {
        valid = false;
        emailErrorAlert('The email must be a valid email address');
    }

    return valid;
}

/**
 * show email error
 */
function emailErrorAlert(message) {
    if ($('.email-validation').length) {
        $('.email-validation').html(message)
    } else {
        $("#email").parent().after("<div class = 'email-validation form-group row p-2 mb-0'>" + message + "</div>");
    }
    setTimeout(function () {
        $(".email-validation").remove()
    }, 3000);
}

$('#addTeamMember').on('hidden.bs.modal', function () {
    $(this).find('form').trigger('reset');
    $('#full_name').hide();
    $("#email").parent().next(".email-validation").remove();
})

/**
 * Show the auto suggested email ids to select from
 */
$("#email").autocomplete({
    source: function (request, response) {
        if (emailIsValid()) {
            $.ajax({
                url: URL + '/teamMembers/getEmail',
                type: 'post',
                dataType: "json",
                data: {
                    _token: $('input[name="_token"]').val(),
                    search: request.term
                },
                success: function (data) {
                    response(data);
                },
                error: function (data) {
                    emailErrorAlert(data.responseJSON.message)
                    return disableSaveTeamMemberButton();
                }
            });
        }
    },
    select: function (event, ui) {
        $("#email").val(ui.item.label);
        $("#email").trigger('blur');
        return false;
    }
});
