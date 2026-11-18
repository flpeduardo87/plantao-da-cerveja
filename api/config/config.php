<?php
define('API_VERSION', '1.0');
define('FRONTEND_URL', 'https://plantaodacerveja.com.br');
define('ADMIN_URL',    'https://admin.plantaodacerveja.com.br');
define('API_URL',      'https://api.plantaodacerveja.com.br');
define('UPLOADS_PATH', '/home/u360341722/domains/plantaodacerveja.com.br/public_html/uploads/products/');
define('UPLOADS_URL',  'https://plantaodacerveja.com.br/uploads/products/');

// Session
define('SESSION_NAME',     'plantao_admin');
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// Chaves secretas — carregadas de local.php (jamais commitado no Git)
// No servidor: crie api/config/local.php com suas chaves reais (veja config.example.php)
if (file_exists(__DIR__ . '/local.php')) {
    include_once __DIR__ . '/local.php';
}

// Fallbacks (usados quando local.php não existe, ex: ambiente de dev sem chaves)
if (!defined('PAGARME_SECRET_KEY'))     define('PAGARME_SECRET_KEY',     '');
if (!defined('PAGARME_PUBLIC_KEY'))     define('PAGARME_PUBLIC_KEY',     '');
if (!defined('PAGARME_WEBHOOK_SECRET')) define('PAGARME_WEBHOOK_SECRET', '');
