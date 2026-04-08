<?php
// error_reporting(0);
// ini_set('display_errors', 0);
// if($_SERVER[‘HTTPS’] != "on") {
// $redirect= "https://".$_SERVER[‘HTTP_HOST’].$_SERVER[‘REQUEST_URI’];
// header("Location:$redirect");
// }
// var_dump($_SESSION);
// die("Welcome to admin session");
$redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
if (!isset($_SESSION['logTk'])) {
    $_SESSION['urlgoto'] = $redirect;
    echo '<script>window.location.href = "login";</script>';
    exit();
}

if (isset($_GET['logout'])) {
    session_destroy();
    unset($_SESSION['logTk']);
    echo '<script>window.location.href = "login";</script>';
    exit();
}

if (isset($_SESSION['logTk']) && isset($_SESSION['adminSession'])) {
    $adminToken = $_SESSION['logTk'];
    $adminSession = $_SESSION['adminSession'];
} else {
    session_destroy();
    echo '<script>window.location.href = "login";</script>';
    exit();
}