<?php
session_name('plantao_admin');
session_start();
session_destroy();
header('Location: index.php');
exit;
