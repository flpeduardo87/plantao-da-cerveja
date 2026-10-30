<?php
// POST /customer/register   — criar conta (nome, email, senha, telefone)
// POST /customer/login      — autenticar (email, senha) → token
// POST /customer/logout     — invalidar token
// GET  /customer/me         — dados do cliente autenticado
// PUT  /customer/me         — atualizar nome/telefone/senha
// GET  /customer/orders     — pedidos do cliente autenticado
// POST /customer/address    — salvar/atualizar endereço padrão
// GET  /customer/address    — buscar endereço padrão

$pdo = db();

// ── Helper: autenticar token do cliente ─────────────────────────────────────
function customerAuth(\PDO $pdo): array {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : '';
    if (!$token) json_err('Não autenticado', 401);
    $s = $pdo->prepare(
        'SELECT cu.* FROM customer_sessions cs
         JOIN customer_users cu ON cu.id = cs.customer_id
         WHERE cs.token = ? AND cs.expires_at > NOW()'
    );
    $s->execute([$token]);
    $user = $s->fetch();
    if (!$user) json_err('Sessão inválida ou expirada', 401);
    return $user;
}

match ($method) {

    // ── Registro ─────────────────────────────────────────────────────────────
    'POST' => (function () use ($pdo, $sub) {
        match ($sub) {

            'register' => (function () use ($pdo) {
                $d = body();
                if (empty($d['name']) || empty($d['email']) || empty($d['password'])) {
                    json_err('Nome, e-mail e senha são obrigatórios');
                }
                if (strlen($d['password']) < 6) json_err('Senha deve ter pelo menos 6 caracteres');
                $email = strtolower(trim($d['email']));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('E-mail inválido');

                // Check duplicate
                $chk = $pdo->prepare('SELECT id FROM customer_users WHERE email = ?');
                $chk->execute([$email]);
                if ($chk->fetch()) json_err('E-mail já cadastrado', 409);

                $hash = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 10]);
                $ins  = $pdo->prepare(
                    'INSERT INTO customer_users (name, email, password_hash, phone) VALUES (?,?,?,?)'
                );
                $ins->execute([
                    trim($d['name']),
                    $email,
                    $hash,
                    isset($d['phone']) ? preg_replace('/\D/', '', $d['phone']) : null,
                ]);
                $userId = (int)$pdo->lastInsertId();

                // Auto-login after register
                $token = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
                )->execute([$userId, $token]);

                json_ok(['token' => $token, 'name' => trim($d['name']), 'email' => $email], 201);
            })(),

            'login' => (function () use ($pdo) {
                $d = body();
                if (empty($d['email']) || empty($d['password'])) json_err('E-mail e senha são obrigatórios');
                $email = strtolower(trim($d['email']));
                $s = $pdo->prepare('SELECT * FROM customer_users WHERE email = ?');
                $s->execute([$email]);
                $user = $s->fetch();
                if (!$user || !password_verify($d['password'], $user['password_hash'])) {
                    json_err('E-mail ou senha incorretos', 401);
                }
                $token = bin2hex(random_bytes(32));
                $pdo->prepare(
                    'INSERT INTO customer_sessions (customer_id, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
                )->execute([$user['id'], $token]);
                json_ok(['token' => $token, 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]);
            })(),

            'logout' => (function () use ($pdo) {
                $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
                $token = preg_match('/^Bearer\s+(\S+)$/i', $h, $m) ? $m[1] : '';
                if ($token) $pdo->prepare('DELETE FROM customer_sessions WHERE token = ?')->execute([$token]);
                json_ok(['ok' => true]);
            })(),

            'address' => (function () use ($pdo) {
                $user = customerAuth($pdo);
                $d = body();
                // Upsert default address
                $pdo->prepare('DELETE FROM customer_addresses WHERE customer_id = ?')->execute([$user['id']]);
                $pdo->prepare(
                    'INSERT INTO customer_addresses (customer_id, cep, street, number, complement, neighborhood, reference) VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $user['id'],
                    $d['cep'] ?? null,
                    $d['street'] ?? null,
                    $d['number'] ?? null,
                    $d['complement'] ?? null,
                    $d['neighborhood'] ?? null,
                    $d['reference'] ?? null,
                ]);
                json_ok(['saved' => true]);
            })(),

            default => json_err('Not found', 404),
        };
    })(),

    // ── GET ───────────────────────────────────────────────────────────────────
    'GET' => (function () use ($pdo, $sub) {
        match ($sub) {

            'me' => (function () use ($pdo) {
                $user = customerAuth($pdo);
                json_ok(['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'phone' => $user['phone']]);
            })(),

            'orders' => (function () use ($pdo) {
                $user = customerAuth($pdo);
                $s = $pdo->prepare(
                    'SELECT id, order_number, status, total, payment_method, delivery_type, created_at
                     FROM orders WHERE customer_user_id = ? ORDER BY created_at DESC LIMIT 50'
                );
                $s->execute([$user['id']]);
                json_ok(['orders' => $s->fetchAll()]);
            })(),

            'address' => (function () use ($pdo) {
                $user = customerAuth($pdo);
                $s = $pdo->prepare('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY id DESC LIMIT 1');
                $s->execute([$user['id']]);
                $addr = $s->fetch();
                json_ok($addr ?: (object)[]);
            })(),

            default => json_err('Not found', 404),
        };
    })(),

    // ── PUT (atualizar perfil) ────────────────────────────────────────────────
    'PUT' => (function () use ($pdo, $sub) {
        if ($sub === 'me') {
            $user = customerAuth($pdo);
            $d = body();
            $fields = []; $vals = [];
            if (!empty($d['name']))  { $fields[] = 'name = ?';  $vals[] = trim($d['name']); }
            if (!empty($d['phone'])) { $fields[] = 'phone = ?'; $vals[] = preg_replace('/\D/', '', $d['phone']); }
            if (!empty($d['password'])) {
                if (strlen($d['password']) < 6) json_err('Senha deve ter pelo menos 6 caracteres');
                $fields[] = 'password_hash = ?';
                $vals[]   = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 10]);
            }
            if ($fields) {
                $vals[] = $user['id'];
                $pdo->prepare('UPDATE customer_users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($vals);
            }
            json_ok(['updated' => true]);
        }
        json_err('Not found', 404);
    })(),

    default => json_err('Method not allowed', 405),
};
