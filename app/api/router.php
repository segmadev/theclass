<?php
$routes = [
    "/auth/register" => "authentication@register",
    "/auth/login" => "authentication@signin",
    "/auth/logout" => "user@logout",
    "/user/balance" => "user@getBalance",
    "/user/order" => "user@get_order|'yes'",
    "/user/edit" => "user@editAccount",
    "/user/get" => "user@get_user",
    "/rentals/types" => "rentals@getTypes",
    "/rentals/countries" => "rentals@get_countries",
    "/rentals/services" => "rentals@get_countries|'services'",
    "/rentals/new" => "rentals@newNumberOrder",
    "/rentals/getcode" => "rentals@getCode",
    "/rentals/close" => "rentals@close_rental",
    "/account/categories" => "account@getCategories",
    "/account/platforms" => "account@getPlatfroms",
    "/account/fetch" => "account@fetchAccounts",
    "/account/buy" => "account@buyAccount",
    "/account/fetch/logins" => "account@getLogins",
    "/404" => "auth@page404",
    "/" => "auth@page404",
];
// die(var_dump(PATH));
if (!isset($routes[PATH])) return $d->apiMessage("No Found", 400);
$value = $routes[PATH];
$route = explode("@", $value);
$controller = $route[0];
$action = $route[1];
// $fullAction = explode("|", $action);
$para = []; // Initialize $para to avoid undefined variable issues
$parts = explode('|', $action);
// Extract the first part as $action
$action = array_shift($parts);
// Process remaining parts

foreach ($parts as $part) {
    // Check if the part is a string (enclosed in single quotes)
    if (preg_match("/^'(.*)'$/", $part, $matches)) {
        $para[] = $matches[1]; // Extract the string value without quotes
    } elseif (isset($$part)) { // Check if a variable with this name exists
        $para[] = $$part; // Use variable's value
    }
}
if (file_exists("const/$controller.php")) {
    require_once "const/$controller.php";
}
require_once "controllers/$controller.php";
$classKey = "api" . ucfirst($controller);
$controller = new $classKey();
try {
    //code...
    echo count($para) > 0 ? $controller->$action(...$para) : $controller->$action();
} catch (\Throwable $th) {
    //throw $th;
}