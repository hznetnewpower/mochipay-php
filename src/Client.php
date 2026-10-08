<?php
namespace MochiPay;

final class Client
{
    private $key;
    private $secret;
    private $baseUrl;
    private $timeout;

    public function __construct($key, $secret, $baseUrl = 'https://mochi.bz', $timeout = 30)
    {
        if (!is_string($key) || !is_string($secret) || $key === '' || $secret === '' || preg_match('/[\r\n]/', $key)) {
            throw new \InvalidArgumentException('Private API key and secret are required.');
        }
        $baseUrl = rtrim($baseUrl, '/');
        $parts = parse_url($baseUrl);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !empty($parts['path'])) {
            throw new \InvalidArgumentException('API base URL must be an HTTPS origin without a path.');
        }
        if (!is_int($timeout) || $timeout < 1 || $timeout > 120) {
            throw new \InvalidArgumentException('Timeout must be an integer from 1 to 120 seconds.');
        }
        $this->key = $key;
        $this->secret = $secret;
        $this->baseUrl = $baseUrl;
        $this->timeout = $timeout;
    }

    public function sign($exactBytes)
    {
        return base64_encode(hash_hmac('sha256', $exactBytes, $this->secret, true));
    }

    /** Persist payload + request_id before calling. No automatic creation retries. */
    public function createOrder(array $payload)
    {
        foreach (array('merchant_order_id', 'amount', 'currency', 'payment_method') as $field) {
            if (!isset($payload[$field]) || !is_string($payload[$field]) || $payload[$field] === '') {
                throw new \InvalidArgumentException($field . ' must be a non-empty string.');
            }
        }
        if (!preg_match('/^.{1,100}$/usD', $payload['merchant_order_id'])) {
            throw new \InvalidArgumentException('Merchant order reference must contain at most 100 UTF-8 characters.');
        }
        if (!preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,18})?$/D', $payload['amount']) || !preg_match('/[1-9]/', $payload['amount'])) {
            throw new \InvalidArgumentException('Amount must be a positive decimal string with at most 18 decimal places.');
        }
        if (!in_array($payload['payment_method'], array('USDT_TRC20', 'USDC_ERC20', 'BTC_BITCOIN', 'ETH_ERC20', 'SOL_SOLANA'), true)) {
            throw new \InvalidArgumentException('Unsupported payment method.');
        }
        if (array_key_exists('payment_mode', $payload)) {
            throw new \InvalidArgumentException('Checkout mode is local configuration, not an API field.');
        }
        if (isset($payload['request_id']) && (!is_string($payload['request_id']) || !preg_match('/^[A-Za-z0-9._:-]{1,64}$/D', $payload['request_id']))) {
            throw new \InvalidArgumentException('Invalid request_id.');
        }
        foreach (array('notify_url', 'redirect_url') as $field) {
            if (!empty($payload[$field]) && (!is_string($payload[$field]) || !filter_var($payload[$field], FILTER_VALIDATE_URL) || parse_url($payload[$field], PHP_URL_SCHEME) !== 'https')) {
                throw new \InvalidArgumentException($field . ' must be an HTTPS URL.');
            }
        }
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return $this->request('POST', '/api/v1/orders/create', $body, $body);
    }

    public function queryOrder($orderId)
    {
        return $this->query('order_id', $orderId);
    }

    /** References may be reused: prefer the saved MochiPay order ID. */
    public function queryByMerchantOrderId($merchantOrderId)
    {
        return $this->query('merchant_order_id', $merchantOrderId);
    }

    private function query($field, $value)
    {
        if (!is_string($value) || $value === '') { throw new \InvalidArgumentException('Order identifier is required.'); }
        $query = http_build_query(array($field => $value), '', '&', PHP_QUERY_RFC3986);
        return $this->request('GET', '/api/v1/orders/query?' . $query, '', $query);
    }

    public function validatedPaymentUrl(array $order)
    {
        $url = $order['payment_url'] ?? '';
        $id = $order['order_id'] ?? '';
        if (!is_string($url) || !is_string($id) || !preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
            throw new \InvalidArgumentException('Invalid payment URL or order ID.');
        }
        $parts = parse_url($url);
        $origin = parse_url($this->baseUrl);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== strtolower($origin['host']) || ($parts['port'] ?? 443) !== ($origin['port'] ?? 443) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || ($parts['path'] ?? '') !== '/pay/' . $id) {
            throw new \InvalidArgumentException('Payment URL does not match the configured origin and saved order.');
        }
        return $url;
    }

    private function request($method, $path, $body, $signingBytes)
    {
        if (!function_exists('curl_init')) { throw new ApiException('The PHP cURL extension is required.'); }
        $headers = array('Accept: application/json', 'X-Mochi-Key: ' . $this->key, 'X-Mochi-Signature: ' . $this->sign($signingBytes));
        if ($method === 'POST') { $headers[] = 'Content-Type: application/json; charset=utf-8'; }
        $handle = curl_init($this->baseUrl . $path);
        curl_setopt_array($handle, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout), CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        ));
        if ($method === 'POST') { curl_setopt($handle, CURLOPT_POSTFIELDS, $body); }
        $raw = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        if ($raw === false) { throw new ApiException('Transport failed; creation outcome may be uncertain. Recover using the persisted request_id.', $status); }
        try { $data = Json::decode($raw); }
        catch (\JsonException $e) { throw new ApiException('API response is not valid JSON.', $status); }
        if ($status < 200 || $status >= 300 || !is_array($data) || ($data['success'] ?? false) !== true) {
            // Do not include bodies, credentials or customer data in exceptions.
            throw new ApiException('MochiPay API request failed (HTTP ' . $status . '). Query or review the order before retrying.', $status);
        }
        return $data;
    }
}
