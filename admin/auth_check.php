<?php
// JS cookie set on login (no PHP session dependency)
if (($_COOKIE['plantao_auth'] ?? '') === '1') return;

header('Location: index.php');
exit;
