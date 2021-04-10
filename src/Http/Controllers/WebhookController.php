<?php

namespace Msonowal\Razorpay\Cashier\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    protected $razorpay;

    /**
     * Instantiate a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->razorpay = resolve('razorpay');
    }

    private function validateSignature(Request $request)
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $webhookSignature = $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();
        $this->razorpay->utility->verifyWebhookSignature($payload, $webhookSignature, $webhookSecret);
    }

    /**
     * Handle a Razorpay webhook call.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $this->validateSignature($request);

        $payload = $request->all();

        if ((!isset($payload['entity'])) || $payload['entity'] != 'event') {
            //Unknown webhook as currrently configured to support only events
            return;
        }

        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['event']));

        if (method_exists($this, $method)) {
            return $this->{$method}($payload);
        } else {
            return $this->missingMethod();
        }
    }

    /**
     * Handle a cancelled Razorpay subscription.
     *
     * @param array $payload
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCancelled(array $payload)
    {
        $payload = $payload['payload']['subscription']['entity'];
        $user = $this->getUserByRazorpayId($payload['customer_id']);

        $subscription = $user
                                    ->subscriptions()
                                    ->where('razorpay_id', $payload['id'])
                                            ->limit(1)
                                    ->first();

        if ($subscription->isCancellable()) {
            $subscription->markAsCancelled($payload['ended_at']);
        }

        return new Response('Webhook Handled', 200);
    }

    /**
     * Get the billable entity instance by Razorpay ID.
     *
     * @param string $razorpayId
     *
     * @return \Msonowal\Cashier\Billable
     */
    protected function getUserByRazorpayId($razorpayId)
    {
        $model = getenv('RAZORPAY_MODEL') ?: config('services.razorpay.model');

        return (new $model())->byRazorpayId($razorpayId)->first();
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param array $parameters
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function missingMethod($parameters = [])
    {
        return new Response();
    }
}
