<?php 
// error_reporting(E_ALL);
// ini_set('display_errors', 0);
// Set session cookie parameters
$cookieParams = session_get_cookie_params();
session_set_cookie_params(
  7 * 24 * 60 * 60, // Lifetime in seconds (1 week = 7 days * 24 hours * 60 minutes * 60 seconds)
  $cookieParams["path"],     // Path on the server where the cookie will be available
  $cookieParams["domain"],   // Domain for which the cookie is valid
  true,                      // Set the cookie to be secure (HTTPS only)
  true                       // Set the cookie to be accessible only through HTTP (not JavaScript)
);
session_start(); 
// session_regenerate_id(true);