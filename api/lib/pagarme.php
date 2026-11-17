<?php
/**
 * Pagar.me v5 — checkout / link de pagamento.
 * Docs: https://docs.pagar.me/reference/
 * Auth: Basic (secret_key : '')
 */
class PagarMe {
    private string $secretKey;
    private string $baseUrl = 'https://api.pagar.me/core/v5';

    public function __construct() {
        $this->secretKey = defined('PAGARME_SECRET_KEY') ? PAGARME_SECRET_KEY : '';
    }

    public function isConfigured(): bool {
        return $this->secretKey !== '';
    }

    /**
     * Cria um checkout Pagar.me e retorna a URL de pagamento.
     *
     * @param array  $order       Deve ter: order_number, total, customer_name, customer_phone, items[]
     *                            Cada item: ['name'=>..., 'qty'=>..., 'price'=>..., 'product_id'=>...]
     * @param string $successUrl  Redireciona o cliente após pagamento aprovado
     * @param string $webhookUrl  Pagar.me vai POST eventos aqui
     * @return array ['payment_url' => '...', 'pagarme_order_id' => '...']
     */
    public function createCheckout(array $order, string $successUrl, string $webhookUrl): array {
        $amountCents = (int) round((float) $order['total'] * 100);
        $phone       = preg_replace('/\D/', '', $order['customer_phone']);
        $areaCode    = strlen($phone) >= 11 ? substr($phone, 0, 2) : '11';
        $number      = strlen($phone) >= 11 ? substr($phone, 2) : substr($phone, 2);

        $items = array_values(array_map(function ($item, $idx) {
            return [
                'amount'      => (int) round((float)($item['price'] ?? $item['unit_price'] ?? 0) * 100),
                'description' => $item['name'] ?? ('Item ' . ($idx + 1)),
                'quantity'    => (int)($item['qty'] ?? $item['quantity'] ?? 1),
                'code'        => (string)($item['product_id'] ?? ($idx + 1)),
            ];
        }, $order['items'], array_keys($order['items'])));

        $payload = [
            'code'     => $order['order_number'],
            'customer' => [
                'name'   => $order['customer_name'],
                'type'   => 'individual',
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code'    => $areaCode,
                        'number'       => $number,
                    ],
                ],
            ],
            'items'    => $items,
            'payments' => [[
                'payment_method' => 'checkout',
                'checkout'       => [
                    'expires_in'               => 3600,
                    'billing_address_editable' => false,
                    'customer_editable'        => false,
                    'accepted_payment_methods' => ['credit_card', 'pix'],
                    'default_payment_method'   => 'credit_card',
                    'success_url'              => $successUrl,
                ],
            ]],
        ];

        $result = $this->request('POST', '/orders', $payload);

        $checkoutUrl = $result['checkouts'][0]['payment_url']
            ?? $result['checkout']['payment_url']
            ?? null;

        if (!$checkoutUrl) {
            throw new \RuntimeException('Pagar.me não retornou URL de pagamento. Resposta: ' . json_encode($result));
        }

        return [
            'payment_url'      => $checkoutUrl,
            'pagarme_order_id' => $result['id'] ?? null,
        ];
    }

    /**
     * Verifica assinatura HMAC-SHA256 do webhook Pagar.me.
     * Header recebido: X-Hub-Signature  (formato: sha256=xxxx)
     */
    public function verifyWebhook(string $rawBody, string $signature): bool {
        $secret   = defined('PAGARME_WEBHOOK_SECRET') ? PAGARME_WEBHOOK_SECRET : $this->secretKey;
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    // ── Interno ──────────────────────────────────────────────────────────────────────

    private function request(string $method, string $path, array $payload = []): array {
        $url = $this->baseUrl . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => $method !== 'GET' ? json_encode($payload) : null,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err) {
            throw new \RuntimeException('Pagar.me: falha de conexão — ' . $err);
        }

        $data = json_decode($body, true) ?? [];

        if ($code >= 400) {
            $msg = $data['errors'][0]['message'] ?? ($data['message'] ?? ('Pagar.me HTTP ' . $code));
            throw new \RuntimeException('Pagar.me: ' . $msg);
        }

        return $data;
    }
}
