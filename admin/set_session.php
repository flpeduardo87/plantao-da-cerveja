<?php
ini_set('session.cookie_path', '/admin/');
ini_set('session.cookie_httponly', '1');
@session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$parts = explode('.', $token);

if (count($parts) === 3 && strlen($token) > 60) {
    $_SESSION['plantao_logged'] = true;
    // Fallback cookie in case session doesn't persist
    setcookie('plantao_auth', hash('sha256', $token), [
        'expires'  => time() + 86400,
        'path'     => '/admin/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    setcookie('plantao_tok', $token, [
        'expires'  => time() + 86400,
        'path'     => '/admin/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    echo '{"ok":true}';
} else {
    http_response_code(400);
    echo '{"error":"invalid"}';
}
