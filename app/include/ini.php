<?php 
// ob_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
require_once "include/session.php";
if(isset($_GET['theme'])) {
    if($_GET['theme'] == "dark") {
        // Set the cookie
        $_SESSION['browser_theme'] = "dark";
        // setcookie("browser_theme", "dark", $expiration, "/", "");
        
    } else{
        // exit();
        $_SESSION['browser_theme'] = "light";
        // setcookie("browser_theme", "light", $expiration, "/", "");
        // setcookie("browser_theme", "light", $expiration, "/");    
    }
    // Get the previous page link
$previousPage = $_SERVER['HTTP_REFERER'];
    // Reload the current page
echo '<script>window.location.href = "'.$previousPage.'";</script>';
exit();
}
if (!isset($_SESSION['browser_theme'])) {
    $_SESSION['browser_theme'] = "dark";
}
require_once "include/side.php";
require_once "consts/main.php";
require_once 'vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(rootFile);
$dotenv->load();
require_once "consts/Regex.php";
require_once "admin/include/database.php"; 
$d = new database;
$user = $d->getall("users", "token = ?", [$userToken]);
if(!is_array($user)) {
    $d->message("Unable to identify user. <a href='login'>Login here</a>", "error");
    exit();
}
$userID = $user['ID'];
require_once "consts/general.php";
require_once "content/content.php"; 
require_once "functions/notifications.php"; 
require_once "functions/users.php"; 
$u = new user;
$c = new content;
$n = new Notifications;

$page = "dashboard";
if(isset($_GET['p'])) {
    $page = htmlspecialchars($_GET['p']);
}
$script = [];
$user = $d->getall("users", "ID = ?",  [$userID], fetch:"details");
$full_name = ucwords($user['first_name'].' '.$user['last_name']);
$user_data = $u->user_data($userID);
$activities = $d->getall("activities", "userID = ? order by date DESC LIMIT 1", [$userID], fetch: "moredetails");
$act_title = 'Recent Activity';
$act_des = 'New notifications and activity on your account';


if(isset($_GET['note']) && $_GET['note'] != "") {
    $n->exclude_user(htmlspecialchars($_GET['note']), $userID);
}   
// echo $userID;
// var_dump($u->get_all_emails());
// exit;

$form_trans = [
    "ID"=>["input_type"=>"number"],
    "userID"=>[],
    "forID"=>["is_required"=>false],
    "trans_for"=>["is_required"=>false],
    "action_type"=>[],
    "acct_type"=>[],
    "amount"=>["input_type"=>"number"],
    "current_balance"=>["input_type"=>"number"],
];
// $d->create_table("transactions", $form_trans);
$script[] = "live_chat";
$script[] = "modal";
?>
