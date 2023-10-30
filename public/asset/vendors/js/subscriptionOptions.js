$(document).ready(function () {
    $('body').on('change', '.pause-subscription', function () {
        pauseOrContinueSubscription();
    });

    $('body').on('click', '#cancelSubscription', function () {
        // Cancel subscription handle Event
        swal({
            text: 'Are you sure you want to cancel your subscription? \n\n' +
                'WARNING: This will revoke your access to the system and all associated data.  Please backup your data before this operation so that you will be able to restore your account with this data at future date! ',
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
                cancelSubscription();
            }
        })
    });

    /**
     * Resets downgrade plan form.
     */
    $('body').on('click', '#cancelDowngrade,#closeDowngradePlanModel', function () {
        resetDowngradeFormElements();
    });

    /**
     * Stores active groups for downgrade plan modal
     */
    $('body').on('click', '#saveDowngrade', function () {
        swal({
            text: 'Are you sure you want to downgrade your plan? \n\n' +
                'WARNING: This will remove all other groups except the one which you have designated to remain as active.  ' +
                'This action is not reversible.',
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
                downgradeToBasicPlan();
            }
        });
    });
});

/**
 * Resets downgrade plan form and manage buttons inside modal.
 */
function resetDowngradeFormElements() {
    $('#downgradePlan_form').trigger('reset');
}

/**
 * Shows provided error message in the top right corner of the screen.
 *
 * @param {string} message that will be displayed.
 */
function errorMessageToast(message) {
    $.toast({
        heading: 'Error',
        text: message,
        showHideTransition: 'slide',
        icon: 'error',
        loaderBg: '#f2a654',
        position: 'top-right',
    });
}

/**
 * Shows provided success message in the top right corner of the screen.
 *
 * @param {string} message that will be displayed.
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
 * Shows spinner loader on the submit button.
 */
function showLoader() {
    document.getElementById('loader').classList.remove('invisible');
}

/**
 * Hides spinner loader on the submit button.
 */
function hideLoader() {
    document.getElementById('loader').classList.add('invisible');
}

/**
 * Cancels the current user's subscription
 */
function cancelSubscription() {
    $.ajax({
        type: 'GET',
        url: '/cancelSubscription',
        success: onSuccessCancelSubscriptionCallback,
        error: onErrorCancelSubscriptionCallback,
    });
}

/**
 * Shows success message and refreshes the page after 3 seconds.
 *
 * @param {object} contains success response including message.
 */
function onSuccessCancelSubscriptionCallback(successResponse) {
    successMessageToast(successResponse.message);

    setTimeout(location.reload(), 3000);
}

/**
 * Shows error message to the user if our request is failed.
 *
 * @param {object} contains error response including message.
 */
function onErrorCancelSubscriptionCallback(errorResponse) {
    errorMessageToast(errorResponse.responseJSON.message);
}

/**
 * Pauses or continue subscription plan.
 */
function pauseOrContinueSubscription() {
    $.ajax({
        type: 'POST',
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        url: 'subscription/pauseOrContinueSubscription',
        success: function (successResponse) {
            successMessageToast(successResponse.message);
            setTimeout(location.reload(), 3000);
        },
        error: function (errorResponse) {
            errorMessageToast(errorResponse.responseJSON.message);
        },
    });
}

/**
 * downgrade users pro plan subscriptions to basic Plan subscriptions
 */
function downgradeToBasicPlan() {
    $.ajax({
        type: 'POST',
        dataType: 'json',
        data: $('#downgradePlan_form').serialize(),
        url: 'subscription/downgradeToBasicPlan',
        beforeSend:
            /**
             * That disable "Cancel" & "Save" button once user proceed with Downgrade To Basic Plan process
             * and shows loader on that Save button until process is not completed.
             */
            function () {
                $('#cancelDowngrade').prop('disabled', true);
                $('#saveDowngrade').prop('disabled', true);

                showLoader();
            },
        success:
            /**
             * showing success message once request is succeed and reset the form & refresh page after 3 seconds.
             *
             * @param {object} successResponse returns success response
             */
             function (successResponse) {
                successMessageToast(successResponse.message);
                resetDowngradeFormElements();
                setTimeout(location.reload(), 3000);
            },
        error:
            /**
             * Showing error message to user if our request is failed.
             *
             * @param {object} errorResponse returns error response
             */
            function (errorResponse) {
                errorMessageToast(errorResponse.responseJSON.message);
            },
        complete:
            /**
             * Calls every time once our request is completed and enables "Cancel" & "Save" button
             * and close the popup modal and also hides loader.
             */
             function () {
                $('#cancelDowngrade').prop('disabled', false);
                $('#saveDowngrade').prop('disabled', false);
                $('#show-downgrade-plan-modal').modal('hide');
                hideLoader();
            },
    });
}







