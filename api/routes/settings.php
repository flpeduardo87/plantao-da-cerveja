<?php
// GET  /settings          — public settings (store_name, min_order, delivery_fee, etc.)
// GET  /settings/all      — all settings [admin]
// POST /settings          — upsert settings [admin]
// PUT  /settings          — upsert settings [admin] (same as POST, used by admin JS)
// PUT  /settings/secrets  — save sensitive credentials [admin] (Fonnte, Stone)

$pdo = db();

$PUBLIC_KEYS = ['store_name','min_order','delivery_fee','delivery_time_min','delivery_time_max','store_open','store_whatsapp','store_hours'];

// Shared upsert logic
$upsert = function (array $d) use ($pdo): void {
    if (!$d) json_err('Body required');
    if (isset($d['key'])) $d = [$d['key'] => $d['value'] ?? ''];
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
            json_ok($out);
        } else {
            $ph = implode(',', array_fill(0, count($PUBLIC_KEYS), '?'));
            $s  = $pdo->prepare("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ($ph)");
            $s->execute($PUBLIC_KEYS);
            $out = [];
            foreach ($s->fetchAll() as $r) $out[$r['setting_key']] = $r['setting_value'];
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
