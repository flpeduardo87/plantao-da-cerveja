<?php
ini_set('session.cookie_path', '/');
ini_set('session.cookie_httponly', '1');
session_start();
if (empty($_SESSION['plantao_logged'])) {
    header('Location: index.php');
    exit;
}
