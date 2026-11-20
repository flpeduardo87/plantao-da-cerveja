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
    /**
     * @param array       $order         Dados do pedido.
     * @param string      $cardToken     Token do cartão (fluxo padrão sem 3DS).
     * @param int         $installments  Parcelas.
     * @param array|null  $rawCard       Dados brutos do cartão (fluxo 3DS autenticado).
     *                                   ['number','holder_name','exp_month','exp_year','cvv']
     * @param string|null $tdsTransId    tds_server_trans_id retornado pelo SDK 3DS.
     */
    public function createCreditCardOrder(array $order, string $cardToken, int $installments = 1, ?array $rawCard = null, ?string $tdsTransId = null): array {
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
        $email   = trim($order['customer_email'] ?? '');
        if (!$email) {
            $cleanPhone = preg_replace('/\D/', '', $order['customer_phone'] ?? '');
            $email = $cleanPhone . '@noemail.plantaodacerveja.com.br';
        }
        $zip     = preg_replace('/\D/', '', $order['address_cep'] ?? $order['address_zip'] ?? '');
        $street  = $order['address_street'] ?? '';
        $num     = $order['address_number'] ?? '0';
        $city    = $order['address_city'] ?? '';
        $state   = strtoupper(substr($order['address_state'] ?? '', 0, 2)) ?: 'SP';

        $addressLine1 = trim($num . ', ' . $street) ?: '0, Rua nao informada';
        $addrObj = [
            'line_1'   => $addressLine1,
            'zip_code' => $zip ?: '00000000',
            'city'     => $city ?: 'São Paulo',
            'state'    => $state,
            'country'  => 'BR',
        ];

        $successUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'plantaodacerveja.com.br')
            . '/?pago=1';

        $use3ds = $rawCard && $tdsTransId;

        if ($use3ds) {
            // Fluxo 3DS autenticado via SDK Stone: cartão bruto + authentication object
            $cardObj = [
                'number'          => preg_replace('/\D/', '', $rawCard['number'] ?? ''),
                'holder_name'     => $rawCard['holder_name'] ?? '',
                'exp_month'       => (int)($rawCard['exp_month'] ?? 1),
                'exp_year'        => (int)($rawCard['exp_year'] ?? 2030),
                'cvv'             => $rawCard['cvv'] ?? '',
                'billing_address' => $addrObj,
            ];
            $paymentEntry = [
                'payment_method' => 'credit_card',
                'credit_card'    => [
                    'installments'         => max(1, (int)$installments),
                    'statement_descriptor' => 'PLANT CERVEJA',
                    'card'                 => $cardObj,
                    'authentication'       => [
                        'type'          => 'threed_secure',
                        'threed_secure' => [
                            'mpi'            => 'pagarme',
                            'transaction_id' => $tdsTransId,
                        ],
                    ],
                ],
            ];
        } else {
            // Fluxo padrão: token de cartão sem parâmetros 3DS
            $paymentEntry = [
                'payment_method' => 'credit_card',
                'credit_card'    => [
                    'operation_type'       => 'auth_and_capture',
                    'installments'         => max(1, (int)$installments),
                    'statement_descriptor' => 'PLANT CERVEJA',
                    'card_token'           => $cardToken,
                    'card'                 => ['billing_address' => $addrObj],
                ],
            ];
        }

        $payload = [
            'code'     => $order['order_number'],
            'customer' => array_filter([
                'name'          => $order['customer_name'],
                'email'         => $email,
                'type'          => 'individual',
                'document'      => $cpf ?: null,
                'document_type' => $cpf ? 'CPF' : null,
                'address'       => $addrObj,
                'phones' => [
                    'mobile_phone' => [
                        'country_code' => '55',
                        'area_code'    => $areaCode,
                        'number'       => $number,
                    ],
                ],
            ]),
            'items'    => $items,
            'payments' => [$paymentEntry],
        ];

        $result = $this->request('POST', '/orders', $payload);

        $orderStatus  = $result['status'] ?? 'failed';
        $charge       = $result['charges'][0] ?? [];
        $chargeId     = $charge['id'] ?? null;
        $chargeStatus = $charge['status'] ?? null;
        $lastTxn      = $charge['last_transaction'] ?? [];
        $txnStatus    = $lastTxn['status'] ?? null;

        // Consider paid if order OR charge is paid/authorized/captured
        $paidStatuses = ['paid', 'authorized', 'captured', 'overpaid'];
        $isPaid = in_array($orderStatus, $paidStatuses, true)
               || in_array($chargeStatus, $paidStatuses, true)
               || in_array($txnStatus, $paidStatuses, true);

        // 3DS redirect URL — try multiple paths Pagar.me may use
        $threedUrl = $lastTxn['threed_secure_authentication_url']
            ?? ($lastTxn['threed_secure_object']['authentication_url'] ?? null)
            ?? ($lastTxn['threed_secure_object']['acs_url'] ?? null)
            ?? null;

        // Decline message from acquirer — only show when truly failed
        $declineMsg = null;
        if (!$isPaid && !$threedUrl) {
            $declineMsg = $lastTxn['acquirer_message']
                ?? ($lastTxn['gateway_response']['errors'][0]['message'] ?? null)
                ?? ($lastTxn['gateway_response']['code'] ?? null);
            // Don't show success messages as decline reasons
            if ($declineMsg && preg_match('/aprovad|sucesso/i', $declineMsg)) {
                $declineMsg = null;
            }
        }

        // Normalize status for caller
        if ($isPaid) {
            $status = 'paid';
        } elseif ($threedUrl) {
            $status = 'pending';
        } elseif (in_array($orderStatus, ['pending', 'processing'], true)
               || in_array($chargeStatus, ['pending', 'waiting_payment', 'processing'], true)) {
            $status = 'pending';
        } else {
            $status = 'failed';
        }

        return [
            'status'           => $status,
            'pagarme_order_id' => $result['id'] ?? null,
            'charge_id'        => $chargeId,
            'decline_reason'   => $declineMsg,
            'threed_secure_url'=> $threedUrl,
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
     * Obtém token de sessão 3DS da Stone (válido ~20 s).
     * Deve ser chamado imediatamente antes de cada tentativa de autenticação.
     */
    public function get3dsToken(): string {
        $url = 'https://3ds.stone.com.br/v2/tds-token';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode($this->secretKey . ':')],
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false || $err) {
            throw new \RuntimeException('3DS token: falha de conexão — ' . $err);
        }
        if ($code >= 400) {
            throw new \RuntimeException('3DS token: HTTP ' . $code . ' — ' . $body);
        }
        $data = json_decode($body, true) ?? [];
        $token = $data['tds_token'] ?? '';
        if (!$token) {
            throw new \RuntimeException('3DS token: resposta sem tds_token — ' . $body);
        }
        return $token;
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
