<?php 
// Initialize the router
$router = new Router();
// helper routes 
$router->register("/helper/plshelp", "helper@plshelp", "GET");
$router->register("/auth/register", "authentication@register", ["POST", "PUT"]);
$router->register("/auth/login", "authentication@signin", ["POST", "PUT"]);
$router->register("/404", "auth@page404");
$router->register("/", "auth@page404");
// Dispatch the current request
$router->dispatch(PATH);
// die(var_dump(PATH));
