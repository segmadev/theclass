<?php
if (!defined('Regex')) {
    define("Regex", "");
}
// $2y$10$zaHI56uHbjpe0xfZdAVVZO4gruUDPE/NZmIGc3s3iX78e5/vZtTYe
class database
{
    public $db;
    private $index;
    private $marks;
    private $data;
    public $err = "no";
    public $userID;
    private $currentDBIndex; // <--- add this line
    // private constructor
    public function __construct($connectIndex = 0)
    {
        $servername = "";
        if ($connectIndex !== "custom" && defined("db_connect") && isset(db_connect[$connectIndex])) {
            $servername = db_connect[$connectIndex]["servername"];
            $username = db_connect[$connectIndex]["username"];
            $password = db_connect[$connectIndex]["password"];
            $db_name = db_connect[$connectIndex]["db_name"];
            $this->currentDBIndex = $connectIndex;
        }
        if ($servername != "" && defined("db_host_name")) {
            $servername = db_host_name;
            $username = db_username;
            $password = db_password;
            $db_name = db_name;
            $this->currentDBIndex = "custom";
        }
        if ($servername == "") die("No Connection");
        // Define connection options for better performance and security
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4", // Proper character set
        ];

        try {
            $this->db = new PDO(
                "mysql:host=$servername;dbname=" . $db_name . ";charset=utf8mb4",
                $username,
                $password,
                $options
            );
            
            // Set session wait timeout to prevent long-running queries
            $this->db->exec("SET SESSION wait_timeout=300");
            
        } catch (PDOException $e) {
            // Log the error securely without exposing sensitive information
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed. Please check your database configuration.");
        }
    }

    function get_index() {
        $newDBIndex = null;
        if( isset($_GET['for_date']) && $_GET['for_date'] == '2024') {
        $newDBIndex = 1;
        }
        return $newDBIndex;
    }

    protected function verify_captcha()
    {
        $captchaToken = $_POST['cf-turnstile-response'] ?? '';
        if (empty($captchaToken)) {
            $this->message("Robot verification failed. Please try again.", "error");
            return;
        }

        $secretKey = $this->get_settings("cloudflare_key"); // Replace with your Cloudflare Secret Key
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        $data = [
            'secret' => $secretKey,
            'response' => $captchaToken,
            'remoteip' => $_SERVER['REMOTE_ADDR'] // Optional: Verify IP address
        ];

        // Send POST request to Cloudflare
        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result, true);
        if (!$response['success']) {
            $this->message("Robot verification failed. Please try again.", "error");
            return false;
        }
        return true;
    }

    function is_ofline_buyer($userID)
    {
        $idlist = $this->get_settings("offline_buyers");
        // Split the $idlist by commas and whitespace
        $id_array = array_map('trim', explode(",", $idlist));
        // Check if the $userID is in the $id_array
        if (in_array($userID, $id_array)) {
            return true;
        } else {
            return false;
        }
    }

    function get_visitor_details()
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
        $data = ["ip_address" => $ip, "device" => $deviceInfo, "browser" => "",  "theme" => "light", "country" => "", "postal_code" => "", "state" => "", "city" => ""];
        $apiUrl = "http://ip-api.com/json/{$ip}";
        try {
            $locationData = json_decode(file_get_contents($apiUrl));
            // var_dump($locationData);
            if ($locationData && $locationData->status === 'success') {
                // var_dump($locationData);
                $data['country'] = $locationData->country;
                $data['state'] = $locationData->regionName;
                $data['city'] = $locationData->city;
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
      
        if (isset($_COOKIE['browser_theme'])) {
            $data['theme'] = htmlspecialchars($_COOKIE['browser_theme']);
        }
        setcookie("visitor_details", serialize($data), time() + 12 * 60 * 60);
        //    var_dump($data);
        return $data;
    }

    function validate_admin()
    {
        if (isset($_SESSION['adminSession'])) {
            return true;
        }
        return false;
    }

    function new_activity($data)
    {
        if (isset($_SESSION['anonymous'])) {
            return null;
        }
        // $data = 'userID', "date_time", "action_name", "link", "action_for", "action_for_ID";
        if (is_array($data) && isset($data['userID'])) {
            $info = [];
            if ($this->getall("users", "ID = ? and acct_type = ?", [$data['userID'], "bot"], fetch: "") > 0) {
                return true;
            }
            $info['userID'] = $data['userID'];
            unset($data['userID']);
            // var_dump($this->get_visitor_details());
            $visitor_info = [];
            // if(!isset($_SESSION['adminSession'])) {
            $visitor_info = $this->get_visitor_details();
            // }
            $info = array_merge($info, $visitor_info, $data);
            if (!$this->quick_insert("activities",  $info)) {
                return false;
            }
            return true;
        } else {
            return false;
        }
    }

    function new_notification(array $data, $what = "quick")
    {
        if (is_array($what != "quick")) {
            if (!isset($_POST['time_set'])) {
                $_POST['time_set'] = time();
            }
            $info = $this->validate_form($data, 'notifications', "insert");
            if ($info) {
                return true;
            }
        } else {
            if ($this->quick_insert("notifications",  $data)) {
                return true;
            }
        }
        return false;
    }
    // USEAGE
    // Get information from the database using where condition
    // CODE: $members = $d->getall('members', 'email = ?', ['www@gmail.com '], fetch: "moredetails");
    // get all info from database with no conditions
    // CODE: $members = $d->getall(from: 'members', fetch: "moredetails");
    // get info from database with  no conditions but with a limit
    // CODE: $members = $d->getall(from: 'members', where: "LIMTI 10" fetch: "moredetails");

    function getall($from, $where = "", array $data = [], $select = "*", $fetch = "details", $iscacheable = false, $cacheTime = 1200, $isrenew = false, $newDBIndex = null)
    {
        // If a new DB index is provided, use a new instance for that connection
        if ($newDBIndex !== null) {
            // die("Gor here");
            $db = new database($newDBIndex);
            // Call getall on the new instance, but don't pass newDBIndex again to avoid infinite recursion
            return $db->getall($from, $where, $data, $select, $fetch, $iscacheable, $cacheTime, $isrenew, null);
        }

        $originalDBIndex = $this->currentDBIndex;
        
        // Create a directory for caching based on the 'from' parameter
        $cacheDir =  rootFile . '/cache/' . strtok(trim(strtolower($from)), " ");
        if (!is_dir($cacheDir) && ($iscacheable == true || $isrenew == true)) {
            mkdir($cacheDir, 0777, true); // Create the directory if it doesn't exist
             // Define the cache file path based on the query
            }
            $cacheFile = $cacheDir . '/' . md5($where . json_encode($data) . '_' . $originalDBIndex) . '_cache.json'; // Unique cache file name

        // Check if caching is enabled
        if ($iscacheable && $isrenew == false && file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
            // Read from cache
            $cachedData = json_decode(file_get_contents($cacheFile), true);
            return (count($cachedData) == 1 && isset($cachedData['data'][0])) ? $cachedData['data'][0] : $cachedData['data']; // Return the cached data
        }

        // If fetch mode is empty and select is "*", set select to count
        if ($fetch == "" && $select == "*") {
            $select = "COUNT(*) as count";
        }

        // Prepare the base SQL query
        $sql = "SELECT $select FROM $from";

        // Append WHERE clause if provided
        if (!empty($where) && substr($where, 0, 5) != "LIMIT") {
            $sql .= " WHERE $where";
        } elseif (substr($where, 0, 5) == "LIMIT" || empty($where)) {
            // If LIMIT is specified or no where clause, just append it
            $sql .= " $where";
        }

        // Prepare the statement
        $q = $this->db->prepare($sql);

        // Execute the statement with parameters
        $q->execute($data);

        // Fetch results based on the specified fetch mode
        $result = $this->getmethod($q, $fetch, $iscacheable);
        // Cache the result if caching is enabled
        if ($iscacheable || $isrenew == true) {
            $cacheData = [
                'queryString' => $sql, // Store the query string
                'data' => $result // Store the actual data
            ];
            file_put_contents($cacheFile, json_encode($cacheData));
        }
        return $result; // Return the result directly
    }
    // function getall($from, $where = "", array $data = [], $select = "*", $fetch = "details")
    // {
    //     if ($fetch == "" && $select == "*") {
    //         $select = "COUNT(*) as count";
    //     }
    //     if (substr($where, 0, 5) == "LIMIT" || substr($where, 0, 5) == "limit" || $where == "") {
    //         $q = $this->db->prepare("SELECT $select FROM $from $where");
    //     } else {
    //         $q = $this->db->prepare("SELECT $select FROM $from  where $where");
    //     }
    //     $q->execute($data);
    //     return $this->getmethod($q, $fetch);
    // }

function clearCache($folder = null)
    {
        if ($folder) {
            if(is_array($folder)) {
                foreach($folder as $f) {
                    $this->clearfolder($f);
                }
                return;
            }
           $this->clearfolder($folder);
        } else {
            // Clear all cache
            array_map('unlink', glob("cache/*")); // Delete all files in the cache directory
            rmdir("cache"); // Remove the cache directory
        }
    }

    function clearfolder($folder)
    {
        if ($folder) {
            // Clear cache for a specific folder
            $cacheDir = rootFile . '/cache/' . strtolower($folder);
            if (is_dir($cacheDir)) {
                array_map('unlink', glob("$cacheDir/*")); // Delete all files in the folder
                rmdir($cacheDir); // Remove the folder itself
            }
        }
    }
    public function getCount($table, $conditions, $params)
    {
        // Prepare the SQL statement with COUNT(*)
        $sql = "SELECT COUNT(*) AS count FROM {$table} WHERE {$conditions}";
        // Prepare and execute the statement
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        // Fetch the result
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Return the count
        return $result['count'];
    }


    // USEAGE
    // Insert single data
    // $d->quick_insert("members",
    // [
    //     "firstname" => "tolu",
    //     "lastname" => "ajayi",
    //     "email" => "tolu@gmail.com",
    //     "phonenumber" => "3444334",
    //     "address" => "bawa",
    //     "password" => "dkdkdkdkdk"
    // ],
    // );
    // insert multiple data
    // $d->quick_insert("members",
    //[ 
    // [
    //     "firstname" => "tolu",
    //     "lastname" => "ajayi",
    //     "email" => "tolu@gmail.com",
    //     "phonenumber" => "3444334",
    //     "address" => "bawa",
    //     "password" => "dkdkdkdkdk"
    // ],
    // [
    //     "firstname" => "tolu",
    //     "lastname" => "ajayi",
    //     "email" => "tolu@gmail.com",
    //     "phonenumber" => "3444334",
    //     "address" => "bawa",
    //     "password" => "dkdkdkdkdk"
    // ],
    // ]
    // );

    function quick_insert($into, array $data, $message = null)
    {
        if (isset($data[0]) && is_array($data[0])) {
            foreach ($data as $row) {
                $insert =  $this->insert_data($into, $row);
                if (isset($insert)) {
                    $this->get_message($message);
                }
            }
            // return true;
        } else {
            $insert =  $this->insert_data($into, $data);
            $this->get_message($message);
            return true;
        }
        return false;
    }

    function get_message($message =  null)
    {
        if ($message == null) {
            return null;
        }
        $this->message($message, "success");
        return true;
    }
    // $update = $d->update("members", ["firstname"=>"tunde", "email"=>"tunde@gmail.com"], "ID = '4'");
    function update($what, $data, $where, $message = null)
    {
        $this->get_index_data($data, "update");
        $query = $this->db->prepare("UPDATE $what SET $this->index WHERE $where");
        $update = $query->execute($this->data);
        if ($update) {
            $this->get_message($message);
            return true;
        }
        return false;
    }
    // $d->delete("members", "ID = ? or phonenumber = ?", [3, 3434]);
    function delete($from, $where, array $data)
    {
        $query = $this->db->prepare("DELETE FROM $from WHERE $where ");
        $delete = $query->execute($data);
        if ($delete) {
            return true;
        }
        return false;
    }

    private function get_index_data(array $data, $type = "insert")
    {
        $index = '';
        $marks = '';

        if ($type == "insert") {
            foreach ($data as $key => $k) {
                $index .= "`$key`, ";
                $marks .= "?, ";
            }
        }

        if ($type == "update") {
            foreach ($data as $key => $value) {
                $index .= "`$key` = ?, ";
                $marks .= "?, ";
            }
        }

        $this->index = rtrim($index, ", ");
        $this->marks = rtrim($marks, ", ");
        $this->data = array_values($data);
        return true;
    }

    private function getmethod($q, $fetch, $iscacheable = false)
    {
        // For count queries
        if ($fetch == "") {
            $data = $q->fetch(PDO::FETCH_ASSOC);
            if (isset($data['count'])) return $data['count'];
        }

        // For single row queries
        if ($fetch == "details" || $fetch == "single" || $fetch == "s") {
            // Use fetch with FETCH_ASSOC for better performance
            return $q->fetch(PDO::FETCH_ASSOC);
        }

        // For multiple rows
        if ($fetch == "moredetails" || $fetch == "all" || $fetch == "a") {
            if ($iscacheable) {
                // Use fetchAll with FETCH_ASSOC for better performance
                return $q->fetchAll(PDO::FETCH_ASSOC);
            }
            // Return statement for non-cached queries
            return $q;
        }

        // For row count
        return $q->rowCount();
    }

    // Add new method for optimized bulk fetching
    public function bulkFetch($table, $where = "", array $data = [], $select = "*", $fetch = "moredetails", $batchSize = 1000)
    {
        if (empty($where)) {
            $sql = "SELECT $select FROM $table";
        } else {
            $sql = "SELECT $select FROM $table WHERE $where";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);

        if ($fetch == "moredetails" || $fetch == "all" || $fetch == "a") {
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = $row;
                // If we reach batch size, yield the results
                if (count($results) >= $batchSize) {
                    yield $results;
                    $results = [];
                }
            }
            // Yield any remaining results
            if (!empty($results)) {
                yield $results;
            }
        } else {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    // Add method for optimized single record fetch
    public function fetchOne($table, $where = "", array $data = [], $select = "*")
    {
        if (empty($where)) {
            $sql = "SELECT $select FROM $table LIMIT 1";
        } else {
            $sql = "SELECT $select FROM $table WHERE $where LIMIT 1";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Add method for optimized count
    public function count($table, $where = "", array $data = [])
    {
        if (empty($where)) {
            $sql = "SELECT COUNT(*) as count FROM $table";
        } else {
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $where";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    // Add method for optimized exists check
    public function exists($table, $where = "", array $data = [])
    {
        if (empty($where)) {
            $sql = "SELECT 1 FROM $table LIMIT 1";
        } else {
            $sql = "SELECT 1 FROM $table WHERE $where LIMIT 1";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (bool) $stmt->fetch(PDO::FETCH_COLUMN);
    }

    // Add method for optimized pagination
    public function paginate($table, $where = "", array $data = [], $page = 1, $perPage = 20, $select = "*")
    {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $total = $this->count($table, $where, $data);
        
        // Get paginated data
        if (empty($where)) {
            $sql = "SELECT $select FROM $table LIMIT $perPage OFFSET $offset";
        } else {
            $sql = "SELECT $select FROM $table WHERE $where LIMIT $perPage OFFSET $offset";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    function create_table($name, array $data, $isCreate = true)
    {
        if (!is_array($data)) {
            return null;
        }
        if ($isCreate == true && $this->check_table($name)) {
            return true;
        }
        $info = $this->get_table_para($data, $isCreate);
        $action = !$isCreate  ? "ALTER" : "CREATE";
        $name = !$isCreate  ? $name . " ADD" : $name;
        $query = $this->db->prepare("$action  TABLE $name ($info)");
        try {
            $update = $query->execute();
        } catch (\Throwable $th) {
            if (str_contains($th, "Column already exists")) {
                return true;
            }
            return false;
        }
        return true;
    }
    function check_table($name)
    {
        try {
            $query = $this->db->prepare("select 1 from $name");
            $update = $query->execute();
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
    function get_table_para(array $datas, $isCreate = true)
    {
        $info = "";
        foreach ($datas as $key => $data) {
            $key = str_replace('[]', '', $key);
            $type = "VARCHAR(250)";
            $default_value = "";
            $isNull = "NOT NULL";
            if ($key == "ID" && isset($data['input_type']) && $data['input_type'] == "number") {
                $isNull .= " AUTO_INCREMENT";
            }
            $primaryKey = "";
            if (isset($data['input_type']) && $data['input_type'] == "number") {
                match (htmlspecialchars($data['input_type'])) {
                    "number" => $type = "INT(100)",
                };
            }
            if (isset($data['type']) && ($data['type'] == "textarea" || $data['type'] == "custom_fields_builder")) {
                $type = "TEXT";
            }
            if (isset($data['is_required']) && $data['is_required'] == false) {
                $isNull = "NULL";
            }
            if (isset($datas['input_data'][$key])) {
                $default_value = "DEFAULT '" . htmlspecialchars($datas['input_data'][$key]) . "'";
            }

            $info .= "$key $type $isNull $default_value,";
        }
        if (!$isCreate) return rtrim($info, ',');
        $info .= "`date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,";
        if (isset($datas['ID'])) {
            $info  .= "PRIMARY KEY(ID),";
        }
        return rtrim($info, ',');
    }
    function insert_data($into, $data)
    {
        $this->get_index_data($data);
        $query = $this->db->prepare("INSERT INTO $into ($this->index) values ($this->marks)");
        // if($into == "message") {
        //     var_dump($this->data);
        // }
        $insert = $query->execute($this->data);
        if ($insert) {
            return true;
        } else {
            return false;
        }
    }

    function options_list($table, $key = "ID", $value = "name", $where = "", array $params = [])
    {

        if (is_string($table)) $table = $this->getall("$table", $where, $params, fetch: "moredetails");
        if ($table->rowCount() > 0) {
            foreach ($table as $row) {
                $data[$row[$key]] = $this->pass_value($value, $row);
            }
        }
        return $data ?? [];
    }

    function pass_value($value, $row)
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
    function isPasswordHash($string)
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


    function validate_form($datas, $what = "", $action = null, bool $showError = true)
    {
        $err = false;
        $info = [];
        if (!is_array($datas)) {
            return false;
        }
        $wait = [];
        $files = [];
        foreach ($datas as $key => $data) {

            if ($key == "input_data") {
                continue;
            }
            if (isset($data["input_type"]) && $data["input_type"] == "file") {
                $info[$key] = "";
                $files[$key] = $data;
                continue;
            }
            if ($this->check_if_required($data)) {
                if (!isset($_POST[$key]) || empty($_POST[$key])) {
                    if ($showError) {
                        echo $this->message(ucwords(str_replace("_", " ", $key)) . " is required", "error");
                    }
                    $err = true;
                    continue;
                }
            }

            if (isset(Regex[$key]) && Regex[$key]['value'] && !empty($_POST[$key])) {
                if (!preg_match(Regex[$key]['value'], htmlspecialchars($_POST[$key]))) {
                    $err = true;
                    if ($showError) {
                        echo $this->message(Regex[$key]["error_message"], "error");
                    }
                    continue;
                }
            }

            // mangedate and time here
            if (isset($data['input_type']) && $data['input_type'] == "datetime-local" && isset($_POST[$key]) && !empty($_POST[$key])) {
                $dateTime = new DateTime($_POST[$key]);
                $_POST[$key] = $dateTime->format('Y-m-d H:i:s');
            }

            if (isset($data['timestamp']) && $data['timestamp'] == true && $_POST[$key] != "") {
                $_POST[$key] = strtotime($_POST[$key]);
            }

            if (isset($_POST[$key]) && is_array($_POST[$key])) {
                $info[$key] = json_encode($_POST[$key]);
            } elseif (isset($_POST[$key])) {
                if (isset($data['type']) && $data['type'] == 'custom_fields_builder') {
                    $info[$key] = $_POST[$key];
                } else {
                    $info[$key] = htmlspecialchars($_POST[$key]);
                }
            } else {
                $info[$key]  = "";
            }
            if (isset($data['unique'])) {
                $wait[] = $key;
            }
        }

        if (isset($datas["password"]) && isset($datas['confirm_password']) && !empty($data['password'])) {
            if ($_POST['password'] != $_POST['confirm_password']) {
                $err = true;
                if ($showError) {
                    echo $this->message("Password and confrim password do not match", "error");
                }
                return null;
            }
        }
        // if(isset($_POST['password']) && $_POST['password'] != "") {
        //     $info['password'] = password_hash($_POST['password'],  PASSWORD_DEFAULT);
        // }

        if ($what != "") {
            if (!$this->validate_database_data($what, $wait, $datas, $info)) {
                $err = true;
            }
        }

        if (count($files) > 0 && $err != true) {
            $files_set = [];
            foreach ($files as $key => $row) {
                $files_set[$key] =  $this->proccess_single_image($key, $row, $datas);
            }
            if (in_array(false, $files_set)) {
                return null;
            }
            // var_dump($files_set);
            foreach ($files_set as $key => $value) {
                // echo $value;
                // var_dump(gettype($value));
                // echo $key;var_dump(gettype($value));
                if ($value == "no--value") {
                    $info[$key] = "";
                    continue;
                }

                if ($value == "upload--this--file") {
                    // var_dump($value);
                    $file_name = $key;
                    if (isset($datas[$key]['file_name'])) {
                        $file_name = $datas[$key]['file_name'];
                    }
                    if (isset($datas[$key]['formart'])) {
                        $vaild_formart = $datas[$key]['formart'];
                    }
                    $path = $datas[$key]['path'] ?? "upload/";
                    $image = $this->process_image($file_name, $path, $key, valid_formats1: $vaild_formart ?? null);
                    if (!$image) {
                        return null;
                    }
                    $info[$key] = $image;
                    continue;
                }

                $info[$key] = $value;

                // var_dump($info);
            }
        }

        // var_dump($info);
        if (!$err) {
            if (isset($info['confirm_password'])) {
                unset($info['confirm_password']);
            }

            if (!$this->database_action($action, $info, $what)) {
                return false;
            }
            return $info;
        }
        return null;
    }

    private function database_action($action, $data, $what)
    {
        if (!is_array($data) || empty($what) || $action == null) {
            return true;
        }
        if (isset($data['password']) && !$this->isPasswordHash($data['password'])) {
            $data['password'] = password_hash($data['password'],  PASSWORD_DEFAULT);
        }
        switch ($action) {
            case 'insert':
                if (!$this->quick_insert($what, $data)) {
                    return false;
                }
                return true;

            case 'update':
                if (!isset($data['ID'])) {
                    return false;
                }
                $id = $data['ID'];
                if (!$this->update($what, $data, "ID = '$id'")) {
                    return false;
                }
                return true;
            default:
                return true;
        }
    }
    function check_if_required($data)
    {
        if (isset($data['is_required']) && $data['is_required'] == true || !isset($data['is_required'])) {
            return true;
        }
        return false;
    }
    function validate_database_data($what, $wait, $datas, $info): bool
    {
        $error = false;
        $idc = "";
        $idv = "";
        if (isset($datas['ID']) && isset($info['ID'])) {
            $idc = "ID != ? and ";
            $idv = $info["ID"];
        }
        // print_r($wait);

        foreach ($wait as $k => $key) {
            // echo $key;

            if (!isset($datas[$key]["unique"])) {
                return true;
            }
            $against = $datas[$key]["unique"];
            // var_dump($against);
            if ($against == "") {
                $datacheck = [$info[$key]];
                if ($idv != "") {
                    $datacheck = [$idv, $info[$key]];
                }
                $check = $this->getall("$what", "$idc $key = ?", $datacheck, fetch: "");
            }
            // exit();

            if (!isset($check)) {
                if (!isset($datas[$against])) {
                    $error = true;
                    echo $this->message("Int: We have issues to validate your data. please reload the page and try again", "error");
                    return false;
                }

                if ((int)array_search($key, array_keys($datas)) > (int)array_search($against, array_keys($datas))) {

                    $datacheck = [$info[$against], $info[$key]];
                    if ($idv != "") {
                        $datacheck = [$idv, $info[$against], $info[$key]];
                    }

                    $check = $this->getall($what, "$idc $against = ? and $key = ?", $datacheck, fetch: "");
                    // var_dump("$idc $against = ? and $key = ?"); exit();
                    // var_dump($check);



                } else {
                    $datacheck = [$info[$key], $info[$against]];
                    if ($idv != "") {
                        $datacheck = [$idv, $info[$key], $info[$against]];
                    }

                    $check = $this->getall($what, "$idc $key = ? and $against = ?", $datacheck, fetch: "");
                    //  data here
                }
            }
            if ($check > 0) {
                $error = true;
                echo $this->message("This exact " . $this->clean_str($what) . " already exist", "error");
                $check = null;
            }
        }
        if ($error) {
            return false;
        }
        return true;
    }

    function clean_str($string)
    {
        // echo $this->message("Int: We have issues to validate your data. please reload the page and try again", "error");
        return ucwords(str_replace("_", " ", $string));
    }
    function checkmessage($arry)
    {
        foreach ($arry as $key) {
            $check = substr($key, -5);
            if ($check == "_null") {
                $key = substr_replace($key, "", -5);
            }
            $key = str_replace(" ", "_", $key);
            if ($check != "_null") {
                if ($_POST["$key"] == "" || !isset($_POST["$key"]) && $key != "referral_code") {
                    $this->err = "yes";
                    database::message("Please enter your $key", "error");
                } else {
                    $set["$key"] = ${$key} = htmlspecialchars($_POST["$key"]);
                }
            } else {
                $set["$key"] = ${$key} = htmlspecialchars($_POST["$key"]);
            }
        }
        if (isset($set['password']) && isset($set['confirm_password'])) {
            if (isset($set['confirm_password'])) {
                $check = database::checkpass($set['password'], $set['confirm_password']);
                if ($check) {
                    return $set;
                } else {
                    $this->err = "yes";
                }
            } else {
                $this->err = "yes";
                database::message("IntErr: We can't confirm your password", "error");
            }
        } elseif ($this->err != "yes") {
            return $set;
        } else {
            return $this->err;
        }
    }

    private function checkpass($password, $cpass)
    {
        // Validate password strength
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $number    = preg_match('@[0-9]@', $password);
        // $specialChars = preg_match('@[^\w]@', $password);

        if (!$uppercase || !$lowercase || !$number || strlen($password) < 4) {
            database::message("Password should be at least 8 characters in length and should include at least one upper case letter and one number.", "error");
            return false;
        } else {
            if ($password == $cpass) {
                return true;
            } else {
                database::message("Password don't match. Check and try again", "error");
                return false;
            }
        }
    }
    public function message($message, $type, $method = "default", $isDie = true)
    {
        // die(ISAPI);
        // $isapi =  ? true : false;
        if (defined('ISAPI') && ISAPI == true) {
            $method = "json";
        }
        // die($method);

        if ($type == "error") {
            $type = "danger";
        } elseif ($type == "success") {
            $type = "success";
        }
        $message = str_replace("_", " ", $message);
        //     echo "<div class='bg-$type fade show mb-5' role='bg'>
        //     <!--  <div class='bg-icon'><i class='flaticon-$type'></i></div> -->
        //     <div class='bg-text'>$message</div>
        // </div>";
        if ($type == "success" && $method == "default") {
            echo "<div class='message p-2 mt-1 mb-2 rounded-2 bg-light-success $type' style='color:green!important'>
                <span class='closebtn' onclick=\"this.parentElement.style.display='none';\">&times;</span>
                $message
                </div>";
        } elseif ($type == "danger" && $method == "default") {
            echo "<div class='message mt-1 p-2 mb-2 rounded-2 bg-light-danger $type' style='color:red!important'>
                <span class='closebtn' onclick=\"this.parentElement.style.display='none';\">&times;</span>
                $message
                </div>";
        }

        if ($type == "success" && $method == "json") {
            if(defined('ISAPI') && ISAPI == true && $isDie == true)  {
                die($this->apiMessage($message, 200));
            }  
            return ($this->apiMessage($message, 200));
        } elseif ($type == "danger" && $method == "json") {
             if(defined('ISAPI') && ISAPI == true  && $isDie == true) { die($this->apiMessage($message, 400)); } 
             return ($this->apiMessage($message, 400));
        }
    }

    function sendverifyemail($userID)
    {
        $d = new database;
        $user = $d->getall("users", "ID = ?", [$userID]);
        if (!is_array($user)) {
            $d->message("User not found please login and try again", "error");
            return false;
        }

        if ($user['email_verify'] == 1) {
            $d->message("Seems user account is verified please login into your account", "error");
            return false;
        }
        $token = $user['token'];
        if ($user['token'] == "0") {
            $token = $d->randcar(40);
            $d->update("users", "", "ID = '$userID'", ["token" => $token]);
        }
        $email = $user['email'];
        $sendemail = $d->smtpmailer(1, $user['email'], "Account Email Verification", "Please verify your account with the link provided below <br> <a href='verify.php?token=$token&e=$email'>verify Account</a>");
        if ($sendemail) {
            $d->message("Email Sent Successfully", "success");
        } else {
            $d->message("Error sending email please try again later", "error");
        }
    }

        

    function randcar($no = 20)
    {
        $seed = str_split('abcdefghijklmnopqrstuvwxyz'
            . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'
            . '0123456789'); // and any other characters
        shuffle($seed); // probably optional since array_is randomized; this may be redundant
        $rand = '';
        foreach (array_rand($seed, $no) as $k) $rand .= $seed[$k];
        return "3" . $rand;
    }

    function send_email($userID, $subject, $message, $notify = false)
    {
        if (is_array($notify)) {
            $data = [];
            $data['userID'] = $userID;
            $data['n_for'] = "users";
            $data['title'] = $subject;
            $data['description']  = $message;
            $data['time_set'] = time();
            $this->new_notification($data);
        }
        $smessage = $this->get_email_template("default")['template'];
        $user =  $this->getall("users", "ID = ?", [$userID], "first_name, last_name, email");
        if (!is_array($user)) {
            return false;
        }
        // ${amount} ${reason} ${website_url} 
        $smessage = $this->replace_word(['${first_name}' => $user['first_name'], '${last_name}' => $user['last_name'], '${message_here}' => $message, '${website_url}' => $this->get_settings("website_url")], $smessage);
        $sendmessage = $this->smtpmailer($user['email'], $subject, $smessage);
        if ($sendmessage) {
            return true;
        } else {
            return false;
        }
    }
    
    function send_relay_email($email, $title, $message) {
            // API endpoint
            $apiUrl = 'http://localhost/index.php';
        
            // Your API key
            $apiKey = '7Fh2Q9LmTa8zR3uKp1vXw5YcB0dSgNe';
        
            // Data payload
            $data = [
                'apikey'  => $apiKey,
                'title'   => $title,
                'message' => $message,
                'email'   => $email
            ];
        
            // Initialize cURL
            $ch = curl_init($apiUrl);
        
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_TIMEOUT => 1000
            ]);
        
            // Execute request
            $response = curl_exec($ch);
            var_dump($response);
            die();
            if (curl_errno($ch)) {
                curl_close($ch);
                return false;
            }
        
            curl_close($ch);
        
            // Decode JSON response
            $result = json_decode($response, true);
        
            // Return true only if successful
            return isset($result['status']) && $result['status'] === 'success';
        }

    function smtpmailer($to, $subject, $body, $name = "", $message = '', $smtpid = 3, $attachment = null)
    {
        $smtpid = 3;
        $body = htmlspecialchars_decode($body);
        if (isset($_ENV['mail_type']) && $_ENV['mail_type'] == "log") {
            $maillog = fopen("maillog.log", "w");
            $header = "<p>To: $to </p>";
            $header .= "<h3>Subject: $subject </h3>";
            $header .= "<p>name: $name </p>";
            $body = $header . $body . "<hr>";
            fwrite($maillog, $body);
            return true;
        }
        // return $to;
        require_once (str_replace("app/api", "", rootFile)) . "/include/phpmailer/PHPMailerAutoload.php";
        // require_once "";
        $d = new database;
        $smtp = $d->getall("smtp_config", "ID = ?", ["$smtpid"]);
        if (!is_array($smtp)) {
            $d->message("Something went wrong", "error");
            // $d->message("SMTP selected not found please choose another one or refresh page and try again", "error");
            return false;
        }
        $server = $smtp['server'];
        $username = $smtp['username'];
        $password = $smtp['password'];
        $port = $smtp['port'];
        $smtp_from_email = $smtp['from_email'];
        if($username == $to || $smtp_from_email == $to) return false;
        if(!$this->canSendEmail($to)) {
            return false;
        }

        // echo $body;
        try {
            $from = $username;
            $mail = new PHPMailer(true);
            $mail->IsSMTP();
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'ssl';
            $mail->Host = "$server";
            $mail->Port = "$port";
            $mail->Username = "$username";
            $mail->Password = "$password";

            // Add attachment if provided
            if ($attachment && file_exists($attachment)) {
                $mail->AddAttachment($attachment, basename($attachment));
            }

            $mail->IsHTML(true);
            $mail->From = "$username";
            $mail->FromName = $username;
            $mail->Sender = "$smtp_from_email";
            $mail->AddReplyTo("$username", $username);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AddAddress($to);
            // die(var_dump($mail));
            $send = $mail->Send();
            // if (isset($_GET['debug'])) {
            //     var_dump($mail);
            //     echo "<hr>";
            //     die(var_dump($send));
            // }
            if ($send) {
                $this->updateEmailHistory($to);
                return true;
            }
        } catch (phpmailerException $e) {
            // die(var_dump($e));
            if (isset($_GET['debug'])) die(var_dump($e, $smtp));
            return false;
        } catch (Exception $e) {
            // die(var_dump($e));
            if (isset($_GET['debug'])) die(var_dump($e, $smtp));
            return false;
        }
    }

    function canSendEmail($email)
    {
        $emailFile = (str_replace("app/api", "", rootFile)) . "/assets/json/emailhistory.json";
        $data = [];

        // Create file if it doesn’t exist
        if (!file_exists($emailFile)) {
            file_put_contents($emailFile, json_encode([]));
        }

        // Read existing data
        $content = file_get_contents($emailFile);
        if (!empty($content)) {
            $data = json_decode($content, true);
            if (!is_array($data)) $data = [];
        }

        $now = time();
        $cooldown = 8 * 60; // 8 minutes in seconds

        // Check if email was sent recently
        if (isset($data[$email])) {
            $lastSent = intval($data[$email]);
            if (($now - $lastSent) < $cooldown) {
                return false; // within cooldown
            }
        }

        return true;
    }

    function updateEmailHistory($email)
    {
        $emailFile = (str_replace("app/api", "", rootFile)) . "/assets/json/emailhistory.json";
        $data = [];

        if (file_exists($emailFile)) {
            $content = file_get_contents($emailFile);
            if (!empty($content)) {
                $data = json_decode($content, true);
                if (!is_array($data)) $data = [];
            }
        }

        // Update with current timestamp
        $data[$email] = time();

        // Save file
        file_put_contents($emailFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function imageupload($name)
    {
        //��heck that we have a file
        if ((!empty($_FILES["uploaded_file"])) && ($_FILES['uploaded_file']['error'] == 0)) {
            //Check if the file is JPEG image and it's size is less than 350Kb
            $filename = basename($_FILES['uploaded_file']['name']);
            $ext = substr($filename, strrpos($filename, '.') + 1);
            if (($ext == "jpg") && ($_FILES["uploaded_file"]["type"] == "image/jpeg") &&
                ($_FILES["uploaded_file"]["size"] < 350000)
            ) {
                //Determine the path to which we want to save this file
                $name = $name . '.' . $ext;
                $newname = 'upload/' . $name;
                //Check if the file with the same name is already exists on the server
                // if (!file_exists($newname)) {
                //Attempt to move the uploaded file to it's new place
                if ((move_uploaded_file($_FILES['uploaded_file']['tmp_name'], $newname))) {
                    //  database::message("Passport Uploaded Successfully", "success");
                    return $name;
                } else {
                    database::message("Error: A problem occurred during Passport upload!", "error");
                    return false;
                }
                // } else {
                //    echo "Error: File ".$_FILES["uploaded_file"]["name"]." already exists";
                // }
            } else {
                database::message("Error: Only .jpg images under 350Kb are accepted for upload", "error");
                return false;
            }
        } else {
            database::message("Error: No image uploaded", "error");
            return false;
        }
    }

    function upload_canvas($img, $upload_dir = "upload/")
    {
        $img = str_replace('data:image/png;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        $file = $upload_dir . "image_name.png";
        $success = file_put_contents($file, $data);
        return $success;
    }
    protected function proccess_single_image($key, $value, $datas)
    {
        if (!$this->check_if_required($value)) {

            if ($_FILES[$key]['name'] == "" && isset($datas['input_data'][$key]) && $datas['input_data'][$key] != "") {
                return $datas['input_data'][$key];
            }
            // var_dump($key);
            if ($_FILES[$key]['name'] == "") {
                return  "no--value";
            }

            return "upload--this--file";
        }

        if (!isset($_FILES[$key]['name']) || $_FILES[$key]['name'] == "") {
            $key = str_replace("_", " ", $key);
            database::message("You need to upload $key", "error");
            return false;
        }
        if (!isset($value['path']) || $value['path'] == "") {
            $key = str_replace("_", " ", $key);
            $this->message("Intr: No path set for $key. <br> Note: this error is an internal error, you are not the reason for the error. <br> Please report to us on <a href='mailto:" . $this->get_settings("support_email") . "' target='_BLANK'>" . $this->get_settings("support_email") . "</a>", "error");
            return false;
        }
        return "upload--this--file";
    }
    protected function check_multiple_files($names)
    {
        $error = false;
        foreach ($names as $key => $value) {
            if ($this->check_if_required($value)) {
                if ($_FILES["$key"]['name'] == "" || !isset($_FILES["$key"]['name'])) {
                    $error = true;
                    database::message("You need to upload your $key", "error");
                } else {
                    $set["$key"] = ${$key} = htmlspecialchars($_FILES["$key"]['name']);
                }
            } else {
                $set["$key"] = ${$key} = htmlspecialchars($_FILES["$key"]['name']);
            }
        }

        if (!$error) {
            return $set;
        } else {
            return $this->err;
        }
    }

    function verbose($ok = 1, $info = "", $file_name = "")
    {
        if ($ok == 0) {
            http_response_code(400);
        }
        return json_encode(["ok" => $ok, "info" => $info, "filename" => $file_name]);
    }
    function chunk_upload($mainfilePath, $valid_formats1 = ["mp4", "mov"])
    {
        $filePath = $mainfilePath;
        // (B) INVALID UPLOAD
        if (empty($_FILES) || $_FILES["file"]["error"]) {
            return $this->verbose(0, "<small class='text-danger'>Failed to move uploaded file. Reload page and try again</small>");
        }

        if ((int)$_FILES["file"]["size"] * ((int)$_REQUEST["chunks"] - 1) > 209715200) {
            return $this->verbose(0, "<small class='text-danger'>File too large. MAX OF: 200MB, compress the file and try again</small>");
        }



        // (C) UPLOAD DESTINATION - CHANGE FOLDER IF REQUIRED!
        // $filePath = __DIR__ . DIRECTORY_SEPARATOR . "uploads";
        if (!file_exists($filePath)) {
            if (!mkdir($filePath, 0777, true)) {
                return $this->verbose(0, "Failed to create $filePath");
            }
        }
        $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : $_FILES["file"]["name"];
        $fileInfo = pathinfo($fileName);
        // var_dump($fileInfo);
        $ext = strtolower($fileInfo['extension']);

        if (!in_array($ext, $valid_formats1)) {
            return $this->verbose(0, "<small class='text-danger'>Video file Not Support. We support: " . implode(" ", $valid_formats1) . "</small>");
        }

        $filePath = $filePath . DIRECTORY_SEPARATOR . $fileName;

        // (D) DEAL WITH CHUNKS
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
        $out = @fopen("{$filePath}.part", $chunk == 0 ? "wb" : "ab");
        if ($out) {
            $in = @fopen($_FILES["file"]["tmp_name"], "rb");
            if ($in) {
                while ($buff = fread($in, 4096)) {
                    fwrite($out, $buff);
                }
            } else {
                return $this->verbose(0, "Failed to open input stream");
            }
            @fclose($in);
            @fclose($out);
            @unlink($_FILES["file"]["tmp_name"]);
        } else {
            return $this->verbose(0, "Failed to open output stream");
        }

        // (E) CHECK IF FILE HAS BEEN UPLOADED
        if (!$chunks || $chunk == $chunks - 1) {
            $fileName = uniqid("video-") . "." . $ext;
            $thefilePath = $mainfilePath . DIRECTORY_SEPARATOR . $fileName;
            rename("{$filePath}.part", $thefilePath);
            return $this->verbose(1, "Upload OK", $fileName);
        }
        return $this->verbose(1, "Upload OK");
    }
    function process_image($title, $path, $name = "uploaded_file", $i = 0, array $valid_formats1 = null, $compress = false)
    {
        //file to place within the server
        // echo $name;
        if ($valid_formats1 == null) {
            $valid_formats1 = ["JPG", "jpg", "png", "jpeg",  "svg"];
        }
        if (is_array($_FILES["$name"]["name"])) {
            if (empty($_FILES["$name"]["name"][0])) return null;
        } else {
            if ($_FILES["$name"]["name"] == "") return null;
        }
        if ($i == 0 && $name != "uploaded_file" && !is_array($_FILES["$name"]["name"])) {
            $image = $_FILES["$name"]["name"]; //input file name in this code is file1
            $size = $_FILES["$name"]["size"];
            $tmp = $_FILES["$name"]["tmp_name"];
        } else {
            $image = $_FILES["$name"]["name"][$i]; //input file name in this code is file1
            $size = $_FILES["$name"]["size"][$i];
            $tmp = $_FILES["$name"]["tmp_name"][$i];
        }
        // Check for upload errors
        $uploadError = 0;
        if (is_array($_FILES[$name]['error'])) {
             $uploadError = $_FILES[$name]['error'][$i];
        } else {
             $uploadError = $_FILES[$name]['error'];
        }

        if ($uploadError !== UPLOAD_ERR_OK) {
             switch ($uploadError) {
                 case UPLOAD_ERR_INI_SIZE:
                     database::message("The uploaded file exceeds the upload_max_filesize directive in php.ini", "error");
                     break;
                 case UPLOAD_ERR_FORM_SIZE:
                     database::message("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form", "error");
                     break;
                 case UPLOAD_ERR_PARTIAL:
                     database::message("The uploaded file was only partially uploaded", "error");
                     break;
                 case UPLOAD_ERR_NO_FILE:
                     database::message("No file was uploaded", "error");
                     break;
                 case UPLOAD_ERR_NO_TMP_DIR:
                     database::message("Missing a temporary folder", "error");
                     break;
                 case UPLOAD_ERR_CANT_WRITE:
                     database::message("Failed to write file to disk", "error");
                     break;
                 case UPLOAD_ERR_EXTENSION:
                     database::message("File upload stopped by extension", "error");
                     break;
                 default:
                     database::message("Unknown upload error", "error");
                     break;
             }
             return false;
        }

        //list of file extention to be accepted
        if (empty($image)) {
            database::message("No file selected", "error");
            return false;
        }
        if (isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST") {

            if ($size < 10000000) {
                $fileInfo = pathinfo($image);
                $ext = strtolower($fileInfo['extension']);

                if (in_array($ext, $valid_formats1)) {
                    if ($path == "check") {
                        return true;
                    }
                    $titlename = str_replace(" ", "_", $title);
                    $actual_image_name =  $titlename . "." . $ext;

                    if ($compress) {
                        if ($this->compress_image($tmp, $path . $actual_image_name, 60)) {
                            return $actual_image_name;
                        }
                    }

                    if (move_uploaded_file($tmp, $path . $actual_image_name)) {
                        return $actual_image_name;
                    } else {
                        database::message($message = '<b>' . $image . ': Image Not Uploaded Try again ', $type = 'error');
                        return false;
                    }
                } else {

                    database::message($message = '<b>' . $image . ':</b> Image file Not Support. We support: ' . implode(" ", $valid_formats1), $type = 'error');
                    return false;
                }
            } else {
                database::message("<b>$image</b>: Image too large. Make sure your image size is not above 10MB", "error");
                return false;
            }
        }
    }

    function compress_image($source, $destination, $quality)
    {
        if (!file_exists($source)) {
            return false;
        }
        $info = @getimagesize($source);
        if (!$info) {
            return false;
        }
        if ($info['mime'] == 'image/jpeg')
            $image = imagecreatefromjpeg($source);
        elseif ($info['mime'] == 'image/gif')
            $image = imagecreatefromgif($source);
        elseif ($info['mime'] == 'image/png')
            $image = imagecreatefrompng($source);
        else
            return false;

        if ($info['mime'] == 'image/jpeg') {
            imagejpeg($image, $destination, $quality);
        } elseif ($info['mime'] == 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $quality = 9 - round(($quality / 100) * 9);
            imagepng($image, $destination, $quality);
        } elseif ($info['mime'] == 'image/gif') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagegif($image, $destination);
        }
        return true;
    }

    function handleLinkInText($s)
    {
        // Decode URL-encoded entities to prevent broken links
        $s = htmlspecialchars_decode($s, ENT_QUOTES);
        // Replace URLs in text with proper links
        return preg_replace('@(https?://([-\w\.]+[-\w])+(:\d+)?(/([\w/_\.#-]*(\?\S+)?[^\s<>])?)?)@', '<a href="$1" target="_blank">$1</a><br>', $s);
    }
    // addtion functions

    function get_settings($value = "company_name", $where = "settings",  $who = "all", $type = "meta_for")
    {
        $data = $this->getall("$where", "meta_name = ? and meta_for = ?", [htmlspecialchars($value), $who]);
       
        if (!is_array($data)) {
            return "";
        }
        if ($this->isEncrypted($data['meta_value'])) {
            $data['meta_value'] = $this->get_enypt_data($data['meta_value']);
        }
        return ($type == "all") ? $data : $data['meta_value'];
    }

    protected function get_enypt_data($id)
    {
        $data = $this->getall("encrypted_data", "ID = ?", [$id]);
        if (!is_array($data)) return false;
        return $this->decryptData($data['meta_value']);
    }

    function isEncrypted($data)
    {
        $explode = explode("-", $data);
        if ($explode[0] == "enyptdata") return true;
        return false;
    }

    protected function enypt_unlink($id)
    {
        if ($this->delete("encrypted_data", "ID = ?", [$id])) return true;
        return false;
    }
    function enypt_and_save_data($data)
    {
        if ($data == null || $data == "") return false;
        $mainData = $data;
        $data = $this->encryptData($data);
        if ($data == false || $data == "") return false;
        $data = [
            "ID" => uniqid("enyptdata-"),
            "meta_value" => $data
        ];
        if ($this->quick_insert("encrypted_data", $data)) {
            $data['data'] = $mainData;
            return $data;
        }
        return false;
    }

    function create_settings(array $data, $what = "settings")
    {
        if (!is_array($data)) {
            return null;
        }
        foreach ($data as $key => $value) {
            if ($this->getall($what, "meta_name = ?",  [$key], fetch: "") > 0) {
                continue;
            }
            $this->quick_insert($what, ["meta_name" => $key, "meta_value" => $value]);
        }
    }

    function replace_word(array $data, $word)
    {
        // $word = $word;
        foreach ($data as $key => $value) {
            $value = htmlspecialchars($value);
            if (!strpos($word, $key)) {
                continue;
            }
            $word = str_replace($key, $value, $word);
        }
        // var_dump($word);
        return $word;
    }
    function get_email_template($name)
    {
        return $this->getall("email_template", "name = ?", [$name]);
    }



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

    function api_call($service_url, $posts = [], $header = [], $isRaw = false, $method = 'GET',)
    {
        $curl = curl_init($service_url);

        // If there are posts, assume a non-GET method
        if ($this->isJson(($posts)) || count($posts) > 0) {
            $method = "POST";
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Set method-specific options
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                break;
            case 'PATCH':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
            default:
                // For GET and any other methods, do nothing
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
                break;
        }

        // Add POST or PATCH fields if needed
        if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $posts);
        }
        // die(var_dump(cu));
        // Execute request and handle response
        $curl_response = curl_exec($curl);
        curl_close($curl);

        return ($isRaw) ? $curl_response : json_decode($curl_response);
    }


    function newcoin()
    {
        $d = new database;
        $check = $d->checkmessage(["coinID_null", "name", "short_name"]);
        if (!is_array($check)) {
            return false;
        }
        if ($d->getall("coins", "name = ?", [$check['name']], fetch: "") > 0) {
            $d->message("Coin with this name already exit", "error");
            return false;
        }
        $d->quick_insert("coins", $check, message: "Icon added successfully");
        // $icon = $d->process_image(uniqid(), "../upload/icon/", "uploaded_icon");
        // if($icon){
        //     $check['image_icon'] = $icon;
        // $d->quick_insert("coins", "", $check, "Icon added successfully");
        // }
    }
    function autogenerate()
    {
        $data = $this->getcoins();
        foreach ($data as $coin) {
            $_POST['coinID'] = $coin->id;
            $_POST['name'] = $coin->name;
            $_POST['short_name'] = $coin->symbol;
            $this->newcoin();
        }
    }

    function coins_options()
    {
        $coins = $this->getall("coins", fetch: "moredetails");
        if ($coins != "") {
            $options = [];
            foreach ($coins as $row) {
                $id = $row['coinID'];
                // var_dump($id);
                $options[$id] = $row['name'] . ' - ' . $row['short_name'];
            }
        }
        return $options;
    }

    function get_wallet($who = "admin")
    {
        $wallet = $this->getall("wallets", "userID  = ?", [$who], fetch: "moredetails");
        if ($wallet->rowCount() == 0) {
            return [];
        }
        $options = [];
        foreach ($wallet as $row) {
            $id = $row['ID'];
            $coin = $this->getall("coins", 'coinID = ?', [$row['coin_name']]);
            if (!is_array($coin)) {
                continue;
            }
            $options[$id] = $coin['name'] . ' - ' . $coin['short_name'];
        }
        return $options;
    }

    function generate_withdrawal_user(string $date)
    {
        $user = $this->getbotuser();
        // check if userID is not the withdrwal in the past two days
        if ($this->getall("withdraw", "userID = ? and date >= ?", [$user['ID'], date("Y-m-d", strtotime("-3 days", $date))], fetch: "") > 0) {
            return $this->generate_withdrawal_user($date);
        }
        return $user;
    }
    function getbotuser()
    {
        if (rand(1, 2) == 2) {
            $user = $this->getall("users", "acct_type = ? and status =  ? ORDER BY RAND()", ["bot", "active"]);
        } else {
            $user = $this->getall("users", "profile_image != ? and acct_type = ? and status =  ? ORDER BY RAND()", ["", "bot", "active"]);
        }
        return $user;
    }
    function money_format($amount, $currency = 'N')
    {
        $tamount = number_format((float)$amount, 2,);
        $parts = explode(".", $tamount);
        if ($parts[1] == "00") {
            $tamount = number_format((float)$amount);
        }
        return $currency . ' ' . $tamount;
    }

    function date_format($date)
    {
        $date = date_create($date);
        return date_format($date, "D, d M Y h:i:sa");
    }

    function formatDate($date)
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

    function datediffe($largeDate, $smallDate, $format = "h")
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
    function cal_percentage(int | float $no, int | float $total)
    {
        return round(($no * 100) / $total);
    }

    function money_percentage($percentage, $amount)
    {
        return round($amount * ($percentage / 100));
    }

    function calculateProfitPercentage($buyingPrice, $sellingPrice)
    {
        $profitPercentage = (($sellingPrice - $buyingPrice) / $buyingPrice) * 100;
        return $profitPercentage;
    }

    function calculateIncreasedValue($originalValue, $percentageIncrease)
    {
        $percentageIncrease = $percentageIncrease / 100;
        $hold = $originalValue * $percentageIncrease;
        $increasedValue = $originalValue + $hold;
        return $hold;
    }

    function loadpage($url, $isJson = false, $message = "Redirecting...")
    {
        if (!$isJson) {
            echo '<script>window.location.href = "' . $url . '";</script>';
            return;
        }
        return json_encode([
            "message" => ["Success", "$message", "success"],
            "function" => ["loadpage", "data" => [$url, "null"]],
        ]);
    }

    function ago($time)
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

    // short a text

    function short_text($text, $maxCharacters = 30, $justText = false)
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
        $shortenedText = "<p class='m-0' id='$id' $data >$short $function</p>";
        return !$justText  ?  $shortenedText : $short;
    }

    function short_no($no, $maxno = 99)
    {
        if ($no == 0) {
            $no = "";
        }
        if ($no > $maxno) {
            $no = "$maxno+";
        }
        return $no;
    }

    function generateRandomDateTime($startDate = '2022-01-01 09:00:00', $endDate = null)
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


    function addMinutes($datetimeStr, $minutes)
    {
        // Create DateTime object from the input string
        $originalDatetime = new DateTime($datetimeStr);

        // Add the specified number of minutes
        $newDatetime = $originalDatetime->modify("+$minutes minutes");

        // Format the new datetime as desired
        $newDatetimeStr = $newDatetime->format('Y-m-d H:i:s');

        return $newDatetimeStr;
    }


    // tempory user functions below
    protected function credit_debit(
        $userID, $amount, 
        $what = "balance", $action = "credit", 
        $for = "", $forID = "", $narration = "", $request = false, $raw_data = "", $applyReferralBonus = false, $notNegative = true)
    {
        unset($_COOKIE['user_data']);
        // Validate amount
        if (!is_numeric($amount) || $amount < 0) {
            $this->message("Invalid amount provided.", "error");
            return false;
        }
        $amount = round(floatval($amount), 2);
        $user = $this->getall("users", "ID = ?", [$userID], "$what, acct_type");
        if (!is_array($user)) {
            $this->message("Error getting user", "error");
            return false;
        }
        $current = isset($user[$what]) ? round(floatval($user[$what]), 2) : 0.00;
        $status = "success";
        $amount_left = 0;
        $updateUser = true;
         // Prevent operations that would result in negative balances
        switch ($action) {
            case 'credit':
                $newBalance = round($current + $amount, 2);
                break;
            case 'debit':
                if ($current < $amount) {
                    $this->message("Insufficient " . str_replace("_", " ", $what) . ". Your balance: " . $this->money_format($current), "error");
                    if(!$request) {
                        return false;
                    }
                    $updateUser = false;
                    $status = "pending";
                    $amount_left = $amount;
                }
                $newBalance = round($current - $amount, 2);
                // Prevent negative balances
                if ($newBalance < 0 && $notNegative = true) {
                    $this->message("Operation would result in negative balance.", "error");
                    return false;
                }
                break;
            default:
                $this->message("Invalid action type.", "error");
                return false;
        }
        if ($updateUser) {
           $user[$what] = $newBalance;
            $update = $this->update("users", $user, "ID = '$userID'");
            if (!$update) {
                $this->message("We have issue performing this task", "error");
                return null;
            }
        }
        if(is_array($raw_data)) $raw_data = json_encode($raw_data, true);
        $trans = [
            "userID" => $userID,
            "forID" => $forID,
            "trans_for" => $for,
            "action_type" => $action,
            "acct_type" => $what,
            "amount" => $amount,
            "amount_left" => $amount_left ?? 0,
            "current_balance" => $newBalance,
            "narration" => $narration,
            "status" => $status,
            "raw_data"=>$raw_data
        ];
        $this->quick_insert("transactions", $trans);
        if ($applyReferralBonus && $what == "balance") {
            unset($trans['raw_data']);
            $this->applyReferralBonus($userID, $amount, $trans, $action);
        }
        return true;
    }

    protected function applyReferralBonus($userID, $amount, $raw_data = null, $action = "credit")
    {
        $referralSettings = $this->get_settings("referral_settings");
        $referralSettings =json_decode($referralSettings, true) ?? [];
         // Check if referral program is active
        if (!is_array($referralSettings) || (!isset($referralSettings['status']) || $referralSettings['status'] != "on") && $action != "debit") {
            // die("Referral program is not active");
            return false;
        }
       $referral = $this->getall("referrals", "userID = ?", [$userID]);
       if (!is_array($referral) || (!isset($referral['referral_code']) || $referral['referral_code'] == "")) {
            // die("No referral code");   
            return false;
        }
        $referralID = $referral['ID'];
        $referraledUser = $this->getall("users", "referral_code = ?", [$referral['referral_code']]);
        if (!is_array($referraledUser)) {
            return false;
        }
       
        if($referraledUser['total_credit'] < $referralSettings['min_deposit']) {
            return false;
        }
        // add referral bonus to referrer using  $refferraledUser['earnings_percent'] 
        $referrerID = $referraledUser['ID'];
        // use $referralSettings['earnings_percent'] to calculate bounus on the amount and credit the referrer
        $bonusAmount =  ($referralSettings['earnings_percent'] / 100) * $amount;
        $referral['amount_earned'] = ($action == "credit") ? (round(floatval($referral['amount_earned']), 2) + $bonusAmount) : (round(floatval($referral['amount_earned']), 2) - $bonusAmount);
        $referral['last_update'] = date("Y-m-d H:i:s");
        $narration = ($action == "credit") ? "Referral Bonus from $userID" : "Referral Bonus Reversal from $userID";
        $credit = $this->credit_debit($referrerID, $bonusAmount, what: "referral_bonus", action: $action, for: "referral_bonus", forID: $referralID, narration: $narration, raw_data: $raw_data ?? $referral);
        if($credit) {
            $this->message("Referral applied successfully", "success");
            unset($referral['ID']);
            unset($referral['date']);
           return $this->update("referrals", $referral, "ID = '$referralID'");
        }
    }

    function removeRepeatedValuesFromBack($arr)
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

    // data encytion
    function encryptData($data, $secretKey = null)
    {
        if ($secretKey == null && isset($_ENV['DATA_ENCRYPTION_KEY'])) $secretKey = $_ENV['DATA_ENCRYPTION_KEY'];
        if ($secretKey == null) return false;
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encryptedData = openssl_encrypt($data, $method, $secretKey, 0, $iv);
        return base64_encode($iv . $encryptedData);
    }

    function decryptData($encryptedDataWithIv, $secretKey = null)
    {
        if ($secretKey == null && isset($_ENV['DATA_ENCRYPTION_KEY'])) $secretKey = $_ENV['DATA_ENCRYPTION_KEY'];
        if ($secretKey == null) return false;
        $method = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($method);
        $ivWithCiphertext = base64_decode($encryptedDataWithIv);
        $iv = substr($ivWithCiphertext, 0, $ivLength);
        $encryptedData = substr($ivWithCiphertext, $ivLength);

        return openssl_decrypt($encryptedData, $method, $secretKey, 0, $iv);
    }


    // cookies handler

    // Function to set a cookie, can be used for both simple values and arrays
    function setCookieValue($name, $value, $expire = 86400, $path = "/", $domain = "", $secure = false, $httponly = false)
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
    function getCookieValue($name)
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
    function deleteCookie($name, $path = "/", $domain = "")
    {
        setcookie($name, "", time() - 3600, $path, $domain);
    }

    // api functions
    // api functions

    function apiMessage($message, int $code = 400, $data = null)
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
        ]);
    }

    function handleJsonMessage($message)
    {
        // var_dump($message);
        try {
            $message = json_decode($message, true);
            if(isset($message['message']) && $message['status'] = "error") {
                return $this->apiMessage($message['message'], $message['code'] ?? 400);
            }
            if(isset($message['message']) && $message['status'] = "success") {
                return $this->apiMessage($message['message'],  200);
            }
            if (isset($message['message'][2]) && $message['message'][2] == "error") {
                return $this->apiMessage($message['message'][1], 400);
            }

            if (isset($message['message'][2]) && $message['message'][2] == "success") {
                return $this->apiMessage($message['message'][1], 200);
            }           
            return $this->apiMessage("Something went wrong", 400);
        } catch (\Throwable $th) {
            return $this->apiMessage("Something went wrong", 400);
        }
    }

    /** 
     * Get header Authorization
     * */
    function getAuthorizationHeader()
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
