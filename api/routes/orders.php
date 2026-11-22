<?php
// GET  /orders            — list orders [admin]  ?status=new&limit=50&offset=0
// GET  /orders/{id}       — single order [admin]
// POST /orders            — create order [public]
// PATCH /orders/{id}      — update status [admin]

require_once __DIR__ . '/../lib/delivery.php';

$pdo = db();

match ($method) {
    // ── List / Single ────────────────────────────────────────────────────────
    'GET' => (function () use ($pdo, $id) {
        // Public tracking by order_number + phone (no auth required)
        if (!empty($_GET['order_number']) && !empty($_GET['phone'])) {
            $phone = preg_replace('/\D/', '', $_GET['phone']);
            $s = $pdo->prepare(
                'SELECT o.order_number, o.status, o.payment_status, o.created_at, o.total, o.payment_method, o.delivery_type,
                        o.address_street, o.address_number,
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                "product_name", oi.product_name,
                                "quantity", oi.quantity,
                                "unit_price", oi.unit_price,
                                "total", oi.total
                            )
                        ) AS items_json
                 FROM orders o
                 LEFT JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.order_number = ? AND o.customer_phone = ?
                 GROUP BY o.id'
            );
            $s->execute([trim($_GET['order_number']), $phone]);
            $row = $s->fetch();
            if (!$row) json_err('Pedido não encontrado', 404);
            $row['items'] = $row['items_json'] ? json_decode('[' . $row['items_json'] . ']', true) : [];
            unset($row['items_json']);
            json_ok(['success' => true, 'order' => $row]);
        }

        if ($id) {
            $s = $pdo->prepare(
                'SELECT o.*, GROUP_CONCAT(
                    JSON_OBJECT(
                        "id", oi.id,
                        "product_id", oi.product_id,
                        "product_name", oi.product_name,
                        "quantity", oi.quantity,
                        "unit_price", oi.unit_price,
                        "total", oi.total
                    )
                ) AS items_json
                FROM orders o
                LEFT JOIN order_items oi ON oi.order_id = o.id
                WHERE o.id = ?
                GROUP BY o.id'
            );
            $s->execute([$id]);
            $row = $s->fetch();
            if (!$row) json_err('Not found', 404);
            $row['items'] = $row['items_json'] ? json_decode('[' . $row['items_json'] . ']', true) : [];
            unset($row['items_json']);
            json_ok($row);
        }

        $where  = '1=1';
        $vals   = [];
        if (!empty($_GET['status'])) {
            $where .= ' AND o.status = ?';
            $vals[] = $_GET['status'];
        }
        $limit  = min((int)($_GET['limit']  ?? 50), 200);
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : ($page - 1) * $limit;

        $cs = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
        $cs->execute($vals);
        $total = (int)$cs->fetchColumn();

        $s = $pdo->prepare(
            "SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
                    o.address_street, o.address_number, o.address_neighborhood,
                    o.total, o.payment_method, o.payment_status,
                    o.delivery_type, o.status, o.created_at
             FROM orders o
             WHERE $where
             ORDER BY o.created_at DESC
             LIMIT $limit OFFSET $offset"
        );
        $s->execute($vals);
        json_ok(['data' => $s->fetchAll(), 'total' => $total]);
    })(),

    // ── Create order ─────────────────────────────────────────────────────────
    'POST' => (function () use ($pdo) {
        $d = body();

        // Optional: link order to logged-in customer
        $customerId = null;
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) {
            $cs = $pdo->prepare('SELECT customer_id FROM customer_sessions WHERE token = ? AND expires_at > NOW()');
            $cs->execute([$m[1]]);
            $row = $cs->fetch();
            if ($row) $customerId = (int)$row['customer_id'];
        }

        // Validate required fields
        $required = ['customer_name','customer_phone','items','payment_method'];
        foreach ($required as $f) {
            if (empty($d[$f])) json_err("Field '$f' is required");
        }
        if (!is_array($d['items']) || count($d['items']) === 0) json_err('items must be a non-empty array');

        // Fetch live prices from DB to avoid price tampering
        $ids         = array_filter(array_column($d['items'], 'product_id'), 'is_numeric');
        $priceMap    = [];
        if ($ids) {
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $ps  = $pdo->prepare("SELECT id, name, price, has_deposit, deposit_value FROM products WHERE id IN ($ph) AND active = 1");
            $ps->execute(array_values($ids));
            foreach ($ps->fetchAll() as $p) $priceMap[$p['id']] = $p;
        }

        $subtotalCents = 0;
        $includesDeposit = false;
        $lineItems = [];
        foreach ($d['items'] as $item) {
            $pid   = isset($item['product_id']) ? (int)$item['product_id'] : 0;
            $qty   = max(1, (int)($item['quantity'] ?? $item['qty'] ?? 1));
            $pname = $item['product_name'] ?? $item['name'] ?? '';

            // Use DB price if product found, else use submitted price (for custom items)
            $price = isset($priceMap[$pid])
                ? (float)$priceMap[$pid]['price']
                : (float)($item['unit_price'] ?? $item['price'] ?? 0);
            $name  = isset($priceMap[$pid]) ? $priceMap[$pid]['name'] : $pname;

            if ($price <= 0 || !$name) continue;

            $product = $priceMap[$pid] ?? null;
            $deposit = !empty($product['has_deposit']) ? max(0, (float)$product['deposit_value']) : 0;
            $includesDeposit = $includesDeposit || $deposit > 0;
            $unitCents = (int)round($price * 100) + (int)round($deposit * 100);
            $lineCents = $unitCents * $qty;
            $lineItems[] = ['product_id' => $pid ?: null, 'name' => $name, 'qty' => $qty, 'price' => $unitCents / 100, 'total' => $lineCents / 100];
            $subtotalCents += $lineCents;
        }

        if (!$lineItems) json_err('No valid items');
        if (($includesDeposit || isset($d['items_subtotal_cents'])) && ($d['items_subtotal_cents'] ?? null) !== $subtotalCents) {
            json_err('O subtotal dos produtos mudou. Atualize a loja e confira a sacola antes de confirmar.', 422);
        }
        $subtotal = $subtotalCents / 100;

        // Fetch delivery fee and min order from settings
        $cfg = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('delivery_rates','min_order','store_open','fonnte_token','store_whatsapp','pix_key')")->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (($cfg['store_open'] ?? '1') === '0') json_err('Loja fechada no momento', 503);

        $minOrder = (float)($cfg['min_order'] ?? 0);
        if ($subtotal < $minOrder) json_err("Pedido mínimo é R$ " . number_format($minOrder, 2, ',', '.'));

        $neighborhood = $d['address_neighborhood'] ?? null;
        $rates = deliveryRates($cfg['delivery_rates'] ?? null);
        if (!is_string($neighborhood) || $neighborhood === ''
            || !is_string($d['address_street'] ?? null) || trim($d['address_street']) === ''
            || !is_string($d['address_number'] ?? null) || trim($d['address_number']) === '') {
            json_err('Selecione um bairro atendido e informe a rua e o número da entrega.');
        }
        if (!array_key_exists($neighborhood, $rates)) {
            json_err('O bairro selecionado não está disponível. Escolha um bairro atendido.', 409);
        }
        if (($d['delivery_fee_cents'] ?? null) !== $rates[$neighborhood]) {
            json_err('A taxa de entrega mudou. Confira o novo valor antes de confirmar.', 409);
        }

        $deliveryFee = $rates[$neighborhood] / 100;
        $total = round($subtotal + $deliveryFee, 2);

        if ($d['payment_method'] === 'credit_card') {
            $billing = $d['billing_address'] ?? [];
            if (!is_array($billing)
                || !is_string($billing['zip_code'] ?? null) || !preg_match('/^[0-9]{8}$/D', $billing['zip_code'])
                || !is_string($billing['state'] ?? null) || !preg_match('/^[A-Z]{2}$/D', $billing['state'])
                || !is_string($billing['city'] ?? null) || trim($billing['city']) === ''
                || !is_string($billing['line_1'] ?? null) || trim($billing['line_1']) === '') {
                json_err('Informe o endereço de cobrança do cartão, incluindo CEP, cidade e UF.');
            }
            $cardToken    = trim($d['card_token'] ?? '');
            $installments = max(1, (int)($d['installments'] ?? 1));
            $tdsTransId   = trim($d['tds_transaction_id'] ?? '');
            $cardRaw      = is_array($d['card_raw'] ?? null) ? $d['card_raw'] : null;
            $use3ds       = $tdsTransId && $cardRaw && isset($cardRaw['number']);
            if (!$cardToken && !$use3ds) {
                json_err('Token do cartão ou dados 3DS ausentes.');
            }
        }
        $orderNumber = 'PC' . date('ymd') . strtoupper(substr(uniqid(), -4));

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO orders
                 (customer_user_id, order_number, customer_name, customer_phone, customer_email,
                  address_cep, address_street, address_number, address_complement, address_neighborhood, address_reference,
                  subtotal, delivery_fee, total, payment_method, delivery_type, scheduled_at, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $customerId,
                $orderNumber,
                trim($d['customer_name']),
                preg_replace('/\D/', '', $d['customer_phone']),
                $d['customer_email'] ?? null,
                $d['address_cep'] ?? null,
                $d['address_street'] ?? null,
                $d['address_number'] ?? null,
                $d['address_complement'] ?? null,
                $neighborhood,
                $d['address_reference'] ?? null,
                round($subtotal, 2),
                $deliveryFee,
                $total,
                $d['payment_method'],
                $d['delivery_type'] ?? 'immediate',
                !empty($d['scheduled_at']) ? $d['scheduled_at'] : null,
                $d['notes'] ?? null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $itemIns = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, total) VALUES (?,?,?,?,?,?)'
            );
            foreach ($lineItems as $li) {
                $itemIns->execute([$orderId, $li['product_id'], $li['name'], $li['qty'], $li['price'], $li['total']]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            json_err('Erro ao salvar pedido: ' . $e->getMessage(), 500);
        }

        $paymentItems = $lineItems;
        if ($deliveryFee > 0) {
            $paymentItems[] = ['product_id' => 'delivery-fee', 'name' => 'Taxa de entrega', 'qty' => 1, 'price' => $deliveryFee];
        }

        // Pagar.me checkout transparente (cartão de crédito)
        $pmDebugErr  = null;
        $pmPaid      = false;
        if ($d['payment_method'] === 'credit_card') {
            try {
                require_once __DIR__ . '/../lib/pagarme.php';
                $pm = new PagarMe();
                if (!$pm->isConfigured()) {
                    $pmDebugErr = 'NOT_CONFIGURED: PAGARME_SECRET_KEY ausente em config.php';
                } else {
                    $result = $pm->createCreditCardOrder([
                        'order_number'    => $orderNumber,
                        'total'           => $total,
                        'customer_name'   => trim($d['customer_name']),
                        'customer_phone'  => preg_replace('/\D/', '', $d['customer_phone']),
                        'customer_cpf'    => preg_replace('/\D/', '', $d['customer_cpf'] ?? ''),
                        'customer_email'  => $d['customer_email'] ?? '',
                        'billing_address' => $billing,
                        'items'           => $paymentItems,
                    ], $use3ds ? '' : $cardToken, $installments, $use3ds ? $cardRaw : null, $use3ds ? $tdsTransId : null);

                    $pmPaid   = ($result['status'] === 'paid');
                    $pm3dsUrl = $result['threed_secure_url'] ?? null;
                    if ($pmPaid) {
                        $pdo->prepare("UPDATE orders SET payment_status='paid', status='accepted' WHERE id=?")
                            ->execute([$orderId]);
                    } elseif ($pm3dsUrl) {
                        // 3DS authentication required — keep order as processing
                        $pdo->prepare("UPDATE orders SET payment_status='processing', status='accepted' WHERE id=?")
                            ->execute([$orderId]);
                    } elseif ($result['status'] === 'pending') {
                        // Payment processing / waiting confirmation
                        $pdo->prepare("UPDATE orders SET payment_status='processing', status='accepted' WHERE id=?")
                            ->execute([$orderId]);
                    } elseif ($result['status'] === 'failed') {
                        $reason = $result['decline_reason'] ?? null;
                        $pmDebugErr = 'Pagamento recusado' . ($reason ? ': ' . $reason : '. Verifique os dados do cartão e tente novamente.');
                    }
                }
            } catch (\Throwable $pmErr) {
                error_log('Pagar.me error: ' . $pmErr->getMessage());
                $pmDebugErr = $pmErr->getMessage();
            }
            if ($pmDebugErr) {
                // Cancelar pedido e informar erro
                $pdo->prepare("UPDATE orders SET payment_status='failed', status='cancelled' WHERE id=?")->execute([$orderId]);
                json_err($pmDebugErr);
            }
        }

        // Pagar.me Pix e chave manual configurada pela loja
        $pixQrCode    = '';
        $pixQrUrl     = '';
        $storePix     = '';
        $pixExpiresIn = 1800;
        if ($d['payment_method'] === 'pix') {
            try {
                require_once __DIR__ . '/../lib/pagarme.php';
                $pm = new PagarMe();
                if (!$pm->isConfigured()) {
                    throw new \RuntimeException('Pagar.me não configurado');
                } else {
                    $pixResult  = $pm->createPixOrder([
                        'order_number'   => $orderNumber,
                        'total'          => $total,
                        'customer_name'  => trim($d['customer_name']),
                        'customer_phone' => preg_replace('/\D/', '', $d['customer_phone']),
                        'customer_cpf'   => preg_replace('/\D/', '', $d['customer_cpf'] ?? ''),
                        'customer_email' => $d['customer_email'] ?? '',
                        'items'          => $paymentItems,
                    ]);
                    $pixQrCode    = $pixResult['qr_code'];
                    $pixQrUrl     = $pixResult['qr_code_url'];
                    $pixExpiresIn = $pixResult['expires_in'] ?? 1800;
                }
            } catch (\Throwable $pixErr) {
                error_log("Pagar.me Pix {$orderNumber}: " . $pixErr->getMessage());
                $storePix = trim($cfg['pix_key'] ?? '');
                if ($storePix === '') {
                    $pdo->prepare("UPDATE orders SET payment_status='failed', status='cancelled' WHERE id=?")
                        ->execute([$orderId]);
                    json_err('Não foi possível gerar o Pix. Tente novamente ou escolha outra forma de pagamento.', 502);
                }
            }
        }

        // WhatsApp via Fonnte (best-effort, never blocks order creation)
        $fonnteToken = $cfg['fonnte_token'] ?? '';
        $ownerPhone  = preg_replace('/\D/', '', $cfg['store_whatsapp'] ?? '');
        if ($fonnteToken && $ownerPhone) {
            $itemsText = implode("\n", array_map(fn($li) => "• {$li['qty']}x {$li['name']} — R$ " . number_format($li['total'], 2, ',', '.'), $lineItems));
            $payLabel  = ['pix' => 'PIX', 'cash' => 'Dinheiro', 'machine' => 'Maquininha', 'credit_card' => 'Cartão Crédito'][$d['payment_method']] ?? $d['payment_method'];
            $msg = "🍺 *Novo Pedido #{$orderNumber}*\n\n"
                . "*Cliente:* {$d['customer_name']}\n"
                . "*Fone:* {$d['customer_phone']}\n"
                . "*Endereço:* {$d['address_street']}, {$d['address_number']}" . (!empty($d['address_complement']) ? " - {$d['address_complement']}" : '') . " · {$neighborhood}\n"
                . (!empty($d['address_reference']) ? "*Ref:* {$d['address_reference']}\n" : '')
                . "\n*Itens:*\n{$itemsText}\n\n"
                . "*Subtotal:* R$ " . number_format($subtotal, 2, ',', '.') . "\n"
                . ($deliveryFee > 0 ? "*Taxa de entrega:* R$ " . number_format($deliveryFee, 2, ',', '.') . "\n" : '')
                . "*Total:* R$ " . number_format($total, 2, ',', '.') . "\n"
                . "*Pagamento:* {$payLabel}";

            @file_get_contents('https://api.fonnte.com/send', false, stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Authorization: {$fonnteToken}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query(['target' => $ownerPhone, 'message' => $msg]),
                    'timeout' => 5,
                ],
            ]));
        }

        // Web Push notification (best-effort)
        try {
            require_once __DIR__ . '/../lib/webpush.php';
            $vapid = $pdo->query(
                "SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('vapid_public','vapid_private_pem','vapid_subject')"
            )->fetchAll(\PDO::FETCH_KEY_PAIR);
            if (!empty($vapid['vapid_public']) && !empty($vapid['vapid_private_pem'])) {
                $subs = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions")->fetchAll();
                $pvs  = $vapid['vapid_subject'] ?? 'mailto:admin@plantaodacerveja.com.br';
                $msg  = json_encode([
                    'title' => '🍺 Novo pedido!',
                    'body'  => "#{$orderNumber} · {$d['customer_name']} · R$ " . number_format($total, 2, ',', '.'),
                ]);
                foreach ($subs as $s2) {
                    @wp_send(
                        ['endpoint' => $s2['endpoint'], 'keys' => ['p256dh' => $s2['p256dh'], 'auth' => $s2['auth']]],
                        $msg,
                        $vapid['vapid_public'],
                        $vapid['vapid_private_pem'],
                        $pvs
                    );
                }
            }
        } catch (\Throwable $e2) { /* best-effort */ }

        $resp = ['success' => true, 'id' => $orderId, 'order_number' => $orderNumber, 'total' => $total];
        if (!empty($pm3dsUrl)) $resp['threed_secure_url'] = $pm3dsUrl;
        if ($pixQrCode || $pixQrUrl) {
            $resp['pix_qr_code'] = $pixQrCode;
            $resp['pix_qr_url'] = $pixQrUrl;
            $resp['pix_expires_in'] = $pixExpiresIn;
        } elseif ($storePix !== '') {
            $resp['pix_key'] = $storePix;
        }
        json_ok($resp, 201);
    })(),

    // ── Delete order ─────────────────────────────────────────────────────────
    'DELETE' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$id]);
        json_ok(['deleted' => true]);
    })(),

    // ── Update status ─────────────────────────────────────────────────────────
    'PUT', 'PATCH' => (function () use ($pdo, $id) {
        if (!$id) json_err('ID required');
        $d = body();
        $allowed = ['status','payment_status','notes','whatsapp_sent'];
        $fields  = [];
        $vals    = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $d)) { $fields[] = "$f = ?"; $vals[] = $d[$f]; }
        }
        if (!$fields) json_err('Nothing to update');
        $vals[] = $id;
        $pdo->prepare('UPDATE orders SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
        json_ok(['updated' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
