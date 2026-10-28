<?php
// GET  /orders            — list orders [admin]  ?status=new&limit=50&offset=0
// GET  /orders/{id}       — single order [admin]
// POST /orders            — create order [public]
// PATCH /orders/{id}      — update status [admin]

$pdo = db();

match ($method) {
    // ── List / Single ────────────────────────────────────────────────────────
    'GET' => (function () use ($pdo, $id) {
        // Public tracking by order_number + phone (no auth required)
        if (!empty($_GET['order_number']) && !empty($_GET['phone'])) {
            $phone = preg_replace('/\D/', '', $_GET['phone']);
            $s = $pdo->prepare(
                'SELECT o.order_number, o.status, o.created_at, o.total, o.payment_method, o.delivery_type,
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
            json_ok($row);
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
            $ps  = $pdo->prepare("SELECT id, name, price FROM products WHERE id IN ($ph) AND active = 1");
            $ps->execute(array_values($ids));
            foreach ($ps->fetchAll() as $p) $priceMap[$p['id']] = $p;
        }

        $subtotal = 0;
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

            $lineItems[] = ['product_id' => $pid ?: null, 'name' => $name, 'qty' => $qty, 'price' => $price, 'total' => round($price * $qty, 2)];
            $subtotal   += $price * $qty;
        }

        if (!$lineItems) json_err('No valid items');

        // Fetch delivery fee and min order from settings
        $cfg = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('delivery_fee','min_order','store_open','fonnte_token','store_whatsapp')")->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (($cfg['store_open'] ?? '1') === '0') json_err('Loja fechada no momento', 503);

        $minOrder = (float)($cfg['min_order'] ?? 0);
        if ($subtotal < $minOrder) json_err("Pedido mínimo é R$ " . number_format($minOrder, 2, ',', '.'));

        $deliveryFee = (float)($cfg['delivery_fee'] ?? 0);
        $total       = round($subtotal + $deliveryFee, 2);
        $orderNumber = 'PC' . date('ymd') . strtoupper(substr(uniqid(), -4));

        $pdo->beginTransaction();
        try {
            $ins = $pdo->prepare(
                'INSERT INTO orders
                 (order_number, customer_name, customer_phone, customer_email,
                  address_cep, address_street, address_number, address_complement, address_neighborhood, address_reference,
                  subtotal, delivery_fee, total, payment_method, delivery_type, scheduled_at, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $ins->execute([
                $orderNumber,
                trim($d['customer_name']),
                preg_replace('/\D/', '', $d['customer_phone']),
                $d['customer_email'] ?? null,
                $d['address_cep'] ?? null,
                $d['address_street'] ?? null,
                $d['address_number'] ?? null,
                $d['address_complement'] ?? null,
                $d['address_neighborhood'] ?? null,
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
            json_err('Erro ao salvar pedido', 500);
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
                . "*Endereço:* {$d['address_street']}, {$d['address_number']}" . (!empty($d['address_complement']) ? " - {$d['address_complement']}" : '') . "\n"
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

        json_ok(['id' => $orderId, 'order_number' => $orderNumber, 'total' => $total], 201);
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
