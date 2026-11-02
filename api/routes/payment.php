<?php
// POST /payment/webhook  — Stone payment status webhook (public, signature-verified)

require_once __DIR__ . '/../lib/stone.php';

$pdo = db();

if ($method !== 'POST') json_err('Method not allowed', 405);
if (($sub ?? '') !== 'webhook') json_err('Not found', 404);

$rawBody  = file_get_contents('php://input');
$sig      = $_SERVER['HTTP_X_STONE_SIGNATURE'] ?? '';
$stone    = new Stone();

// Verify signature if Stone is configured (skip verification in dev when key not set)
if ($stone->isConfigured() && $sig && !$stone->verifyWebhook($rawBody, $sig)) {
    json_err('Invalid signature', 401);
}

$event = json_decode($rawBody, true) ?? [];
$type  = $event['type'] ?? '';

// Stone sends events like "payment_link.paid", "payment_link.expired", etc.
$statusMap = [
    'payment_link.paid'     => ['payment_status' => 'paid',    'status' => 'accepted'],
    'payment_link.expired'  => ['payment_status' => 'expired', 'status' => null],
    'payment_link.canceled' => ['payment_status' => 'expired', 'status' => null],
];

if (!isset($statusMap[$type])) {
    // Unknown event — acknowledge silently
    json_ok(['received' => true]);
}

$meta        = $event['data']['metadata'] ?? $event['metadata'] ?? [];
$orderNumber = $meta['order_number'] ?? '';

if ($orderNumber) {
    $update = $statusMap[$type];
    $sets   = ['payment_status = ?'];
    $vals   = [$update['payment_status']];

    if ($update['status']) {
        $sets[] = 'status = ?';
        $vals[] = $update['status'];
    }

    $vals[]  = $orderNumber;
    $pdo->prepare('UPDATE orders SET ' . implode(', ', $sets) . ' WHERE order_number = ?')
        ->execute($vals);
}

json_ok(['received' => true]);
