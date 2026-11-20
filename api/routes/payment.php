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
    $sets   = ['payment_status = ?'];
    $vals   = [$update['payment_status']];

    if ($update['status']) {
        $sets[] = 'status = ?';
        $vals[] = $update['status'];
    }

    $vals[] = $orderNumber;
    $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE order_number = ?')
        ->execute($vals);
}

json_ok(['received' => true]);
