<?php
// Este arquivo mostra como criar api/config/local.php no servidor.
// Copie para local.php e preencha as chaves reais.
// NUNCA commite local.php no Git — ele já está no .gitignore.

define('PAGARME_SECRET_KEY',     'sk_live_...');  // Chave secreta do painel pagar.me
define('PAGARME_PUBLIC_KEY',     'pk_live_...');  // Chave pública do painel pagar.me
define('PAGARME_WEBHOOK_SECRET', '');             // Segredo do webhook (pode ficar vazio)
