<?php
    class Router extends database
    {
        private array $routes = [];

        /**
         * Register a new route or multiple routes.
         *
         * @param string|array $paths
         * @param string $handler
         * @param array|string $methods
         * @param bool $authRequired
         * @param array|null $rateLimit  // New: per-route rate limit config
         */
    
        public function register($paths, string $handler, $methods = 'GET', bool $authRequired = false, ?array $rateLimit = null, bool $isencrypte = false): void
        {
            // Normalize methods to an array
            $methods = is_array($methods) ? $methods : [$methods];

            // Normalize paths to an array
            $paths = is_array($paths) ? $paths : [$paths];

            foreach ($paths as $path) {
                $this->routes[$path] = [
                    'handler' => $handler,
                    'methods' => $methods,
                    'authRequired' => $authRequired,
                    'rateLimit' => $rateLimit, // Store per-route rate limit config
                    'isencrypte' => $isencrypte // Store encryption flag
                ];
            }
        }

        /**
         * Dispatch the request to the appropriate controller and action.
         *
         * @param string|null $path
         */
        public function dispatch(string $path = null): void
        {
            // --- Security Features ---
            $RATE_LIMIT = 400; // max requests
            $RATE_LIMIT_WINDOW = 600; // seconds (10 min)
            $RATE_LIMIT_FILE = sys_get_temp_dir() . '/api_rate_limit.json';
            $BLACKLISTED_IPS = [
                // '192.168.1.1', // Example
            ];
            $WHITELISTED_IPS = [
                // If you want to allow only certain IPs, add here
            ];
            $LOG_FILE = __DIR__ . '/../suspicious_activity.log';
            $BLOCKLIST_FILE = __DIR__ . '/../suspicious_blocklist.json';
            $SUSPICIOUS_THRESHOLD = 7; // events
            $SUSPICIOUS_WINDOW = 600; // 10 min
            $BLOCK_DURATION = 1800; // 30 min

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $requestPath = parse_url($requestUri, PHP_URL_PATH);

            // Logging function
            $log_suspicious = function($msg) use ($LOG_FILE, $ip) {
                $entry = date('Y-m-d H:i:s') . " | $ip | $msg\n";
                file_put_contents($LOG_FILE, $entry, FILE_APPEND);
            };
            $log_block_event = function($msg) use ($LOG_FILE, $ip) {
                $entry = date('Y-m-d H:i:s') . " | $ip | [BLOCK] $msg\n";
                file_put_contents($LOG_FILE, $entry, FILE_APPEND);
            };

            // --- Automatic Temporary Blocking ---
            // Ensure blocklist file exists
            if (!file_exists($BLOCKLIST_FILE)) {
                file_put_contents($BLOCKLIST_FILE, '{}');
            }
            $blockData = json_decode(file_get_contents($BLOCKLIST_FILE), true) ?: [];
            $now = time();
            $blocked = false;
            // Clean up expired blocks and check if IP is blocked
            foreach ($blockData as $blockedIp => $info) {
                if (isset($info['blocked_until']) && $now > $info['blocked_until']) {
                    unset($blockData[$blockedIp]);
                    $log_block_event("Unblocked (expired): $blockedIp");
                }
            }
            if (isset($blockData[$ip]) && isset($blockData[$ip]['blocked_until']) && $now <= $blockData[$ip]['blocked_until']) {
                $blocked = true;
            }
            if ($blocked) {
                $log_block_event("Request denied (still blocked)");
                http_response_code(429); exit(json_encode(["error" => "Too many suspicious activities. Try again later."]));
            }
            // Save any cleanup
            file_put_contents($BLOCKLIST_FILE, json_encode($blockData));
            // --- End Automatic Temporary Blocking ---

            // Input sanitization
            $sanitize_input = function (&$array) use (&$sanitize_input) {
                foreach ($array as $k => $v) {
                    if (is_array($v)) $sanitize_input($array[$k]);
                    else $array[$k] = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
                }
            };
            $sanitize_input($_GET);
            $sanitize_input($_POST);
            $sanitize_input($_REQUEST);

            // IP Blacklist/Whitelist
            if (in_array($ip, $BLACKLISTED_IPS)) {
                $log_suspicious("Blocked blacklisted IP");
                $this->incrementSuspicious($ip, $BLOCKLIST_FILE, $SUSPICIOUS_THRESHOLD, $SUSPICIOUS_WINDOW, $BLOCK_DURATION, $log_block_event);
                http_response_code(403); exit(json_encode(["error" => "Forbidden"]));
            }
            if (!empty($WHITELISTED_IPS) && !in_array($ip, $WHITELISTED_IPS)) {
                $log_suspicious("Blocked non-whitelisted IP");
                $this->incrementSuspicious($ip, $BLOCKLIST_FILE, $SUSPICIOUS_THRESHOLD, $SUSPICIOUS_WINDOW, $BLOCK_DURATION, $log_block_event);
                http_response_code(403); exit(json_encode(["error" => "Forbidden"]));
            }

            // Ensure RATE_LIMIT_FILE exists
            if (!file_exists($RATE_LIMIT_FILE)) {
                file_put_contents($RATE_LIMIT_FILE, '{}');
            }

            // --- Route Matching ---
            $path = $path ?? PATH ?? "/";
            $matchedRoute = $this->matchRoute($path);

            if (!$matchedRoute) {
                $log_suspicious("Not Found route: $path");
                $this->incrementSuspicious($ip, $BLOCKLIST_FILE, $SUSPICIOUS_THRESHOLD, $SUSPICIOUS_WINDOW, $BLOCK_DURATION, $log_block_event);
                $this->apiMessage("Not Found", 404);
                exit;
            }

            [$route, $parameters] = $matchedRoute;
            $handler = $route['handler'];
            $methods = $route['methods'];
            $authRequired = $route['authRequired'];
            $routeRateLimit = $route['rateLimit'] ?? null;

            // --- Per-route or global rate limiting ---
            $rateData = [];
            if (file_exists($RATE_LIMIT_FILE)) {
                $rateData = json_decode(file_get_contents($RATE_LIMIT_FILE), true) ?: [];
            }
            $now = time();
            $routeKey = $requestPath;
            if ($routeRateLimit && isset($routeRateLimit['group_size']) && isset($routeRateLimit['window'])) {
                $group_size = $routeRateLimit['group_size'];
                $window = $routeRateLimit['window'];
                if (!isset($rateData[$ip])) {
                    $rateData[$ip] = [];
                }
                if (!isset($rateData[$ip][$routeKey])) {
                    $rateData[$ip][$routeKey] = ["count" => 1, "start" => $now, "group" => 1];
                } else {
                    $entry = &$rateData[$ip][$routeKey];
                   $isencrypte = $route['isencrypte'] ?? false;
                    if ($now - $entry["start"] > $window) {
                        $entry = ["count" => 1, "start" => $now, "group" => 1];
                    } else {
                        $entry["count"]++;
                        if ($entry["count"] % $group_size == 1 && $entry["count"] > 1) {
                            $entry["group"]++;
                        }
                        if ($entry["group"] > 1) { // Only allow 1 group per window
                            $log_suspicious("Custom rate limit exceeded for $routeKey");
            if ($isencrypte && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $encrypted = $_POST['data'] ?? '';
                if (!$encrypted) {
                    $this->apiMessage('Missing encrypted data', 400);
                    exit;
                }
                $key = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY');
                if (!$key || strlen($key) !== 64) {
                    $this->apiMessage('Encryption key misconfigured', 500);
                    exit;
                }
                $key = hex2bin($key);
                $decrypted = $this->decrypt_payload($encrypted, $key);
                if (!is_array($decrypted)) {
                    $this->apiMessage('Failed to decrypt payload', 400);
                    exit;
                }
                // Overwrite $_POST with decrypted data
                $_POST = $decrypted;
            }
                            $this->incrementSuspicious($ip, $BLOCKLIST_FILE, $SUSPICIOUS_THRESHOLD, $SUSPICIOUS_WINDOW, $BLOCK_DURATION, $log_block_event);
                            http_response_code(429); exit(json_encode(["error" => "Too many requests"]));
                        }

                        
            // --- Encrypted Payload Handling ---
            if ($isencrypte && $_SERVER['REQUEST_METHOD'] === 'POST') {
                $encrypted = $_POST['data'] ?? '';
                if (!$encrypted) {
                    $this->apiMessage('Missing encrypted data', 400);
                    exit;
                }
                $key = $_ENV['ENCRYPTION_KEY'] ?? null;
                if (!$key || strlen($key) !== 32) {
                    $this->apiMessage('Encryption key misconfigured', 500);
                    exit;
                }
                $decrypted = $this->decrypt_payload($encrypted, $key);
                if (!is_array($decrypted)) {
                    $this->apiMessage('Failed to decrypt payload', 400);
                    exit;
                }
                // Overwrite $_POST with decrypted data
                $_POST = $decrypted;
            }
                    }
                }
                file_put_contents($RATE_LIMIT_FILE, json_encode($rateData));
            } else {
                // Global rate limiting (for all other routes)
                if (!isset($rateData[$ip])) {
                    $rateData[$ip] = ["count" => 1, "start" => $now];
                } elseif (is_array($rateData[$ip]) && isset($rateData[$ip]["count"])) {
                    if ($now - $rateData[$ip]["start"] > $RATE_LIMIT_WINDOW) {
                        $rateData[$ip] = ["count" => 1, "start" => $now];
                    } else {
                        $rateData[$ip]["count"]++;
                        if ($rateData[$ip]["count"] > $RATE_LIMIT) {
                            $log_suspicious("Rate limit exceeded");
                            $this->incrementSuspicious($ip, $BLOCKLIST_FILE, $SUSPICIOUS_THRESHOLD, $SUSPICIOUS_WINDOW, $BLOCK_DURATION, $log_block_event);
                            http_response_code(429); exit(json_encode(["error" => "Too many requests"]));
                        }
                    }
                }
                file_put_contents($RATE_LIMIT_FILE, json_encode($rateData));
            }
            // --- End Security Features ---

            // Check if the HTTP method is allowed
            if (!in_array($_SERVER['REQUEST_METHOD'], $methods)) {
                $log_suspicious("Method not allowed: {$_SERVER['REQUEST_METHOD']} for $handler");
                $this->apiMessage("Method not allowed", 405);
                exit;
            }

            // Check if authentication is required
            if ($authRequired) {
                $this->checkAuth();
            }

            [$controllerName, $actionDefinition] = explode("@", $handler);

            // Extract action and additional parameters
            $actionParts = explode('|', $actionDefinition);
            $action = array_shift($actionParts);

            // Load the controller file
            $controllerFile = "controllers/$controllerName.php";
            if (!file_exists($controllerFile)) {
                $log_suspicious("Controller file not found: $controllerFile");
                $this->apiMessage("Controller not found", 404);
                exit;
            }
            require_once $controllerFile;
            // $this->apiMessage("Controller not found", 200, ["user"=>"name"]);
            // die("Got here");
            // Instantiate the controller
            $className = "Api".ucfirst($controllerName);
            if (!class_exists($className)) {
                $log_suspicious("Controller class not found: $className");
                $this->apiMessage("Controller class not found", 500);
                exit;
            }
            $controller = new $className();

            try {
                // Call the action with parameters
                $response = count($parameters) > 0
                    ? $controller->$action(...$parameters)
                    : $controller->$action();

                echo $response;
            } catch (Throwable $e) {
                $log_suspicious("Exception: " . $e->getMessage());
                if(isset($_ENV['debug']) && $_ENV['debug'] == true) die($e);
                $this->apiMessage("Int An error occurred", 500);
            }
        }

        /**
         * Match the given path to a registered route.
         *
         * @param string $path
         * @return array|null
         */
        private function matchRoute(string $path): ?array
        {
            foreach ($this->routes as $route => $details) {
                $routePattern = preg_replace('/:\w+/', '([^/]+)', $route); // Replace :param with regex
                $routePattern = '#^' . $routePattern . '$#'; // Add start and end delimiters

                if (preg_match($routePattern, $path, $matches)) {
                    array_shift($matches); // Remove the full match
                    $this->populateGetParameters($route, $matches);
                    return [$details, $matches];
                }
            }

            return null;
        }

        /**
         * Populate $_GET with dynamic parameters.
         *
         * @param string $route
         * @param array $matches
         */
        private function populateGetParameters(string $route, array $matches): void
        {
            $routeParts = explode('/', $route);
            foreach ($routeParts as $index => $part) {
                if (strpos($part, ':') === 0) { // Check if it's a dynamic parameter
                    $paramName = substr($part, 1); // Remove the colon
                    $_GET[$paramName] = $matches[$index - count($routeParts) + count($matches)];
                }
            }
        }

        /**
         * Resolve additional parameters for the action.
         *
         * @param array $actionParts
         * @return array
         */
        private function resolveParameters(array $actionParts): array
        {
            $parameters = [];
            foreach ($actionParts as $part) {
                if (preg_match("/^'(.*)'$/", $part, $matches)) {
                    $parameters[] = $matches[1]; // Extract string values without quotes
                } elseif (isset($GLOBALS[$part])) {
                    $parameters[] = $GLOBALS[$part]; // Use global variable values if defined
                }
            }
            return $parameters;
        }

        /**
         * Check if the user is authenticated.
         */
        private function checkAuth(): void
        {
            $user = new user();
            $user->set_user(); // This will terminate the request if the user is not authenticated
        }

        /**
         * Increment suspicious event count and block IP if threshold is reached.
         */
        private function incrementSuspicious($ip, $blocklistFile, $threshold, $window, $blockDuration, $log_block_event) {
            $now = time();
            if (!file_exists($blocklistFile)) {
                file_put_contents($blocklistFile, '{}');
            }
            $blockData = json_decode(file_get_contents($blocklistFile), true) ?: [];
            if (!isset($blockData[$ip])) {
                $blockData[$ip] = [
                    'events' => [ $now ],
                    'blocked_until' => null
                ];
            } else {
                // Remove old events
                $blockData[$ip]['events'] = array_filter($blockData[$ip]['events'], function($t) use ($now, $window) {
                    return $t > ($now - $window);
                });
                $blockData[$ip]['events'][] = $now;
            }
            // Block if threshold reached
            if (count($blockData[$ip]['events']) >= $threshold) {
                $blockData[$ip]['blocked_until'] = $now + $blockDuration;
                $blockData[$ip]['events'] = [];
                $log_block_event("Blocked for $blockDuration seconds");
            }
            file_put_contents($blocklistFile, json_encode($blockData));
        }
    }