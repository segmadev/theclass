<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
// If it's a preflight request, respond with 200 and exit
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
header("Content-Type: application/json; charset=UTF-8");
// Remove the "/cruise/" prefix from the URI
$iniPath = str_replace("/cruise/", "", $_SERVER['REQUEST_URI']);
// Parse the URL to extract only the path
$parsedUrl = parse_url($iniPath);
$cleanPath = rtrim($parsedUrl['path'], '/'); // Remove trailing slash if it exists
define("ISAPI", true);
// Define PATH without GET parameters
$thePath = str_replace("app/api", "", $cleanPath);
define("PATH", str_replace("//", "/", $thePath));
// die(var_dump(PATH));
// define("PATH", $cleanPath);
// Parse the query string into $_GET if present
if (isset($parsedUrl['query'])) {
    parse_str($parsedUrl['query'], $_GET);
}
require_once "../consts/main.php";
require_once "../admin/include/database.php";
$d = new database;
$token = $d->getBearerToken();
// load env data
require_once '../vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(str_replace("api/app/", "", str_replace("api/cruise/app", "", rootFile)));
$dotenv->load();