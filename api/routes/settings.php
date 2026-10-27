<?php
// GET  /settings          — public settings (store_name, min_order, delivery_fee, delivery_time_*, store_open)
// GET  /settings/all      — all settings [admin]
// POST /settings          — upsert one or many settings [admin]

$pdo = db();

// Keys safe to expose publicly
$PUBLIC_KEYS = ['store_name','min_order','delivery_fee','delivery_time_min','delivery_time_max','store_open','store_whatsapp'];

match ($method) {
    'GET' => (function () use ($pdo, $sub, $PUBLIC_KEYS) {
        if ($sub === 'all') {
            $rows = $pdo->query('SELECT setting_key, setting_value FROM store_settings ORDER BY setting_key')->fetchAll();
            $out  = [];
            foreach ($rows as $r) $out[$r['setting_key']] = $r['setting_value'];
            json_ok($out);
        } else {
            $placeholders = implode(',', array_fill(0, count($PUBLIC_KEYS), '?'));
            $s = $pdo->prepare("SELECT setting_key, setting_value FROM store_settings WHERE setting_key IN ($placeholders)");
            $s->execute($PUBLIC_KEYS);
            $out = [];
            foreach ($s->fetchAll() as $r) $out[$r['setting_key']] = $r['setting_value'];
            json_ok($out);
        }
    })(),

    'POST' => (function () use ($pdo) {
        $d = body();
        if (!$d) json_err('Body required');
        // Accept either {"key":"val"} flat map or [{"key":"k","value":"v"}] array
        if (isset($d['key'])) $d = [$d['key'] => $d['value'] ?? ''];
        $s = $pdo->prepare(
            'INSERT INTO store_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($d as $k => $v) {
            if (is_string($k)) $s->execute([$k, (string)$v]);
        }
        json_ok(['saved' => true]);
    })(),

    default => json_err('Method not allowed', 405),
};
