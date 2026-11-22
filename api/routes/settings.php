<?php
// GET  /settings          — public settings (store_name, min_order, delivery_fee, etc.)
// GET  /settings/all      — all settings [admin]
// POST /settings          — upsert settings [admin]
// PUT  /settings          — upsert settings [admin] (same as POST, used by admin JS)
// PUT  /settings/secrets  — save sensitive credentials [admin] (Fonnte, Stone)

require_once __DIR__ . '/../lib/delivery.php';

$pdo = db();

$PUBLIC_KEYS = ['store_name','min_order','delivery_fee','delivery_rates','delivery_time_min','delivery_time_max','store_open','store_whatsapp','store_hours','pix_key'];

// Shared upsert logic
$upsert = function (array $d) use ($pdo): void {
    if (!$d) json_err('Body required');
    if (isset($d['key'])) $d = [$d['key'] => $d['value'] ?? ''];
    // Match only canonical keys before the database applies its case-insensitive collation.
    $allowed = ['store_name','min_order','delivery_fee','delivery_rates','delivery_time_min',
        'delivery_time_max','store_open','store_whatsapp','store_hours','pix_key',
        'fonnte_token','stone_client_id','stone_client_secret','stone_sandbox'];
    if (array_key_exists('delivery_rates', $d)) {
        $email = trim((string)($d['delivery_admin_email'] ?? ''));
        $password = (string)($d['delivery_admin_password'] ?? '');
        $admin = $pdo->prepare('SELECT password_hash FROM admin_users WHERE email = ? AND active = 1');
        $admin->execute([strtolower($email)]);
        $hash = $admin->fetchColumn();
        if (!$hash || !password_verify($password, $hash)) {
            json_err('Confirme a senha de administrador para alterar as taxas.', 403);
        }

        $rates = $d['delivery_rates'];
        if (!is_array($rates) || !$rates) json_err('Informe pelo menos um bairro com taxa de entrega.');
        foreach ($rates as $name => $fee) {
            if (!is_string($name) || !preg_match('/^[^<>&|\p{C}]{1,100}$/u', $name)
                || trim($name) !== $name || !is_int($fee) || $fee < 0 || $fee > 99999999) {
                json_err('Informe bairros válidos e taxas em centavos.');
            }
        }
        $d['delivery_rates'] = json_encode($rates, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($d['delivery_rates']) > 65535) json_err('A tabela de taxas é grande demais.');
    }
    $d = array_intersect_key($d, array_flip($allowed));
    if (!$d) json_err('Nenhuma configuração válida foi informada.');
    $s = $pdo->prepare(
        'INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($d as $k => $v) {
        if (is_string($k)) $s->execute([$k, (string)$v]);
    }
    json_ok(['saved' => true]);
};

match ($method) {
    'GET' => (function () use ($pdo, $sub, $PUBLIC_KEYS) {
        if ($sub === 'all') {
            $rows = $pdo->query('SELECT setting_key, setting_value FROM store_settings ORDER BY setting_key')->fetchAll();
            $out  = [];
            foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
            $out['delivery_rates'] = deliveryRates($out['delivery_rates'] ?? null);
            json_ok($out);
        } else {
            $ph = implode(',', array_fill(0, count($PUBLIC_KEYS), '?'));
            $s  = $pdo->prepare("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ($ph)");
            $s->execute($PUBLIC_KEYS);
            $out = [];
            foreach ($s->fetchAll() as $r) $out[$r['setting_key']] = $r['setting_value'];
            $out['delivery_rates'] = deliveryRates($out['delivery_rates'] ?? null);
            json_ok($out);
        }
    })(),

    'POST', 'PUT' => (function () use ($pdo, $sub, $upsert) {
        $d = body();
        if ($sub === 'secrets') {
            // Only allow known credential keys
            $allowed = ['fonnte_token','store_whatsapp','stone_client_id','stone_client_secret','stone_sandbox'];
            $filtered = array_intersect_key($d, array_flip($allowed));
            if (!$filtered) json_err('No valid keys');
            ($upsert)($filtered);
        } else {
            ($upsert)($d);
        }
    })(),

    default => json_err('Method not allowed', 405),
};
