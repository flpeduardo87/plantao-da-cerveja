<?php
// Avisos de novo pedido para o lojista: WhatsApp (Fonnte) + Web Push.
// Para PIX, só é chamado depois da confirmação do pagamento (webhook).

function notify_new_order(PDO $pdo, string $orderNumber): void {
    $s = $pdo->prepare('SELECT * FROM orders WHERE order_number = ?');
    $s->execute([$orderNumber]);
    $o = $s->fetch();
    if (!$o) return;

    $is = $pdo->prepare('SELECT product_name, quantity, total FROM order_items WHERE order_id = ?');
    $is->execute([$o['id']]);
    $items = $is->fetchAll();

    $cfg = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('fonnte_token','store_whatsapp')")->fetchAll(\PDO::FETCH_KEY_PAIR);
    $fonnteToken = $cfg['fonnte_token'] ?? '';
    $ownerPhone  = preg_replace('/\D/', '', $cfg['store_whatsapp'] ?? '');
    $pickup      = ($o['delivery_type'] ?? '') === 'pickup';
    $total       = (float)$o['total'];
    $deliveryFee = (float)$o['delivery_fee'];

    // WhatsApp via Fonnte (best-effort)
    if ($fonnteToken && $ownerPhone) {
        $itemsText = implode("\n", array_map(fn($li) => "• {$li['quantity']}x {$li['product_name']} — R$ " . number_format((float)$li['total'], 2, ',', '.'), $items));
        $payLabel  = ['pix' => 'PIX', 'cash' => 'Dinheiro', 'machine' => 'Maquininha', 'credit_card' => 'Cartão Crédito'][$o['payment_method']] ?? $o['payment_method'];
        $msg = "🍺 *Novo Pedido #{$orderNumber}*\n\n"
            . "*Cliente:* {$o['customer_name']}\n"
            . "*Fone:* {$o['customer_phone']}\n"
            . ($pickup
                ? "*Recebimento:* Retirada no balcão\n"
                : "*Endereço:* {$o['address_street']}, {$o['address_number']}"
                    . (!empty($o['address_complement']) ? " - {$o['address_complement']}" : '')
                    . " · {$o['address_neighborhood']}\n"
                    . (!empty($o['address_reference']) ? "*Ref:* {$o['address_reference']}\n" : ''))
            . "\n*Itens:*\n{$itemsText}\n\n"
            . "*Subtotal:* R$ " . number_format((float)$o['subtotal'], 2, ',', '.') . "\n"
            . ($deliveryFee > 0 ? "*Taxa de entrega:* R$ " . number_format($deliveryFee, 2, ',', '.') . "\n" : '')
            . "*Total:* R$ " . number_format($total, 2, ',', '.') . "\n"
            . "*Pagamento:* {$payLabel}" . ($o['payment_method'] === 'pix' ? ' (pago ✅)' : '');

        @file_get_contents('https://api.fonnte.com/send', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Authorization: {$fonnteToken}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query(['target' => $ownerPhone, 'message' => $msg]),
                'timeout' => 5,
            ],
        ]));
    }

    // Web Push (best-effort)
    try {
        require_once __DIR__ . '/webpush.php';
        $vapid = $pdo->query(
            "SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('vapid_public','vapid_private_pem','vapid_subject')"
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        if (!empty($vapid['vapid_public']) && !empty($vapid['vapid_private_pem'])) {
            $subs = $pdo->query("SELECT endpoint, p256dh, auth FROM push_subscriptions")->fetchAll();
            $pvs  = $vapid['vapid_subject'] ?? 'mailto:admin@plantaodacerveja.com.br';
            $msg  = json_encode([
                'title' => '🍺 Novo pedido!',
                'body'  => "#{$orderNumber} · {$o['customer_name']} · R$ " . number_format($total, 2, ',', '.'),
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
}
