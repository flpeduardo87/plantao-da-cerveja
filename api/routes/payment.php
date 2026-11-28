<?php
// GET  /payment/config   — retorna public key para tokenização client-side
// POST /payment/webhook  — Pagar.me v5 webhook (público, verificado por HMAC)

require_once __DIR__ . '/../lib/pagarme.php';

$pdo = db();

// ── Config pública ────────────────────────────────────────────────────────────
if ($method === 'GET' && ($sub ?? '') === 'config') {
    json_ok(['public_key' => defined('PAGARME_PUBLIC_KEY') ? PAGARME_PUBLIC_KEY : '']);
}

// ── Token de sessão 3DS (Stone SDK) ──────────────────────────────────────────
if ($method === 'GET' && ($sub ?? '') === '3ds-token') {
    $pm = new PagarMe();
    if (!$pm->isConfigured()) json_err('Pagar.me não configurado', 503);
    try {
        $token = $pm->get3dsToken();
        json_ok(['tds_token' => $token]);
    } catch (\Throwable $e) {
        json_err('3DS token indisponível: ' . $e->getMessage(), 503);
    }
}

if ($method !== 'POST') json_err('Method not allowed', 405);
if (($sub ?? '') !== 'webhook') json_err('Not found', 404);

$rawBody = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE'] ?? '';
$pm      = new PagarMe();

// Verificar assinatura se Pagar.me estiver configurado
if ($pm->isConfigured() && $sig && !$pm->verifyWebhook($rawBody, $sig)) {
    json_err('Invalid signature', 401);
}

$event = json_decode($rawBody, true) ?? [];
$type  = $event['type'] ?? '';
$data  = $event['data'] ?? [];

// Pagar.me v5: eventos de pedido e cobrança
$statusMap = [
    'order.paid'            => ['payment_status' => 'paid',   'status' => 'accepted'],
    'order.payment_failed'  => ['payment_status' => 'failed', 'status' => null],
    'order.canceled'        => ['payment_status' => 'failed', 'status' => 'cancelled'],
    'charge.paid'           => ['payment_status' => 'paid',   'status' => 'accepted'],
    'charge.payment_failed' => ['payment_status' => 'failed', 'status' => null],
    'charge.refunded'       => ['payment_status' => 'refunded', 'status' => 'cancelled'],
];

if (!isset($statusMap[$type])) {
    json_ok(['received' => true]);
}

// order.* events: data IS the order, data['code'] = our order_number
// charge.* events: data IS the charge, data['code'] = charge code, data['order']['code'] = our order_number
if (str_starts_with($type, 'order.')) {
    $orderNumber = $data['code'] ?? '';
} else {
    $orderNumber = $data['order']['code'] ?? $data['code'] ?? '';
}

if ($orderNumber) {
    $update = $statusMap[$type];

    // Estado anterior (para avisar o lojista só na 1ª confirmação de um PIX)
    $prev = $pdo->prepare('SELECT payment_method, payment_status FROM orders WHERE order_number = ?');
    $prev->execute([$orderNumber]);
    $prevRow = $prev->fetch() ?: null;
    $sets   = [];
    $vals   = [];
    $where  = 'order_number = ?';

    if ($update['payment_status'] === 'failed') {
        // Nunca sobrescreve um pagamento já aprovado ou estornado com "falhou"
        $sets[] = "payment_status = IF(payment_status IN ('pending','failed'), 'failed', payment_status)";
    } elseif ($update['payment_status'] === 'refunded') {
        // Estorno só faz sentido para pagamento que foi aprovado
        $sets[]  = "payment_status = 'refunded'";
        $where  .= " AND payment_status IN ('paid','refunded')";
    } else {
        $sets[] = 'payment_status = ?';
        $vals[] = $update['payment_status'];
    }

    if ($update['status']) {
        if ($update['payment_status'] === 'paid') {
            // PIX pago fica como "Novo" para tocar o alarme até o lojista aceitar.
            // Demais métodos: aceita automaticamente, mas nunca volta um pedido já em andamento/cancelado.
            if (!($prevRow && $prevRow['payment_method'] === 'pix')) {
                $sets[] = "status = IF(status = 'new', ?, status)";
                $vals[] = $update['status'];
            }
        } else {
            $sets[] = 'status = ?';
            $vals[] = $update['status'];
        }
    }

    $vals[] = $orderNumber;
    $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . " WHERE $where")
        ->execute($vals);

    // PIX confirmado: o pedido entra no painel como "Novo" (toca o alarme) e o lojista é avisado
    if ($update['payment_status'] === 'paid' && $prevRow
        && $prevRow['payment_method'] === 'pix' && $prevRow['payment_status'] !== 'paid') {
        try {
            require_once __DIR__ . '/../lib/notify.php';
            notify_new_order($pdo, $orderNumber);
        } catch (\Throwable $e) { error_log('notify PIX pago: ' . $e->getMessage()); }
    }
}

json_ok(['received' => true]);
