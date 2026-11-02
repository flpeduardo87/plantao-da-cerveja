<?php
/**
 * Stone payment gateway — payment link (cobrança) integration.
 *
 * Stone API docs: https://docs.stone.com.br
 * Auth: Bearer token (API Key from painel.stone.com.br)
 */
class Stone {
    private string $apiKey;
    private string $stoneCode;
    private string $baseUrl;

    public function __construct() {
        $this->apiKey    = defined('STONE_API_KEY') ? STONE_API_KEY : '';
        $this->stoneCode = defined('STONE_CODE')    ? STONE_CODE    : '';
        $env             = defined('STONE_ENV')     ? STONE_ENV     : 'production';
        $this->baseUrl   = $env === 'sandbox'
            ? 'https://sandbox-api.openbank.stone.com.br'
            : 'https://api.openbank.stone.com.br';
    }

    public function isConfigured(): bool {
        return $this->apiKey !== '' && $this->stoneCode !== '';
    }

    /**
     * Create a Stone payment link for an order.
     *
     * @param array $order  Must have: order_number, total, customer_name, customer_phone
     * @param string $webhookUrl  URL Stone will POST status updates to
     * @return array  ['payment_url' => '...', 'id' => '...']
     * @throws RuntimeException on API error
     */
    public function createPaymentLink(array $order, string $webhookUrl): array {
        $amountCents = (int) round((float) $order['total'] * 100);

        $payload = [
            'amount'              => $amountCents,
            'customer'            => [
                'name'  => $order['customer_name'],
                'phone' => [
                    'country_code' => '55',
                    'area_code'    => substr(preg_replace('/\D/', '', $order['customer_phone']), 0, 2),
                    'number'       => substr(preg_replace('/\D/', '', $order['customer_phone']), 2),
                ],
            ],
            'description'         => 'Plantão da Cerveja — Pedido ' . $order['order_number'],
            'expires_at'          => date('c', strtotime('+1 hour')),
            'metadata'            => [
                'order_number' => $order['order_number'],
                'order_id'     => (string)($order['id'] ?? ''),
            ],
        ];

        $result = $this->request(
            'POST',
            "/api/v1/merchant_accounts/{$this->stoneCode}/payment_links",
            $payload
        );

        if (empty($result['url'])) {
            throw new \RuntimeException('Stone não retornou URL de pagamento');
        }

        return [
            'payment_url' => $result['url'],
            'stone_id'    => $result['id'] ?? null,
        ];
    }

    /**
     * Verify a webhook payload signature from Stone.
     * Stone sends X-Stone-Signature header (HMAC-SHA256 of raw body with API key).
     */
    public function verifyWebhook(string $rawBody, string $signature): bool {
        $expected = hash_hmac('sha256', $rawBody, $this->apiKey);
        return hash_equals($expected, $signature);
    }

    // ── Internal ────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $payload = []): array {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $method !== 'GET' ? json_encode($payload) : null,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err) {
            throw new \RuntimeException('Stone: falha de conexão — ' . $err);
        }

        $data = json_decode($body, true) ?? [];

        if ($code >= 400) {
            $msg = $data['errors'][0]['type'] ?? ($data['message'] ?? ('Stone HTTP ' . $code));
            throw new \RuntimeException('Stone: ' . $msg);
        }

        return $data;
    }
}
