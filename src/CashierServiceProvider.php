<?php

namespace Msonowal\Razorpay\Cashier;

use Illuminate\Support\ServiceProvider;
use Razorpay\Api\Api as Razorpay;

class CashierServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('razorpay', function ($app) {
            $razorpayConfig = config('services.razorpay');
            return new Razorpay(
                    $razorpayConfig['key'],
                    $razorpayConfig['secret']
                );
        });
    }
}
