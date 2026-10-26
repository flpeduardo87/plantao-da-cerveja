<?php
setcookie('plantao_auth', '', ['expires' => time() - 3600, 'path' => '/admin/', 'samesite' => 'Strict']);
header('Location: index.php');
exit;
