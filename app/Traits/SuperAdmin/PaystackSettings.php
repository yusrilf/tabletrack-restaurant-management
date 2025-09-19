<?php
/**
 * Created by PhpStorm.
 * User: DEXTER
 * Date: 24/05/17
 * Time: 11:29 PM
 */

namespace App\Traits\SuperAdmin;

use App\Models\SuperadminPaymentGateway;
use Illuminate\Support\Facades\Config;

trait PaystackSettings
{

    public function setPaystackConfigs()
    {
        $settings = SuperadminPaymentGateway::first();

        if($settings->paystack_mode == 'sandbox'){
            $key       = ($settings->test_paystack_key) ?: env('PAYSTACK_PUBLIC_KEY');
            $apiSecret = ($settings->test_paystack_secret) ?: env('PAYSTACK_SECRET_KEY');
            $email = ($settings->test_paystack_merchant_email) ?: env('MERCHANT_EMAIL');
        }
        else{
            $key       = ($settings->live_paystack_key) ?: env('PAYSTACK_PUBLIC_KEY');
            $apiSecret = ($settings->live_paystack_secret) ?: env('PAYSTACK_SECRET_KEY');
            $email = ($settings->live_paystack_merchant_email) ?: env('MERCHANT_EMAIL');
        }

        $url = ($settings->paystack_payment_url) ?: env('PAYSTACK_PAYMENT_URL');


        Config::set('paystack.publicKey', $key);
        Config::set('paystack.secretKey', $apiSecret);
        Config::set('paystack.paymentUrl', $url);
        Config::set('paystack.merchantEmail', $email);
    }

}



