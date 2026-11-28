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
        // Entrega finaliza sozinha 30 min depois de "saiu para entrega" (retirada no balcão continua manual)
        try {
            $pdo->exec("UPDATE orders SET status = 'delivered'
                        WHERE status = 'delivering' AND delivery_type <> 'pickup'
                          AND updated_at <= (NOW() - INTERVAL 30 MINUTE)");
        } catch (\Throwable $e) { error_log('auto-entrega: ' . $e->getMessage()); }

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
        // Pagamento online só aparece no painel depois de pago (PIX/cartão ainda aguardando ficam ocultos)
        $where .= " AND NOT (
                        (o.payment_method = 'pix'
                           AND o.payment_status IN ('pending','processing','failed')
                           AND o.status IN ('new','cancelled'))
                     OR (o.payment_method IN ('credit_card','debit_card')
                           AND o.payment_status IN ('pending','processing')
                           AND o.status = 'new')
                    )";
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

        if ($d['payment_method'] === 'pix') {
            $email = is_string($d['customer_email'] ?? null) ? trim($d['customer_email']) : '';
            if (strlen($email) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_err('Informe um e-mail válido para gerar o Pix.', 422);
            }
            $cpf = is_string($d['customer_cpf'] ?? null) ? preg_replace('/\D/', '', $d['customer_cpf']) : '';
            if (strlen($cpf) !== 11) json_err('Informe o CPF do pagador com 11 dígitos.', 422);
            $d['customer_cpf'] = $cpf;
            $d['customer_email'] = $email;
        }

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
        $hasReturnableItems = false;
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
            $hasReturnableItems = $hasReturnableItems || $deposit > 0;
            $returning = $deposit > 0 && ($item['return_container'] ?? false) === true;
            if ($returning) {
                preg_match('/^.{0,175}/us', $name, $shortName);
                $name = $shortName[0] . ' (troca de casco)';
            }
            $unitCents = (int)round($price * 100) + ($returning ? 0 : (int)round($deposit * 100));
            $lineCents = $unitCents * $qty;
            $lineItems[] = ['product_id' => $pid ?: null, 'name' => $name, 'qty' => $qty, 'price' => $unitCents / 100, 'total' => $lineCents / 100];
            $subtotalCents += $lineCents;
        }

        if (!$lineItems) json_err('No valid items');
        if (($hasReturnableItems || isset($d['items_subtotal_cents'])) && ($d['items_subtotal_cents'] ?? null) !== $subtotalCents) {
            json_err('O subtotal dos produtos mudou. Atualize a loja e confira a sacola antes de confirmar.', 422);
        }
        $subtotal = $subtotalCents / 100;

        // Fetch delivery fee and min order from settings
        $cfg = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('delivery_rates','min_order','store_open','fonnte_token','store_whatsapp','pix_key')")->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (($cfg['store_open'] ?? '1') === '0') json_err('Loja fechada no momento', 503);

        $minOrder = (float)($cfg['min_order'] ?? 0);
        if ($subtotal < $minOrder) json_err("Pedido mínimo é R$ " . number_format($minOrder, 2, ',', '.'));

        $deliveryType = $d['delivery_type'] ?? 'immediate';
        if (!in_array($deliveryType, ['immediate', 'scheduled', 'pickup'], true)) {
            json_err('Escolha uma forma de recebimento válida.');
        }
        $pickup = $deliveryType === 'pickup';
        $neighborhood = null;
        $deliveryFee = 0;
        if (!$pickup) {
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
        }
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
            error_log('3DS cartao: ' . ($use3ds ? 'autenticado' : 'sem 3DS') . ' ' . substr(preg_replace('/[^\w=|:. -]/', '', (string)($d['tds_note'] ?? '')), 0, 200));
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
                $pickup ? null : ($d['address_cep'] ?? null),
                $pickup ? null : ($d['address_street'] ?? null),
                $pickup ? null : ($d['address_number'] ?? null),
                $pickup ? null : ($d['address_complement'] ?? null),
                $neighborhood,
                $pickup ? null : ($d['address_reference'] ?? null),
                round($subtotal, 2),
                $deliveryFee,
                $total,
                $d['payment_method'],
                $deliveryType,
                $deliveryType === 'scheduled' && !empty($d['scheduled_at']) ? $d['scheduled_at'] : null,
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
                        // Pago: continua "Novo" para tocar a sirene até o lojista aceitar
                        $pdo->prepare("UPDATE orders SET payment_status='paid' WHERE id=?")
                            ->execute([$orderId]);
                    } elseif ($pm3dsUrl) {
                        // 3DS authentication required — aguardando pagamento (oculto no painel até pagar)
                        $pdo->prepare("UPDATE orders SET payment_status='processing' WHERE id=?")
                            ->execute([$orderId]);
                    } elseif ($result['status'] === 'pending') {
                        // Payment processing / waiting confirmation (oculto no painel até pagar)
                        $pdo->prepare("UPDATE orders SET payment_status='processing' WHERE id=?")
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

        // Avisos ao lojista (WhatsApp + push). Pagamento online (PIX/cartão) só avisa depois de confirmado
        // (cartão aprovado na hora avisa aqui; os demais pelo webhook). Dinheiro/maquininha avisam já.
        $onlinePay = in_array($d['payment_method'], ['pix', 'credit_card', 'debit_card'], true);
        if (!$onlinePay || $pmPaid) {
            require_once __DIR__ . '/../lib/notify.php';
            notify_new_order($pdo, $orderNumber);
        }

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
