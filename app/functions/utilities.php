<?php

class utilities
{

    public $logTK;
    // public $limit = 20;

    static function get_limit() {
        return 20;
    }
    function ini()
    {
        utilities::theme();
        utilities::loadSession();
    }
    static function theme()
    {
        if (isset($_GET['theme'])) {
            $expiration = time() + (30 * 24 * 60 * 60); // 30 days * 24 hours * 60 minutes * 60 seconds
            if ($_GET['theme'] == "dark") {
                // Set the cookie
                setcookie("browser_theme", "dark");
            } else {
                setcookie("browser_theme", "light");
            }
            // Get the previous page link
            $previousPage = $_SERVER['HTTP_REFERER'];
            // Reload the current page
            die(utilities::loadpage($previousPage));
        }
    }

    static function force_https()
    {
        if (isset($_ENV['HTTPS_ONLY']) && $_ENV['HTTPS_ONLY'] && !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                echo '<script>window.location.href = "'.$redirect.'";</script>';
                exit();
        }
    }
    static function base_path()
    {
        return ROOT;
        // Get document root and remove trailing slash
        $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');

        // Get the URL path of the executing script, e.g. "/myproject/index.php"
        $scriptPath = $_SERVER['SCRIPT_NAME'];

        // Break the path into segments (ignoring leading/trailing slashes)
        $parts = explode('/', trim($scriptPath, '/'));

        // If there is more than one part, assume the first part is the project folder
        if (count($parts) > 1) {
            $projectFolder = $parts[0];
            return $docRoot . '/' . $projectFolder;
        }

        // Otherwise, the project is hosted directly at the document root
        return $docRoot;
    }
    static function genID($prefix = '', $length = 8)
    {
        $random = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
        return $prefix . time() . $random;
    }
    function loadSession()
    {
        $redirect = utilities::base_url();

        if (!isset($_SESSION['logTk'])) {
            $_SESSION['urlgoto'] = $redirect;
            die(utilities::loadpage("login"));
        }

        if (isset($_GET['logout'])) {
            session_destroy();
            unset($_SESSION['logTk']);
            die(utilities::loadpage($redirect . "/login"));
        }

        if (isset($_SESSION['logTk'])) {
            $this->logTK = $_SESSION['logTk'];
        } else {
            session_destroy();
            die(utilities::loadpage($redirect . "/login"));
        }
    }

    static function humanFileSize($size, $unit = "")
    {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB")
            return number_format($size / (1 << 30), 2) . "GB";
        if ((!$unit && $size >= 1 << 20) || $unit == "MB")
            return number_format($size / (1 << 20), 2) . "MB";
        if ((!$unit && $size >= 1 << 10) || $unit == "KB")
            return number_format($size / (1 << 10), 2) . "KB";
        return number_format($size) . " bytes";
    }

    static function randcar($no = 20)
    {
        $seed = str_split('abcdefghijklmnopqrstuvwxyz'
            . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            . '0123456789'); // and any other characters
        shuffle($seed); // probably optional since array_is randomized; this may be redundant
        $rand = '';
        if((int)$no < 1) $no = 20; 
        foreach (array_rand($seed, $no) as $k) $rand .= $seed[$k];
        return "3" . $rand;
    }

    static function pass_value($value, $row)
    {
        if (is_array($value)) {
            $no = 0;
            $count = count($value);
            $data = "";
            foreach ($value as $key => $val) {
                $no++;
                if ($no == $count) {
                    $data .= $row[$val];
                } else {
                    $data .= $row[$val] . " - ";
                }
            }
        } else {
            $data = $row[$value];
        }
        return $data;
    }
    static function isPasswordHash($string)
    {
        // Check if the length is consistent with bcrypt hashes
        if (strlen($string) === 60 && substr($string, 0, 4) === '$2y$') {
            return true;
        }

        // Check if it is an Argon2 hash
        if (strpos($string, '$argon2i$') === 0 || strpos($string, '$argon2id$') === 0) {
            return true;
        }

        return false;
    }

    static function get_path()
    {
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']
            === 'on' ? "https" : "http") .
            "://" . $_SERVER['HTTP_HOST'] .
            $_SERVER['REQUEST_URI'];
        $path = parse_url($url)['path'] ?? "/index";
        // Remove the leading slash and split the path into an array
        $pathParts = explode('/', trim($path, '/'));

        // Check if the first string is 'eksuth'
        if ($pathParts[0] === 'eksuth') {
            // If there is a next part, return it; otherwise, return 'index'
            $result = isset($pathParts[1]) ? $pathParts[1] : 'index';
        } else {
            // If 'eksuth' is not the first string, use the first part as the result
            $result = $pathParts[0];
        }
        return preg_replace('/[^a-zA-Z0-9_]/', '', $result);
    }

    static function extractKeywords($string, $limit = 10)
    {
        // Convert string to lowercase
        $string = strtolower($string);

        // Define a list of common stopwords
        $stopwords = [
            'the',
            'is',
            'and',
            'or',
            'a',
            'an',
            'of',
            'to',
            'in',
            'on',
            'for',
            'with',
            'by',
            'at',
            'from',
            'this',
            'that',
            'it',
            'as',
            'are',
            'was',
            'were',
            'be',
            'not'
        ];

        // Remove punctuation
        $string = preg_replace('/[^\w\s]/', '', $string);

        // Split the string into words
        $words = explode(' ', $string);

        // Filter out stopwords and short words (less than 3 characters)
        $filteredWords = array_filter($words, function ($word) use ($stopwords) {
            return !in_array($word, $stopwords) && strlen($word) > 2;
        });

        // Count word frequency
        $wordFrequency = array_count_values($filteredWords);

        // Sort words by frequency in descending order
        arsort($wordFrequency);

        // Get the top keywords based on the limit
        $topKeywords = array_slice(array_keys($wordFrequency), 0, $limit);

        return implode(" ,", $topKeywords);
    }

    static function base_url()
    {
        if (isset($_ENV['path']) && $_ENV['path'] != "") {
            return $_ENV['path'];
        }
        // Get the full current URL
        $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

        // Parse the URL into components
        $parsedUrl = parse_url($currentUrl);
        // Split the path into segments
        $pathSegments = explode('/', trim($parsedUrl['path'], '/'));

        // Build the base URL with the first two segments
        $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        // Output the result
        return $baseUrl;
    }

    static function get_visitor_details()
    {
        // ip, browser, theme, country, postal_code, state, city
        $ip = "37.120.215.171";
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_COOKIE['visitor_details'])) {
            $data = unserialize($_COOKIE['visitor_details']);
            if ($data['ip_address'] == $ip) {
                return $data;
            }
        }
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
        $data = ["ip_address" => $ip, "device" => $deviceInfo, "browser" => "", "theme" => "light", "country" => "", "postal_code" => "", "state" => "", "city" => ""];
        $apiUrl = "http://ip-api.com/json/{$ip}";
        $locationData = json_decode(file_get_contents($apiUrl));
        // var_dump($locationData);
        if ($locationData && $locationData->status === 'success') {
            // var_dump($locationData);
            $data['country'] = $locationData->country;
            $data['state'] = $locationData->regionName;
            $data['city'] = $locationData->city;
        }
        if (isset($_COOKIE['browser_theme'])) {
            $data['theme'] = htmlspecialchars($_COOKIE['browser_theme']);
        }
        setcookie("visitor_details", serialize($data), time() + 12 * 60 * 60);
        //    var_dump($data);
        return $data;
    }



    static function money_format($amount, $currency = 'N')
    {
        $tamount = number_format((float)$amount, 2,);
        $parts = explode(".", $tamount);
        if ($parts[1] == "00") {
            $tamount = number_format((float)$amount);
        }
        return $currency . ' ' . $tamount;
    }

    static function date_format($date)
    {
        $date = date_create($date);
        return date_format($date, "D, d M Y h:i:sa");
    }

    static function formatDate($date)
    {
        // Sanitize the input to prevent XSS
        $date = htmlspecialchars($date);

        // Check if the date is provided
        if ($date) {
            // Create a DateTime object from the date
            try {
                $date = new DateTime($date);
                // Format the date to 'YYYY-MM-DD HH:MM:SS'
                return $date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Return an error message if the date is invalid
                $date;
            }
        } else {
            return $date;
        }
    }

    static function datediffe($largeDate, $smallDate, $format = "h")
    {
        $date1 = $largeDate;
        $date2 = $smallDate;

        // Create DateTime objects from the date strings
        $datetime1 = new DateTime($date1);
        $datetime2 = new DateTime($date2);

        // Calculate the difference
        $interval = $datetime1->diff($datetime2);

        // Convert the difference to total hours
        if ($format == "m") return ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
        if ($format == "s") return ($interval->days * 24 * 3600) + ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
        return ($interval->days * 24) + $interval->h + ($interval->i / 60) + ($interval->s / 3600);
    }
    static function cal_percentage(int | float $no, int | float $total)
    {
        return round(($no * 100) / $total);
    }

    static function money_percentage($percentage, $amount)
    {
        return round($amount * ($percentage / 100));
    }

    static function calculateProfitPercentage($buyingPrice, $sellingPrice)
    {
        $profitPercentage = (($sellingPrice - $buyingPrice) / $buyingPrice) * 100;
        return $profitPercentage;
    }

    static function calculateIncreasedValue($originalValue, $percentageIncrease)
    {
        $percentageIncrease = $percentageIncrease / 100;
        $hold = $originalValue * $percentageIncrease;
        $increasedValue = $originalValue + $hold;
        return $hold;
    }

    static function loadpage($url, $isJson = false, $message = "Redirecting...", $delay = 0)
    {
        if (!$isJson) {
            if ($delay > 0) {
                echo '<script>
                        setTimeout(function() {
                            window.location.href = "' . $url . '";
                        }, ' . $delay . ');
                    </script>
                    <p><b>' . $message . '</b></p>
                    ';
            } else {
                echo '<script>window.location.href = "' . $url . '";</script>
                     <p><b>' . $message . '</b></p>
                    ';
            }
            return;
        }
        return json_encode([
            "message" => ["Success", "$message", "success"],
            "function" => [
                "loadpage",
                "data" => [$url, $delay],
            ],
        ]);
    }




    static function ago($time)
    {
        if ($time == "") {
            return "";
        }
        // $time = strtotime($time);
        $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
        $lengths = array("60", "60", "24", "7", "4.35", "12", "10");
        $now = time();

        $difference     = $now - $time;
        $tense         = "ago";

        for ($j = 0; $difference >= $lengths[$j] && $j < count($lengths) - 1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);
        if ($periods[$j] == "second") {
            return "Just now";
        }

        if ($difference != 1) {
            $periods[$j] .= "s";
        }



        $value =  "$difference $periods[$j] ago";
        if ($value == "1 second ago") {
            return "Just now";
        } else {
            return $value;
        }
    }


    static function short_text($text, $maxCharacters = 30, $justText = false)
    {
        $short = $text;
        $id = "";
        $data = "";
        $function = "";
        if (strlen($text) > $maxCharacters) {
            $short = substr($text, 0, $maxCharacters) . "...";
            $isLong = true;
            $id = uniqid();
            $function = "<small><a href=\"javascript:void(0)\" onclick=\"showall('$id')\" class='text-gray'>more</a></small>";
            $data = "data-fulltext='$text'";
        }
        $shortenedText = "<p title='$text' class='m-0' id='$id' $data >$short $function</p>";
        return !$justText  ?  $shortenedText : $short;
    }

    static function short_no($no, $maxno = 99)
    {
        if ($no == 0) {
            $no = "";
        }
        if ($no > $maxno) {
            $no = "$maxno+";
        }
        return $no;
    }

    static function generateRandomDateTime($startDate = '2022-01-01 09:00:00', $endDate = null)
    {
        if ($endDate == null) {
            $endDate =  date('Y-m-d H:i:s');
        }
        // '2022-01-01 09:00:00', date('Y-m-d H:i:s')
        $startTimestamp = strtotime($startDate);
        $endTimestamp = strtotime($endDate);
        $randomTimestamp = mt_rand($startTimestamp, $endTimestamp);
        $randomDateTime = date('Y-m-d H:i:s', $randomTimestamp);

        return $randomDateTime;
    }


    static function addMinutes($datetimeStr, $minutes)
    {
        // Create DateTime object from the input string
        $originalDatetime = new DateTime($datetimeStr);

        // Add the specified number of minutes
        $newDatetime = $originalDatetime->modify("+$minutes minutes");

        // Format the new datetime as desired
        $newDatetimeStr = $newDatetime->format('Y-m-d H:i:s');

        return $newDatetimeStr;
    }


    static function removeRepeatedValuesFromBack($arr)
    {
        $result = [];
        $lastValue = null;

        // Iterate through the array in reverse
        for ($i = count($arr) - 1; $i >= 0; $i--) {
            // If the current value is not equal to the last value, add it to the result
            if ($arr[$i] !== $lastValue) {
                array_unshift($result, $arr[$i]); // Add to the front of the result array
                $lastValue = $arr[$i]; // Update the last value
            }
        }

        return $result;
    }

    // Function to set a cookie, can be used for both simple values and arrays
    static function setCookieValue($name, $value, $expire = 86400, $path = "/", $domain = "", $secure = false, $httponly = false)
    {
        if (is_array($value)) {
            // Encode array to JSON if the value is an array
            $value = json_encode($value);
        }
        try {
            //code...
            setcookie($name, $value, time() + $expire, $path, $domain, $secure, $httponly);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }



    // Function to retrieve a cookie value, ensures that arrays are returned as arrays
    static function getCookieValue($name)
    {
        if (isset($_COOKIE[$name])) {
            $value = $_COOKIE[$name];
            // Attempt to decode JSON
            try {
                $decodedValue = json_decode($value, true);
                return $decodedValue;
            } catch (\Throwable $th) {
                return $value;
            }
        }

        return null; // Return null if the cookie does not exist
    }

    // Function to update an array stored in a cookie
    function updateArrayInCookie($cookieName, $newElement, $key = null, $expire = 86400, $path = "/", $domain = "", $secure = false, $httponly = false)
    {
        // Retrieve the current array from the cookie
        $currentArray = $this->getCookieValue($cookieName);

        if (!is_array($currentArray)) {
            // If the cookie does not exist or is not an array, start with an empty array
            $currentArray = [];
        }

        if ($key !== null && array_key_exists($key, $currentArray)) {
            // If a key is provided and exists, update that element
            $currentArray[$key] = $newElement;
        } else {
            // Otherwise, add the new element to the array
            $currentArray[] = $newElement;
        }

        // Store the updated array back in the cookie
        $this->setCookieValue($cookieName, $currentArray, $expire, $path, $domain, $secure, $httponly);
    }

    // Function to delete a cookie
    static function deleteCookie($name, $path = "/", $domain = "")
    {
        setcookie($name, "", time() - 3600, $path, $domain);
    }



    // api call
    function isJson($string)
    {
        // Decode the string and store the result
        try {
            json_decode($string);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }

    function api_call($service_url, $posts = [], $header = [], $isRaw = false, $method = 'GET')
    {
        // Validate and normalize HTTP method
        $method = strtoupper($method);
        $validMethods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'];
        if (!in_array($method, $validMethods)) {
            throw new InvalidArgumentException("Invalid HTTP method: $method");
        }

        // Initialize cURL
        $curl = curl_init($service_url);

        // Set default cURL options
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        // Determine HTTP method based on $posts
        if ($method === 'GET' && (!empty($posts) || $this->isJson($posts))) {
            $method = 'POST'; // Adjust if $posts provided with default GET
        }

        // Handle headers
        $defaultHeaders = ['Content-Type: application/json'];
        $header = array_merge($defaultHeaders, $header);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Set HTTP method
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
        } else {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        }

        // Add POST or PUT/PATCH fields
        if (in_array($method, ['POST', 'PATCH', 'PUT']) && !empty($posts)) {
            $payload = is_array($posts) ? json_encode($posts) : $posts; // JSON encode if array
            curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        }

        // Execute request and handle response
        $curl_response = curl_exec($curl);

        // Handle errors
        if ($curl_response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException("CURL error: $error");
        }

        curl_close($curl);

        // Return raw or JSON-decoded response
        return $isRaw ? $curl_response : json_decode($curl_response, true);
    }

    // api functions

    static function apiMessage($message, int $code = 400, $data = null, $errors = null)
    {
        // Send the HTTP response code
        http_response_code($code);
        //   ($code != 200 )  "errorCode"=>$errorCode ?? str_replace(" ", "", $message),
        // Send the response as JSON
        return json_encode([
            "code" => $code,
            "status" => ($code == 200 || $code == 201) ? "success" : "error",
            "message" => $message,
            "data" => $data,
            "errors" => $errors
        ]);
    }


    /** 
     * Get header Authorization
     * */
    static function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * get access token from header
     * */
    function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }
}