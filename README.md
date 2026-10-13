# MochiPay PHP SDK

A small server-side PHP client for the MochiPay payment order API. Customer payments go directly to the merchant receiving wallet. MochiPay monitors payments and sends notifications.

Requires PHP 7.4 or newer with cURL, JSON, hash and ctype extensions. Package source is MIT licensed. The external MochiPay service requires an account and an active subscription; current service pricing is $19.90/month. SDK functions are not license locked.

## Installation

Install the published package from Packagist:

```sh
composer require mochipay/php-sdk
```

For a local source checkout, run `composer install` in this directory. Never commit credentials or `vendor/`.

## Create and query

```php
require 'vendor/autoload.php';

$client = new MochiPay\Client(
    getenv('MOCHIPAY_API_KEY'),
    getenv('MOCHIPAY_API_SECRET')
);

// Persist this payload and request ID before making the request.
$payload = [
    'request_id' => 'shop-order-1001-attempt-1',
    'merchant_order_id' => 'SHOP-1001',
    'amount' => '19.90',
    'currency' => 'USD',
    'payment_method' => 'USDT_TRC20',
];
$order = $client->createOrder($payload);
// Persist order_id + immutable order/payment bindings.
$status = $client->queryOrder($order['order_id']);
$hppUrl = $client->validatedPaymentUrl($order);
```

Use decimal **strings**, never floats. All JSON numeric response tokens are returned as strings, so money is preserved exactly; booleans remain booleans. Scientific notation remains unchanged and must be handled with decimal arithmetic when comparing values. Supported methods: USDT_TRC20, USDC_ERC20, BTC_BITCOIN, ETH_ERC20, SOL_SOLANA, TRX_TRON. Native TRX (`TRX_TRON`) and USDT on TRON (`USDT_TRC20`) are different assets. Enable only methods configured for your account. The SDK's 18-place input limit is a client envelope; actual allowed amount/precision depends on the API currency and method.

Optional `notify_url` and `redirect_url` must be merchant HTTPS endpoints. ON_SITE and HPP use the same created order. Do not send `payment_mode`. ON_SITE renders a local checkout from a minimal payment DTO. HPP uses the validated URL with a 303 redirect. Always authenticate customer ownership before displaying checkout or status.

## Retry and error handling

There are no automatic retries. Save a stable `request_id` (1–64 ASCII letters, digits, `.`, `_`, `:`, `-`) and unchanged payload before creation. On supported servers, repeating it with identical fields recovers the same order. Once you have an order ID, query it. Reused merchant references are not idempotency keys. Do not retrofit request IDs onto historical attempts or blindly repeat uncertain creates.

`ApiException` exposes `getHttpStatus()`; it deliberately omits raw responses to avoid leaking credentials or customer data. An API error or malformed response after creation can mean the outcome is uncertain. Recover from the saved attempt; consult the API documentation for REQUEST_IN_PROGRESS, REQUEST_ID_CONFLICT and REQUEST_ORDER_UNAVAILABLE. The SDK does not store attempts, fulfill orders, or implement business retry policy.

## Returns, callbacks and fulfillment

Incoming callbacks and browser returns only trigger an authenticated `queryOrder` call. They never prove payment. Before fulfilling, compare query results against your immutable local order binding: order ID, merchant reference, original currency and amount, receiving address and method/network (`wallet_type` + `_` + `network` in query results). Require `PAID` and a positive `received_amount`. Require exact decimal equality with `pay_amount`, unless the authenticated query returns the strict Boolean `manual_payment_accepted === true` after a merchant's audited confirmation. A callback field, browser field, string `"true"`, number `1`, or `PAID` alone does not authorize a mismatch. Handle underpaid, overpaid, expired and inconsistent orders through review. Use a database transaction and unique fulfillment constraint to fulfill exactly once. A closed browser must not stop backend callback handling.

TLS certificate and hostname verification are enabled; redirects are disabled for authenticated API calls. Keep API credentials on your backend, never in JavaScript/mobile code. Store attempts durably and apply your application's account authorization and rate limiting.

## Development

```sh
composer validate --strict
composer install
composer test
```

Offline tests cover exact-byte signing, precision-preserving decoding, invalid inputs and hostile HPP links. They do not create real orders. Live API and merchant checkout acceptance are separate checks.

## Documentation

- [API reference](https://mochi.bz/Developers/Reference.aspx)
- [Developer examples](https://mochi.bz/Developers/Examples.aspx)
- [Free integration Skill](https://mochi.bz/Developers/Skill.aspx)
- [Terms](https://mochi.bz/Terms.aspx)
- [Privacy](https://mochi.bz/Privacy.aspx)
