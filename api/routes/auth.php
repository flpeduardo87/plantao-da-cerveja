<?php
// POST /auth/login          — authenticate admin user
// POST /auth/logout         — (stateless: client clears cookie)
// POST /auth/change-password — change own password

$pdo = db();

match ($method) {
    'POST' => (function () use ($pdo, $sub) {
        match ($sub) {
            'login' => (function () use ($pdo) {
                $d = body();
                if (empty($d['email']) || empty($d['password'])) json_err('E-mail e senha são obrigatórios');

                $s = $pdo->prepare('SELECT id, name, email, password_hash FROM admin_users WHERE email = ? AND active = 1');
                $s->execute([strtolower(trim($d['email']))]);
                $user = $s->fetch();

                if (!$user || !password_verify($d['password'], $user['password_hash'])) {
                    json_err('E-mail ou senha incorretos', 401);
                }

                $pdo->prepare('UPDATE admin_users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

                json_ok([
                    'id'    => $user['id'],
                    'name'  => $user['name'],
                    'email' => $user['email'],
                ]);
            })(),

            'logout' => (function () {
                // Client-side logout: JS clears the cookie.
                // This endpoint exists so app.js can call it without error.
                json_ok(['ok' => true]);
            })(),

            'change-password' => (function () use ($pdo) {
                $d = body();
                if (empty($d['email']) || empty($d['current_password']) || empty($d['new_password'])) {
                    json_err('E-mail, senha atual e nova senha são obrigatórios');
                }
                if (strlen($d['new_password']) < 8) json_err('A senha deve ter pelo menos 8 caracteres');

                $s = $pdo->prepare('SELECT id, password_hash FROM admin_users WHERE email = ? AND active = 1');
                $s->execute([strtolower(trim($d['email']))]);
                $user = $s->fetch();

                if (!$user || !password_verify($d['current_password'], $user['password_hash'])) {
                    json_err('Senha atual incorreta', 401);
                }

                $hash = password_hash($d['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
                json_ok(['ok' => true]);
            })(),

            default => json_err('Not found', 404),
        };
    })(),

    default => json_err('Method not allowed', 405),
};
