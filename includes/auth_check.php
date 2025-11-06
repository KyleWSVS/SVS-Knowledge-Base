<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    session_destroy();
    header('Location: login.php');
    exit;
}
if (!isset($_SESSION['login_time']) || (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}
?>
