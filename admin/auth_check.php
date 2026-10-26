<?php
ini_set('session.cookie_path', '/admin/');
ini_set('session.cookie_httponly', '1');
@session_start();

// Primary: PHP session
if (!empty($_SESSION['plantao_logged'])) return;

// Fallback: httpOnly cookie set at login
$tok = $_COOKIE['plantao_tok'] ?? '';
$parts = explode('.', $tok);
if (count($parts) === 3 && strlen($tok) > 60) return;

header('Location: index.php');
exit;
