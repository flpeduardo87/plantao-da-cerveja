<?php
// GET  /push          — returns VAPID public key (auto-generates keys on first call)
// POST /push          — save push subscription
// DELETE /push        — remove subscription

require_once __DIR__ . '/../lib/webpush.php';

$pdo = db();

function get_vapid_settings(PDO $pdo): array {
    $rows = $pdo->query(
        "SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ('vapid_public','vapid_private_pem','vapid_subject')"
    )->fetchAll(\PDO::FETCH_KEY_PAIR);

    if (empty($rows['vapid_public'])) {
        $keys    = wp_generate_keys();
        $subject = 'mailto:admin@plantaodacerveja.com.br';
        $upsert  = $pdo->prepare(
            "INSERT INTO store_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)"
        );
        $upsert->execute(['vapid_public',      $keys['public_b64u']]);
        $upsert->execute(['vapid_private_pem', $keys['private_pem']]);
        $upsert->execute(['vapid_subject',     $subject]);
        return ['public' => $keys['public_b64u'], 'private_pem' => $keys['private_pem'], 'subject' => $subject];
    }

    return [
        'public'      => $rows['vapid_public'],
        'private_pem' => $rows['vapid_private_pem'] ?? '',
        'subject'     => $rows['vapid_subject']     ?? 'mailto:admin@plantaodacerveja.com.br',
    ];
}

match ($method) {
    'GET' => (function () use ($pdo) {
        $v = get_vapid_settings($pdo);
        json_ok(['public_key' => $v['public']]);
    })(),

    'POST' => (function () use ($pdo) {
        $b = body();
        if (empty($b['endpoint']) || empty($b['keys']['auth']) || empty($b['keys']['p256dh']))
            json_err('Invalid subscription');
        $pdo->prepare(
            "INSERT INTO push_subscriptions (endpoint, p256dh, auth, user_agent)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), user_agent=VALUES(user_agent)"
        )->execute([$b['endpoint'], $b['keys']['p256dh'], $b['keys']['auth'], $_SERVER['HTTP_USER_AGENT'] ?? null]);
        json_ok(['ok' => true], 201);
    })(),

    'DELETE' => (function () use ($pdo) {
        $b = body();
        if (empty($b['endpoint'])) json_err('Missing endpoint');
        $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint=?")->execute([$b['endpoint']]);
        json_ok(['ok' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
