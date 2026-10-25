<?php
session_name('plantao_admin');
session_start();
if (empty($_SESSION['logged'])) {
    header('Location: index.php');
    exit;
}
