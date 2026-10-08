<?php
// Server-side only. Run after installing this package with Composer.
require __DIR__ . '/../vendor/autoload.php';

$client = new \MochiPay\Client(getenv('MOCHIPAY_API_KEY'), getenv('MOCHIPAY_API_SECRET'));

// Save this immutable payload in your application database BEFORE this call.
// Reuse the same request_id and identical fields after an uncertain outcome.
$payload = array(
    'request_id' => 'shop-order-1001-attempt-1',
    'merchant_order_id' => 'SHOP-1001',
    'amount' => '19.90',
    'currency' => 'USD',
    'payment_method' => 'USDT_TRC20',
    'notify_url' => 'https://your-store.example/mochipay/notify',
    'redirect_url' => 'https://your-store.example/mochipay/return',
);

$order = $client->createOrder($payload);
// Save order_id and all immutable payment bindings with your local order.
// For HPP, redirect only after authenticating the customer's order ownership.
$hppUrl = $client->validatedPaymentUrl($order);
// For ON_SITE, expose only the address, amount and network to the owner.
// Do not dump the full response to the browser or infer fulfillment here.
