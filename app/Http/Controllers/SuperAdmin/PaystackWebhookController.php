<?php

namespace App\Http\Controllers\SuperAdmin;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Models\GlobalInvoice;
use App\Traits\SuperAdmin\PaystackSettings;
use App\Models\GlobalSubscription;

class PaystackWebhookController extends Controller
{

    use PaystackSettings;

    public function saveInvoices(Request $request, $hash)
    {
        $this->setPaystackConfigs();

        switch ($request['event']) {

            case 'subscription.create':
                $subscription = GlobalSubscription::where('gateway_name', 'paystack')->where('customer_id', $request['data']['customer']['customer_code'])->where('subscription_status', 'active')->latest()->first();
        
                if ($subscription) {
                    $subscription->subscription_id = $request['data']['subscription_code'];
                    $subscription->save();
                }
                                break;
        
            case 'charge.success':
                $subscription = GlobalSubscription::where('gateway_name', 'paystack')
                    ->where('customer_id', $request['data']['customer']['customer_code'])
                    ->where('subscription_status', 'active')
                    ->latest()
                    ->first();
                if ($subscription) {
                    $invoice = GlobalInvoice::where('transaction_id', $subscription->transaction_id)->first();

                    $invoice = $invoice ?: new GlobalInvoice();
                    $invoice->restaurant_id = $subscription->restaurant_id;
                    $invoice->package_id = $subscription->package_id;
                    $invoice->currency_id = $subscription->currency_id;
                    $invoice->global_subscription_id = $subscription->id;
                    $invoice->pay_date = now()->format('Y-m-d');
                    $invoice->next_pay_date = now()->{(($subscription->package_type == 'monthly') ? 'addMonth' : 'addYear')}()->format('Y-m-d');
                    $invoice->status = 'active';
                    $invoice->package_type = $subscription->package_type;
                    $invoice->gateway_name = 'paystack';
                    $invoice->total = $request['data']['amount'] / 100;
                    $invoice->transaction_id = $request['data']['reference'];
                    $invoice->plan_id = $request['data']['plan']['plan_code'] ?? null;
                    $invoice->subscription_id = $subscription->subscription_id ?? null;
                    $invoice->amount = $request['data']['amount'] / 100;
                    $invoice->token = $request['data']['authorization']['authorization_code'];
                    $invoice->signature = $request['data']['authorization']['signature'];
                    $invoice->save();
                }

                break;

        case 'subscription.not_renew':
            $subscription = GlobalSubscription::where('gateway_name', 'paystack')->where('customer_id', $request['data']['customer']['customer_code'])->where('subscription_status', 'active')->latest()->first();

            if ($subscription) {
                $subscriptionInvoice = GlobalInvoice::where('global_subscription_id', $subscription->id)->latest()->first();

                $endDate = Carbon::parse($subscriptionInvoice->next_pay_date);
                $subscription->ends_at = $endDate->gt(now()) ? $endDate : now();

                $subscription->status = 'inactive';
                $subscription->save();
            }

            break;

        default:
            break;
        }

        return response()->json(['success' => true]);
    }

}
