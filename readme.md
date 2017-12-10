
## Laravel Razorpay Cashier

<p align="center">

[![StlyeCI](https://styleci.io/repos/113607269/shield)](https://styleci.io/repos/113607269)
[![Latest Stable Version](https://poser.pugx.org/msonowal/laravel-razor-pay-cashier/v/stable?format=flat-square)](https://packagist.org/packages/msonowal/laravel-razor-pay-cashier)
[![License](https://poser.pugx.org/msonowal/laravel-razor-pay-cashier/license?format=flat-square)](https://packagist.org/packages/msonowal/laravel-razor-pay-cashier)
[![Total Downloads](https://poser.pugx.org/msonowal/laravel-razor-pay-cashier/downloads?format=flat-square)](https://packagist.org/packages/msonowal/laravel-razor-pay-cashier)
[![Monthly Downloads](https://poser.pugx.org/msonowal/laravel-razor-pay-cashier/d/monthly?format=flat-square)](https://packagist.org/packages/msonowal/laravel-razor-pay-cashier)
[![Daily Downloads](https://poser.pugx.org/msonowal/laravel-razor-pay-cashier/d/daily?format=flat-square)](https://packagist.org/packages/msonowal/laravel-razor-pay-cashier)

</p>



## Introduction

Laravel Cashier inspired Razorpay Cashier provides an expressive, fluent interface to [Razorpay's](https://razorpay.com) subscription billing services. It handles almost all of the boilerplate subscription billing code you are dreading writing. In addition to basic subscription management, Cashier can handle subscription "quantities", cancellation grace periods.

## Installation
`composer require "msonowal/laravel-razor-pay-cashier"`

Next, register the service provider in your `config/app.php` configuration file.

`Msonowal\Cashier\CashierServiceProvider`

### Environment Configurations
define these keys in services
```
'razorpay' => [
    'model'     =>  App\Models\User::class,
    'key'       =>  env('RAZORPAY_KEY'),
    'secret'    =>  env('RAZORPAY_SECRET'),
],
```
This will register a singleton which can be resolved by using `razorpay` as a resolver


## Official Documentation

TODO Documentation 
For time being you can follow laravel cashier's documentation for implementaion, and apis I have kept almost same signature with modifications to razorpay


#### .env

    RAZORPAY_KEY=
    RAZORPAY_SECRET=
    RAZORPAY_MODEL=


## Running Cashier's Tests Locally

TODO
Add Invoicing generating PDF based on line items in application side

## Contributing

Thank you for considering contributing to the Cashier. You can read the contribution guide lines [here](contributing.md).

## License

Laravel Cashier is open-sourced software licensed under the [MIT license](LICENSE.txt).


# Found any bugs? or improvement open an issue or send me a PR

