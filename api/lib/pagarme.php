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
     * Cria pedido com pagamento direto via token de cartão (checkout transparente).
     *
     * @param array  $order       order_number, total, customer_name, customer_phone, items[]
     * @param string $cardToken   token gerado pelo frontend via /v5/tokens
     * @param int    $installments parcelas (1–12)
     * @return array ['status'=>'paid'|'failed', 'pagarme_order_id'=>'...', 'charge_id'=>'...']
     */
    public function createCreditCardOrder(array $order, string $cardToken, int $installments = 1): array {
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

        $cpf     = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');
        $zip     = preg_replace('/\D/', '', $order['address_zip'] ?? '');
        $street  = $order['address_street'] ?? '';
        $num     = $order['address_number'] ?? '0';
        $city    = $order['address_city'] ?? '';
        $state   = strtoupper(substr($order['address_state'] ?? '', 0, 2)) ?: 'SP';

        $billing = [
            'name'    => $order['customer_name'],
            'address' => [
                'line_1'   => $num . ', ' . $street,
                'zip_code' => $zip ?: '00000000',
                'city'     => $city ?: 'São Paulo',
                'state'    => $state,
                'country'  => 'BR',
            ],
        ];

        $payload = [
            'code'     => $order['order_number'],
            'customer' => array_filter([
                'name'          => $order['customer_name'],
                'type'          => 'individual',
                'document'      => $cpf ?: null,
                'document_type' => $cpf ? 'CPF' : null,
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code'    => $areaCode,
                        'number'       => $number,
                    ],
                ],
            ]),
            'items'    => $items,
            'payments' => [[
                'payment_method' => 'credit_card',
                'amount'         => $amountCents,
                'credit_card'    => [
                    'installments'         => max(1, (int) $installments),
                    'statement_descriptor' => 'PLANTAO CERVEJA',
                    'card_token'           => $cardToken,
                ],
                'billing' => $billing,
            ]],
        ];

        error_log('Pagar.me CC payload: ' . json_encode($payload));
        $result = $this->request('POST', '/orders', $payload);
        error_log('Pagar.me CC result status=' . ($result['status'] ?? 'n/a') . ' charges=' . json_encode($result['charges'][0]['last_transaction'] ?? []));

        $status      = $result['status'] ?? 'failed';
        $chargeId    = $result['charges'][0]['id'] ?? null;
        $lastTxn     = $result['charges'][0]['last_transaction'] ?? [];
        error_log('Pagar.me charge result: ' . json_encode($result['charges'][0] ?? []));
        $declineMsg  = $lastTxn['acquirer_message']
            ?? ($lastTxn['gateway_response']['errors'][0]['message'] ?? null)
            ?? ($lastTxn['gateway_response']['code'] ?? null);

        return [
            'status'          => $status,
            'pagarme_order_id'=> $result['id'] ?? null,
            'charge_id'       => $chargeId,
            'decline_reason'  => $declineMsg,
        ];
    }

    /**
     * Cria pedido PIX e retorna QR code para exibição imediata.
     *
     * @param array $order  order_number, total, customer_name, customer_phone, items[]
     * @return array ['qr_code'=>'...', 'expires_in'=>1800, 'pagarme_order_id'=>'...']
     */
    public function createPixOrder(array $order): array {
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

        $expiresIn = 1800; // 30 min
        $cpf   = preg_replace('/\D/', '', $order['customer_cpf'] ?? '');
        $email = $order['customer_email'] ?? null;

        $payload = [
            'code'     => $order['order_number'],
            'customer' => array_filter([
                'name'          => $order['customer_name'],
                'type'          => 'individual',
                'document'      => $cpf ?: null,
                'document_type' => $cpf ? 'CPF' : null,
                'email'         => $email ?: null,
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code'    => $areaCode,
                        'number'       => $number,
                    ],
                ],
            ]),
            'items'    => $items,
            'payments' => [[
                'payment_method' => 'pix',
                'amount'         => $amountCents,
                'pix'            => [
                    'expires_in' => $expiresIn,
                ],
            ]],
        ];

        error_log('Pagar.me PIX payload: ' . json_encode($payload));
        $result = $this->request('POST', '/orders', $payload);

        $txn    = $result['charges'][0]['last_transaction'] ?? [];
        $qrCode = $txn['qr_code'] ?? null;

        if (!$qrCode) {
            throw new \RuntimeException('PIX: QR code nao retornado. Resposta: ' . json_encode($result));
        }

        return [
            'qr_code'          => $qrCode,
            'expires_in'       => $expiresIn,
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
            error_log('Pagar.me HTTP ' . $code . ' — ' . $method . ' ' . $path . ' — body: ' . $body);
            $msg = $data['errors'][0]['message'] ?? ($data['message'] ?? ('Pagar.me HTTP ' . $code));
            throw new \RuntimeException('Pagar.me: ' . $msg);
        }

        return $data;
    }
}
