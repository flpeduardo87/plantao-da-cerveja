<?php
// POST /customer/otp      — send 4-digit OTP via WhatsApp
// POST /customer/verify   — verify OTP → session token
// GET  /customer/orders   — list customer's orders  [auth]
// GET  /customer/me       — get customer profile    [auth]
// DELETE /customer/session — logout                 [auth]

$pdo = db();

function customer_auth(\PDO $pdo): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) json_err('Não autorizado', 401);
    $s = $pdo->prepare("SELECT phone FROM customer_sessions WHERE token = ? AND expires_at > NOW()");
    $s->execute([$m[1]]);
    $phone = $s->fetchColumn();
    if (!$phone) json_err('Sessão expirada. Faça login novamente.', 401);
    return (string)$phone;
}

$action = $sub ?? '';

match (true) {

    // ── Send OTP ──────────────────────────────────────────────────────────────
    $method === 'POST' && $action === 'otp' => (function() use ($pdo) {
        $d = body();
        $phone = preg_replace('/\D/', '', $d['phone'] ?? '');
        if (strlen($phone) < 10) json_err('Telefone inválido');

        // Rate limit: max 3 per 10 min
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM customer_otps WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        $cnt->execute([$phone]);
        if ((int)$cnt->fetchColumn() >= 3) json_err('Muitas tentativas. Aguarde alguns minutos.', 429);

        $code = str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $pdo->prepare("INSERT INTO customer_otps (phone, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))")
            ->execute([$phone, $code]);

        $cfg = $pdo->query("SELECT setting_key, setting_value FROM store_settings WHERE setting_key = 'fonnte_token'")->fetchAll(\PDO::FETCH_KEY_PAIR);
        $fonnteToken = $cfg['fonnte_token'] ?? '';
        if (!$fonnteToken) json_err('Serviço de verificação não configurado', 503);

        $msg = "🍺 *Plantão da Cerveja*\n\nSeu código de acesso: *{$code}*\n\nVálido por 10 minutos.";
        @file_get_contents('https://api.fonnte.com/send', false, stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Authorization: {$fonnteToken}\r\nContent-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query(['target' => $phone, 'message' => $msg]),
                'timeout' => 5,
            ],
        ]));

        json_ok(['success' => true, 'message' => 'Código enviado via WhatsApp']);
    })(),

    // ── Verify OTP ────────────────────────────────────────────────────────────
    $method === 'POST' && $action === 'verify' => (function() use ($pdo) {
        $d = body();
        $phone = preg_replace('/\D/', '', $d['phone'] ?? '');
        $code  = trim($d['code'] ?? '');
        if (!$phone || !$code) json_err('Dados inválidos');

        $s = $pdo->prepare("SELECT id FROM customer_otps WHERE phone = ? AND code = ? AND expires_at > NOW() AND used = 0 ORDER BY id DESC LIMIT 1");
        $s->execute([$phone, $code]);
        $otpId = $s->fetchColumn();
        if (!$otpId) json_err('Código inválido ou expirado', 401);

        $pdo->prepare("UPDATE customer_otps SET used = 1 WHERE id = ?")->execute([$otpId]);

        $token = bin2hex(random_bytes(32));
        $pdo->prepare("INSERT INTO customer_sessions (phone, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))")
            ->execute([$phone, $token]);

        // Get customer name from last order
        $info = $pdo->prepare("SELECT customer_name FROM orders WHERE customer_phone = ? ORDER BY id DESC LIMIT 1");
        $info->execute([$phone]);
        $name = $info->fetchColumn() ?: '';

        json_ok(['success' => true, 'token' => $token, 'name' => $name]);
    })(),

    // ── Orders ────────────────────────────────────────────────────────────────
    $method === 'GET' && $action === 'orders' => (function() use ($pdo) {
        $phone = customer_auth($pdo);
        $s = $pdo->prepare(
            "SELECT id, order_number, status, payment_method, delivery_type,
                    subtotal, delivery_fee, total, address_street, address_number,
                    notes, created_at
             FROM orders WHERE customer_phone = ?
             ORDER BY created_at DESC LIMIT 30"
        );
        $s->execute([$phone]);
        json_ok(['success' => true, 'orders' => $s->fetchAll()]);
    })(),

    // ── Profile ───────────────────────────────────────────────────────────────
    $method === 'GET' && $action === 'me' => (function() use ($pdo) {
        $phone = customer_auth($pdo);
        $s = $pdo->prepare(
            "SELECT customer_name, customer_phone, customer_email,
                    address_cep, address_street, address_number,
                    address_complement, address_neighborhood
             FROM orders WHERE customer_phone = ? ORDER BY id DESC LIMIT 1"
        );
        $s->execute([$phone]);
        $row = $s->fetch() ?: ['customer_phone' => $phone];
        json_ok(['success' => true, 'customer' => $row]);
    })(),

    // ── Logout ────────────────────────────────────────────────────────────────
    $method === 'DELETE' => (function() use ($pdo) {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(\S+)$/i', $h, $m)) {
            $pdo->prepare("DELETE FROM customer_sessions WHERE token = ?")->execute([$m[1]]);
        }
        json_ok(['success' => true]);
    })(),

    default => json_err('Not found', 404),
};
