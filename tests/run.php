<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'MochiPay\\') === 0) {
        require __DIR__ . '/../src/' . substr($class, 9) . '.php';
    }
});
function check($condition, $message) {
    if (!$condition) { throw new \RuntimeException($message); }
}
function rejects($callback) {
    try { $callback(); } catch (\InvalidArgumentException $e) { return; }
    throw new \RuntimeException('Expected rejection.');
}
$client = new \MochiPay\Client('fixture-key', 'fixture-secret');
$bytes = '{"amount":"19.90","reference":"a/b"}';
check($client->sign($bytes) === base64_encode(hash_hmac('sha256', $bytes, 'fixture-secret', true)), 'Exact-byte signature');
check($client->sign($bytes) !== $client->sign($bytes . ' '), 'Signature includes whitespace');
$data = \MochiPay\Json::decode('{"success":true,"amount":0.123456789012345678,"count":123,"note":"amount: 42 and \\"x\\""}');
check($data['amount'] === '0.123456789012345678', 'Decimal precision');
check($data['success'] === true && $data['count'] === '123', 'Boolean and numeric tokens');
check($data['note'] === 'amount: 42 and "x"', 'Do not rewrite strings');
check($client->validatedPaymentUrl(array('order_id' => 'abc123', 'payment_url' => 'https://mochi.bz/pay/abc123')) === 'https://mochi.bz/pay/abc123', 'Valid HPP URL');
foreach (array('https://evil.example/pay/abc123', 'https://mochi.bz@evil.example/pay/abc123', 'http://mochi.bz/pay/abc123', 'https://mochi.bz/pay/other', 'https://mochi.bz:444/pay/abc123', 'https://mochi.bz/pay/abc123?next=x', 'https://mochi.bz/pay/abc123#x') as $url) {
    rejects(function () use ($client, $url) { $client->validatedPaymentUrl(array('order_id' => 'abc123', 'payment_url' => $url)); });
}
rejects(function () { new \MochiPay\Client('key', 'secret', 'http://mochi.bz'); });
rejects(function () { new \MochiPay\Client("key\r\nInjected: true", 'secret'); });
$payload = array('merchant_order_id' => 'SHOP-1', 'amount' => 19.90, 'currency' => 'USD', 'payment_method' => 'USDT_TRC20');
rejects(function () use ($client, $payload) { $client->createOrder($payload); });
$payload['amount'] = '0';
rejects(function () use ($client, $payload) { $client->createOrder($payload); });
$payload['amount'] = '19.90';
$payload['request_id'] = 'bad id';
rejects(function () use ($client, $payload) { $client->createOrder($payload); });
echo "Offline checks passed. No live API calls were made.\n";
