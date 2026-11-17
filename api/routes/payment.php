<?php
// POST /payment/webhook  — Pagar.me v5 webhook (público, verificado por HMAC)

require_once __DIR__ . '/../lib/pagarme.php';

$pdo = db();

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

// order_number fica em data.code (evento order) ou data.order.code (evento charge)
$orderNumber = $data['code'] ?? $data['order']['code'] ?? '';

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
