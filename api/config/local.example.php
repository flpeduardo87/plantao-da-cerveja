<?php
// Copy this file to local.php and fill in your values.
// local.php is NOT committed to git.

define('DB_HOST',    'localhost');
define('DB_NAME',    'u360341722_yourdb');
define('DB_USER',    'u360341722_youruser');
define('DB_PASS',    'your_password_here');
define('DB_CHARSET', 'utf8mb4');

// Stone payment gateway (online card payments)
// Get these from painel.stone.com.br → Chaves de API
define('STONE_API_KEY',  '');   // e.g. 74216561a9854be78a16a98f15a8e9d7
define('STONE_CODE',     '');   // e.g. 102308974  (StoneCode / afiliação)
// Set to 'sandbox' for tests or 'production' for live payments
define('STONE_ENV',      'production');
