/* enter keypress event */
$(document).keypress(
  function(event){
    if (event.which == '13') {
      event.preventDefault();
      return false;
    }
});

/**
 * Selector for the first name field
 *
 * @type {string}
 */
const FIRST_NAME_SELECTOR = '#first_name';

/**
 * Selector for the last name field
 *
 * @type {string}
 */
const LAST_NAME_SELECTOR = '#last_name';

$(document).ready(function() {
    if (location.href.indexOf('token') >= 0) {
        document.getElementById('example-1').classList.add("submitting");
        document.getElementById('loderbnt').style.display = 'block'
        document.getElementById("payment-form").submit();
    }
});

function registerElements(elements, exampleName) {
  var formClass = "." + exampleName;
  var example = document.querySelector(formClass);

  var form = example.querySelector("form");
  var resetButton = example.querySelector("a.reset");
  var error = form.querySelector(".error");
  var errorMessage = error.querySelector(".message");

  function enableInputs() {
      Array.prototype.forEach.call(form.querySelectorAll("input[type='text'], input[type='email'], input[type='tel']"), function (input) {
          input.removeAttribute("disabled");
      });
  }

  function disableInputs() {
      Array.prototype.forEach.call(form.querySelectorAll("input[type='text'], input[type='email'], input[type='tel']"), function (input) {
          input.setAttribute("disabled", "true");
      });
  }

  function triggerBrowserValidation() {
      // The only way to trigger HTML5 form validation UI is to fake a user submit
      // event.
      var submit = document.createElement("input");
      submit.type = "submit";
      submit.style.display = "none";
      form.appendChild(submit);
      submit.click();
      submit.remove();
  }

  // Listen for errors from each Element, and show error messages in the UI.
  var savedErrors = {};
  elements.forEach(function (element, idx) {
      element.on("change", function (event) {
          if (event.error) {
              error.classList.add("visible");
              savedErrors[idx] = event.error.message;
              errorMessage.innerText = event.error.message;
          } else {
              savedErrors[idx] = null;

              // Loop over the saved errors and find the first one, if any.
              var nextError = Object.keys(savedErrors)
                  .sort()
                  .reduce(function (maybeFoundError, key) {
                      return maybeFoundError || savedErrors[key];
                  }, null);

              if (nextError) {
                  // Now that they've fixed the current error, show another one.
                  errorMessage.innerText = nextError;
              } else {
                  // The user fixed the last error; no more errors.
                  error.classList.remove("visible");
              }
          }
      });
  });

  // Listen on the form's 'submit' handler...
  //form.addEventListener('submit', function(e) {
  document.getElementById("paybtn").addEventListener("click", function (e) {
      e.preventDefault();

      // Trigger HTML5 validation UI on the form if any of the inputs fail
      // validation.
      var plainInputsValid = true;
      Array.prototype.forEach.call(form.querySelectorAll("input"), function (input) {
          if (input.checkValidity && !input.checkValidity()) {
              plainInputsValid = false;
              return;
          }
      });
      if (!plainInputsValid) {
          triggerBrowserValidation();
          return;
      }

      // Show a loading screen...
      example.classList.add("submitting");

      // Disable all inputs.
      var lastName = form.querySelector(LAST_NAME_SELECTOR);
      if(!lastName) {
        disableInputs();
      }
      // Gather additional customer data we may have collected in our form.
      var name = form.querySelector(FIRST_NAME_SELECTOR);
      var address1 = form.querySelector("#" + exampleName + "-address");
      var city = form.querySelector("#" + exampleName + "-city");
      var state = form.querySelector("#" + exampleName + "-state");
      //var zip = form.querySelector("#" + exampleName + "-zip");
      var additionalData = {
          name: name ? name.value : undefined,
          address_line1: address1 ? address1.value : undefined,
          address_city: city ? city.value : undefined,
          address_state: state ? state.value : undefined,
          //address_zip: zip ? zip.value : undefined,
      };

    // stripe.createToken(elements[0], additionalData).then(function(result) {
    //     example.classList.remove('submitting');
    //     if (result.token) {
    //         example.querySelector("#token").value = result.token.id;
    //         document.querySelector('#subscription').click()
    //     } else {
    //       // Otherwise, un-disable inputs.
    //       enableInputs();
    //     }
    // });
    document.getElementById('loderbnt').style.display='block'
    stripe.createPaymentMethod("card", elements[0], { billing_details: { name: name.value } }).then(function (result) {
        //Stop loading!
        if (result.paymentMethod) {
            // If we received a token, show the token ID.
            example.querySelector("#token").value = result.paymentMethod.id;
            //example.classList.add('submitted');
            document.querySelector("#subscription").click();
        } else {
            //we have handled a payment token error
            if (result.error) {
              let error = form.querySelector(".error");
              let errorMessage = error.querySelector(".message");
              error.classList.add("visible");
              errorMessage.innerText = result.error.message;
            }
            // Otherwise, un-disable inputs.
            enableInputs();
            example.classList.remove("submitting");
        }
    });
  });
}

//card element code
var elements = stripe.elements({
  fonts: [
    {
      cssSrc: 'https://fonts.googleapis.com/css?family=Source+Code+Pro',
    },
  ],
  // Stripe's examples are localized to specific languages, but if
  // you wish to have Elements automatically detect your user's locale,
  // use `locale: 'auto'` instead.
  locale: window.__exampleLocale,
});
// Floating labels
var inputs = document.querySelectorAll('.cell.example.example2 .input');
Array.prototype.forEach.call(inputs, function(input) {
  input.addEventListener('focus', function() {
    input.classList.add('focused');
  });
  input.addEventListener('blur', function() {
    input.classList.remove('focused');
  });
  input.addEventListener('keyup', function() {
    if ( input.value.length === 0 ) {
      input.classList.add('empty');
    } else {
      input.classList.remove('empty');
    }
  });
});
var elementStyles = {
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
var elementClasses = {
  focus: 'focused',
  empty: 'empty',
  invalid: 'invalid',
};
var cardNumber = elements.create('cardNumber', {
  showIcon: true,
  style: elementStyles,
  classes: elementClasses,
  placeholder: 'Card Number',
});
cardNumber.mount('#example2-card-number');
var cardExpiry = elements.create('cardExpiry', {
  style: elementStyles,
  classes: elementClasses,
});
cardExpiry.mount('#example2-card-expiry');
var cardCvc = elements.create('cardCvc', {
  style: elementStyles,
  classes: elementClasses,
});
cardCvc.mount('#example2-card-cvc');
registerElements([cardNumber, cardExpiry, cardCvc], 'example1');

$(document).ready(function () {
    // setting default variables for planPrice, templatePrice, totalPrice and trialLength
    $('body').on('change', '#bump-offer', function () {
        const trialLength = +$(this).data("cf-trial-length"); //getting trial period
        const planPrice = $(this).data("cf-price");
        const templatePrice = $(this).data("cf-template-price"); //getting template price

        // checking if plan has trial days count
        let totalPrice =
            trialLength > 0
                ? parseFloat(templatePrice)
                : parseFloat(planPrice) + parseFloat(templatePrice);

        if ($(this).prop("checked")) {
            $(".oneTimePay").show();
        } else {
            $(".oneTimePay").hide();
            totalPrice = trialLength > 0 ? 0 : parseFloat(planPrice);
        }

        // displaying today's total price
        $(".elOrderProductOptinLabelPrice").html(`$ ${totalPrice}`);
    });
})
