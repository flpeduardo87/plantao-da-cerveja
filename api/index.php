<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// ── CORS ────────────────────────────────────────────────────────────────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = [FRONTEND_URL, ADMIN_URL];
if (in_array($origin, $allowed, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── Router ───────────────────────────────────────────────────────────────────
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Strip leading path segment if API lives in a subdirectory
// e.g. /api/products → products
$parts  = explode('/', $uri);
$route  = $parts[0] ?? '';
$id     = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : null;
$sub    = $parts[1] ?? null; // non-numeric sub-path (e.g. /orders/123/status)

function json_ok(mixed $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_err(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return $raw ? (json_decode($raw, true) ?? []) : [];
}

// ── Dispatch ─────────────────────────────────────────────────────────────────
match ($route) {
    'auth'       => require __DIR__ . '/routes/auth.php',
    'categories' => require __DIR__ . '/routes/categories.php',
    'products'   => require __DIR__ . '/routes/products.php',
    'orders'     => require __DIR__ . '/routes/orders.php',
    'settings'   => require __DIR__ . '/routes/settings.php',
    'upload'     => require __DIR__ . '/routes/upload.php',
    default      => json_err('Route not found', 404),
};
