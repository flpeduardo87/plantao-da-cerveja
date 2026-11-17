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

// Pagar.me v5 — adicione as chaves do painel pagar.me
define('PAGARME_SECRET_KEY',     ''); // sk_... (chave secreta)
define('PAGARME_PUBLIC_KEY',     ''); // pk_... (chave pública)
define('PAGARME_WEBHOOK_SECRET', ''); // segredo do webhook (em pagar.me > webhooks)
