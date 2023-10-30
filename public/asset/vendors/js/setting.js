var URL=$('#base_url').val();
$(document).ready(function(){
    $('body').on('change', '.auto-renew', function () { // Auto - Renew on off switch handleEvent
		auto_renew();
	});
	$('body').on('click', '#save_user', function () { // Auto - Renew on off switch handleEvent
		updateUser();
	});
	/** Time Zone */
	$('body').on('change', '#user_timezone_change_default', function () {
		const selectedTimeZone = getTimeZoneName($(this).val());
		$("#timezone_text_default").html(selectedTimeZone);
	});
	$('body').on('click', '.autoCheck', function () {
		$('#isSet_time').val('');
		getTimeZone();
	});
    if (location.href.indexOf('plans') > 0) {
        $('.tab-pane').removeClass('active');
        $('.nav-link').removeClass('active');
        $('#plan-tab').addClass('active');
        $('#plan-1').addClass('show active');
    } else {
        $('.tab-pane').removeClass('active');
        $('.nav-link').removeClass('active');
        $('#profile-tab').addClass('active');
        $('#profile-1').addClass('show active');
    }

    /** shows update users email modal */
    $('body').on('click', '#save_email', function () {
        if (validUpdateEmailForm()) {
            let isSameAsExistingEmail = $.trim($('#user_email').val()) === $.trim($('#new_email').val());
            if (isSameAsExistingEmail) {
                $('#new_email').after(`<label id="new_email-error" class="error" for="new_email">
                The new email address is the same as the old one.</label>`);
                $('#new_email-error').show();
                setTimeout(function () {
                    $('#new_email-error').remove();
                }, 2000);
                return;
            }
            sendUpdateEmailRequest($('#new_email').val());
        }
    });
})

/**
 * Validates Update Users Email Form inputs
 */
function validUpdateEmailForm() {
    const updateEmailForm = $('#update_email_form');
    updateEmailForm.validate({
        rules: {
            new_email: {
                required: true,
                email: true,
            },
        },
        messages: {
            new_email: {
                required: 'Please provide an email address',
            },
        },
    });

    return updateEmailForm.valid();
}

/**
 * Sends API request to send update email request on user's new email address.
 *
 * @param {string} email containing new email address that user's verification email will be send to
 */
function sendUpdateEmailRequest(email) {
    $.ajax({
        type: 'POST',
        data: {'email': email},
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: URL + '/user/sendNewEmailActivationLink',
        /**
         * Handle the beforeSend response from sendUpdateEmailRequest API
         */
        beforeSend: function() {
            $('#save_email').prop('disabled', true);
            showLoader();
        },
        /**
         * Handle the success response from sendUpdateEmailRequest API
         *
         * @param {object} returns success response
         */
        success: function(...successResponse) {
            const responseData = successResponse[2];
            $('#show-update-email-modal').modal('hide');
            return successMessageToast(responseData.responseJSON.message);
        },
        /**
         * Handle the error response from sendUpdateEmailRequest API
         *
         * @param {object} returns error response
         */
        error: function(...errorResponse) {
            const responseData = errorResponse[0];
            errorMessageToast(responseData.responseJSON.message);
        },
        /**
         * Handle the complete response from sendUpdateEmailRequest API
         */
        complete: function onCompleteSendUpdateEmailRequestCallback() {
            $('#save_email').prop('disabled', false);
            $('#new_email').val('');
            hideLoader();
        },
    });
}

/**
 * Sends API request to update users details.
 */
function updateUser() {
    let formData = $('#user_form').serializeArray();
    $.ajax({
        type: 'POST',
        data: formData,
        url: URL + '/user/update',
        /**
         * Handle the beforeSend response from updateUser API
         */
        beforeSend: function() {
            $('#save_user').prop('disabled', true);
            showLoader();
        },
        /**
         * Handle the success response from updateUser API
         *
         * @param {object} returns success response
         */
        success: function(successResponse) {
            return successMessageToast(successResponse.message);
        },
        /**
         * Handle the error response from updateUser API
         *
         * @param {object} returns error response
         */
        error: function(errorResponse) {
            errorMessageToast(errorResponse.responseJSON.message);
        },
        /**
         * Handle the complete response from updateUser API
         */
        complete: function() {
            $('#save_user').prop('disabled', false);
            hideLoader();
        },
    });
}

/**
 * Shows provided error message in the top right corner of the screen
 *
 * @param {string} message that will be displayed
 */
function errorMessageToast(message) {
	$.toast({
		heading: 'Error',
		text: message,
		showHideTransition: 'slide',
		icon: 'error',
		loaderBg: '#f2a654',
		position: 'top-right'
	})
}

function auto_renew() {
	$.ajax({
		type: "get",
		data: {},
		url: URL + '/autorenewplan',
		success: function (data) {
			if (data.status === "success") {
                successMessageToast(data.message);
                $('.planLabel').text(data.data.planLabel);
			} else {
                errorMessageToast(data.message);
			}
		}
	});
}

// this function set a Timezone value.
getTimeZone();
function getTimeZone() {
	let selectedTimeZoneValue = $('#isSet_time').val();
	let selectedTimeZoneText;
	timezoneFillValues()
	if (selectedTimeZoneValue) {
		selectedTimeZoneText = getTimeZoneName(selectedTimeZoneValue);
	} else {
		let tzDatabaseName	= moment.tz.guess(true);
		selectedTimeZoneText = getTimeZoneName(tzDatabaseName);
	}
	selectedTimeZoneValue	= $('#user_timezone_change_default option').
			filter(function () {
				return $(this).text() == selectedTimeZoneText }
			).val();
	$("#user_timezone_change_default").val(selectedTimeZoneValue);
	$("#timezone_text_default").html(selectedTimeZoneText);
	$("#user_timezone_change_default").trigger('change');
}

// this function set TimeZone list UI in select-picker
async function timezoneFillValues() {
	$("#user_timezone_change_default").html("");
    for (let i = 0; i < timezones_global_data.length; i++) {
        $("#user_timezone_change_default").append(
            "<option value=\"" + timezones_global_data[i].utc[0] + "\">" +
            timezones_global_data[i].text.trim() + "</option>"
        );
    }
	$(".js-example-basic-multiple").select2();
}

/**
 * Shows provided success message in the top right corner of the screen
 *
 * @param {string} message that will be displayed
 */
function successMessageToast(message) {
    $.toast({
        heading: 'Success',
        text: message,
        showHideTransition: 'slide',
        icon: 'success',
        loaderBg: '#f96868',
        position: 'top-right',
    });
}

/**
 * Shows spinner loader on the submit button
 */
function showLoader() {
    document.getElementById('loader').classList.remove('invisible');
}

/**
 * Hides spinner loader on the submit button
 */
function hideLoader() {
    document.getElementById('loader').classList.add('invisible');
}

$('body').on('click', '#upgradeToProPlan', function () { // upgrade subscripation plan handle Event
    swal({
        text: 'Are you sure you want to upgrade your subscription?',
        buttons: {
            cancel: {
                text: 'No',
                value: null,
                visible: true,
                className: 'btn btn-danger',
                closeModal: true,
            },
            confirm: {
                text: 'Yes, go ahead',
                value: true,
                visible: true,
                className: 'btn btn-primary',
                closeModal: true,
            },
        }
    }).then(function (confirm) {
        if (confirm) {
            upgradeToProPlan();
        }
    })
});

/**
 * Upgrades user basic plan subscriptions to Pro Plan subscriptions
 */
function upgradeToProPlan() {
    $.ajax({
        type: 'POST',
        dataType: 'json',
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: '/upgradeToProPlan',
        /**
         * Disables the "Upgrade Your Plan" button once user proceeds with the "Upgrade to Pro Plan Subscription" process
         * and shows loader on that button until process is not completed.
         */
        beforeSend: function() {
            $('#upgradeToProPlan').prop('disabled', true);
            showLoader();
        },
        /**
         * Shows a success message once the request has succeeded and refreshes the page after 3 seconds.
         *
         * @param {object} successResponse returns success response
         */
        success: function(successResponse) {
            successMessageToast(successResponse.message);
            setTimeout(location.reload(), 3000);
        },
        /**
         * Shows an error message to users if our request has failed.
         *
         * @param {object} errorResponse returns error response
         */
        error: function(errorResponse) {
            $('#upgradeToProPlan').prop('disabled', false);
            errorMessageToast(errorResponse.responseJSON.message);
        },
        /**
         * Calls every time once our request is completed and enables the "Upgrade Your Plan" button and also hides loader.
         */
        complete: function() {
            hideLoader();
        },
    });
}
