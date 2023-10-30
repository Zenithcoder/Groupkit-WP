<?php

function getCard()
{
    $user = auth()->user();
    $stripe_customer_id = $user->stripe_id;
    $customer = \Stripe\Customer::retrieve($stripe_customer_id);
    $cdata = json_decode(json_encode($customer));

    $default_source = $cdata->invoice_settings->default_payment_method;
    $card = \Stripe\PaymentMethod::retrieve($default_source);
    $carddata = json_decode(json_encode($card));

    return $carddata;
}

function getPlan()
{
    $paln = \Stripe\Plan::all(["limit" => 100]);

    return json_decode(json_encode($paln), true)['data'];
}

if (!function_exists('getStripeSecret')) {
    /**
     * Gets stripe secret according to the provided stripe account
     *
     * @param string $stripeAccount which will decide what stripe secret will be returned
     *
     * @return string including stripe secret for the provided stripe account
     */
    function getStripeSecret(string $stripeAccount): string
    {
        return config("services.stripe.$stripeAccount.secret");
    }
}
