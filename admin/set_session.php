<?php
session_name('plantao_admin');
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['token'] ?? '');
$parts = explode('.', $token);

if (count($parts) === 3 && strlen($token) > 60) {
    $_SESSION['logged'] = true;
    echo '{"ok":true}';
} else {
    http_response_code(400);
    echo '{"error":"invalid"}';
}
