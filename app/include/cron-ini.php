<?php 
// Enable error reporting
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
    require_once "consts/main.php";
    require_once "consts/Regex.php";
    require_once "admin/include/database.php";
    $d= new database;
    require_once "functions/notifications.php";
    require_once "functions/users.php";
    require_once "consts/general.php";

    require_once "functions/investment.php";
    
    // $u = new users;
    $i = new investment;