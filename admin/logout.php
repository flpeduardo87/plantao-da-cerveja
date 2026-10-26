<?php
ini_set('session.cookie_path', '/');
session_start();
session_destroy();
header('Location: index.php');
exit;
