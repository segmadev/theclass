<?php
require_once ROOT . "content/content.php";
require_once ROOT . "functions/users.php";
require_once ROOT . "functions/notifications.php";
require_once ROOT . "pages/profile/ini.php";
define("profile_form", $profile_form);
define("change_password_from", $change_password_from);
class ApiUser extends user
{
    public $userID;
    public $userData;
    public $limit = 10;
    public $notifications;
    // Constructor for ApiUser
    public function __construct()
    {
        // Call parent constructor to set the name
        parent::__construct();
        $this->notifications = new notifications;
        // Additional logic specific to ApiUser
        $token = $this->getBearerToken();
        if ($token == null || empty($token)) {
            die($this->apiMessage("No token passed", 401));
        }
        $user_api_data = $this->get_user_log($token);
        if (!is_array($user_api_data) || !isset($user_api_data['userID'])) {
            die($this->apiMessage("Invaild API Token", 401));
        }
        $this->userID = $user_api_data['userID'];
        $this->userData = $this->user_data($this->userID);
        $userDetails = $this->getall("users", "ID = ?", [$this->userID], select: "ID, first_name, last_name, email, phone_number, balance, referral_bonus, gender, profile_image, total_credit, status") ?? [];
        $this->userData = array_merge($userDetails, $this->userData);
        if (count($this->userData) == 0) {
            die($this->apiMessage("Invaild API Token or account not active", 401));
        }
    }

    function get_notify_no() {
        $note = $this->notifications->unread_no($this->userID) ?? 0;
        return $this->apiMessage("Notification count fetched", 200, ["count" => $note]);
    }

    function get_notifications() {
         $start = (int)htmlspecialchars($_GET['start'] ?? 0);
         $limit = (int)htmlspecialchars($_GET['limit'] ?? $this->limit);
         $limit = ($limit > $this->limit) ? $this->limit : $limit;
         $noties = $this->notifications->get_pending_all_notification($this->userID, $start, $limit);
         return $this->apiMessage("Notifications fetched", 200, data: ["next"=>($noties->rowCount() >= $limit) ? $start + $limit : null, "notifications"=>$noties->fetchAll(PDO::FETCH_ASSOC)]);
    }

    function mark_notification_as_read()
    {
        $id = htmlspecialchars($_GET['ID'] ?? $_GET['id'] ?? "");
        if ($id == "") return $this->apiMessage("No ID passed", 400);
        $notification = $this->getall("notifications", "ID = ? and (userID = ? or userID = ?)", [$id, $this->userID, "all"], fetch: "");
        if ($notification == 0) return $this->apiMessage("Notification not found", 404);
        if (!$this->notifications->seen_message($id, $this->userID)) return $this->apiMessage("Unable to mark notification as read", 400);
        return $this->apiMessage("Notification marked as read", 200);
    }

    function loadDeposit() {
        require_once ROOT.'functions/deposit.php';
    }

    function get_order($isApi = "no", $id = null)
    {
        if ($id == null && !isset($_GET['id'])) {
            die($this->apiMessage("No ID passed", 401));
        }
        $newDBIndex = $this->get_index();
        $id = $id ?? htmlspecialchars($_GET['id'] ?? $_GET['ID']);
        $order = $this->getall("orders", "ID = ? and userID = ?", [$id, $this->userID], newDBIndex: $newDBIndex);
        if (!is_array($order)) die($this->apiMessage("Order not found", 400));
        if ($isApi == "no") return $order;
        return $this->apiMessage("Order fetched", 200, $this->cleanOrder($order));
    }

    function get_orders($start = 0, $limit = 30) {
        $start = (int)$start;
        $limit = (int)$limit;
        $newDBIndex = $this->get_index();
        $orders = $this->getall("orders", "userID = ? ORDER BY date DESC LIMIT $start, $limit", [$this->userID], fetch: "all", newDBIndex: $newDBIndex);
        if ($orders == "") return $this->apiMessage("No orders found", 404);
        return $this->apiMessage("Orders fetched", 200, array_map([$this, "cleanOrder"], $orders->fetchAll(PDO::FETCH_ASSOC)));
    }

    

    function editAccount() {
        $rawData = file_get_contents("php://input");
        // Decode JSON
        $_POST = json_decode($rawData, true);
        $info = $this->update_profile(profile_form, $this->userID);
        if(!is_array($info)) {
            return $this->apiMessage("Fill all required fields", 400);
        }
        return $this->apiMessage("Account updated", 200);
    }

    function update_password() {
        $rawData = file_get_contents("php://input");
        $_POST = json_decode($rawData, true);
        $info = $this->change_password(change_password_from, $this->userID);
        if(!is_array($info)) {
            return $this->apiMessage("Fill all required fields", 400);
        }
        return $this->apiMessage("Password Changed", 200);
    }

    function get_user() {
        $base_url = $_ENV['files_base'] ?? utilities::base_url();
        $profileImage = $this->userData['profile_image'] ?? '';
        $gender = strtolower(trim($this->userData['gender'] ?? ''));
        if (empty($profileImage) || is_null($profileImage)) {
            if (!empty($gender)) {
                $profileImage = $gender . ".jpg";
            } else {
                $profileImage = "default.jpg";
            }
        }
        $this->userData['profile_image'] = $base_url . "/assets/images/profile/" . $profileImage;
        return $this->apiMessage("User fetched", 200, $this->userData);
    }

    
    function logout() {
        $token = $this->getBearerToken();
        $api_data =  $this->getall("user_logs", "token = ?", [$token]);
        if (!is_array($api_data)) {
            return $this->apiMessage("Invalid token", 401);
        }
        if(!$this->delete("user_logs", "token = ?", [$token])) {
            return $this->apiMessage("Error logging you out", 400);
        }
        return $this->apiMessage("Session logout successfully", 200);
    }

  

    function get_user_log($token)
    {
        // Validate token format
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            
            return null;
        }
        $tokenHash = hash('sha256', $token);
        
        // Check API keys first
        $api_data = $this->getall("api_data", "api_key = ? and status = ?", [$tokenHash, 1], iscacheable: true);
        if (!is_array($api_data)) {
            // Check user session tokens
            $api_data = $this->getall("user_logs", "token = ? and expiry_date >= ? and status = ?", [$tokenHash, time(), 1]);
        }
        if (!is_array($api_data)) {
            // Optionally log failed attempt
            error_log("Failed token attempt: $token from IP {$_SERVER['REMOTE_ADDR']}");
            return null;
        }

        // Check user status
        $user = $this->getall("users", "ID = ?", [$api_data['userID']], iscacheable: false);
        if (!is_array($user) || ($user['status'] != 1 && $user['status'] != "active")) {
            // Optionally revoke token
            $this->delete("user_logs", "token = ?", [$tokenHash]);
            return null;
        }

        // Optionally check IP/device binding
        // if ($api_data['ip'] !== $_SERVER['REMOTE_ADDR']) {
        //     // Optionally log and revoke
        //     error_log("Token IP mismatch for user {$api_data['userID']}");
        //     $this->delete("user_logs", "token = ?", [$tokenHash]);
        //     return null;
        // }

        return $api_data;
    }
    function getBalance()
    {
        die($this->apiMessage("Balance fetched", 200, ["balance" => round($this->userData['balance'], 3), "total_credit" => $this->userData['total_credit'], "currency" => "NGN"]));
    }

    function cleanOrder($order)
    {
        if (!is_array($order)) return [];
        $fOrder = [];
        if ($order['order_type'] == "rentals") {
            $fOrder['ID'] = $order['ID'];
            // $fOrder['serviceCode'] = $order['serviceCode'];
            $fOrder['serviceName'] = $order['serviceName'];
            $fOrder['number'] = $order['loginIDs'];
            $fOrder['type'] = $order['type'];
            $fOrder['amount'] = $order['amount'];
            // $fOrder['country'] = $order['country'];
            $fOrder['expiration'] = $order['expiration'];
            $fOrder['expire_date'] = $order['expire_date'];
            $fOrder['status'] = $order['status'];
            $fOrder['date'] = $order['date'];
        }
        if ($order['order_type'] == "account") {
            $fOrder['ID'] = $order['ID'];
            $fOrder['accountID'] = $order['accountID'];
            $fOrder['no_of_orders'] = $order['no_of_orders'];
            $fOrder['loginIDs'] = $order['loginIDs'];
            $fOrder['amount'] = $order['amount'];
            // $fOrder['costAmount'] = $order['cost_amount'];
            $fOrder['date'] = $order['date'];
        }

        return $fOrder;
    }

    function getWallet() {
        $wallet = $this->getall("user_wallets", "userID = ?", [$this->userID]);
        // die(var_dump($this->userID));
        if(!is_array($wallet)) {
            try {
                $this->loadDeposit();
                $depsoit = new deposit;
                $wallet = $depsoit->create_wallet_address(["ID"=>$this->userID]);
                if(is_array($wallet)) $wallet = $this->getall("user_wallets", "userID = ?", [$this->userID]);  
            } catch (\Throwable $th) {
                //throw $th;
                // die($th);
                return $this->apiMessage("Error Creating wallet try again", 500);
            }
        }
        return $this->apiMessage("Wallet Fetched successfully", 200, $wallet);    
        
    }

    // function deleteAccount(){
    //     $this->loadDeposit();
    //     $depsoit = new deposit;
    //     $account = $depsoit->delete_monipfy_reserved_account(htmlspecialchars($_GET['accountID']), false);
    //     var_dump($account); die();
    // }
    function getAccount() {
        $this->loadDeposit();
        $depsoit = new deposit;
        $account = $depsoit->get_account_details($this->userID);
        $count = $account->rowCount();
        if($count  > 0) {
            $account = $account->fetchAll(PDO::FETCH_ASSOC);
        }
        // $count = 0;
        if($count == 0) {
            try {
                // $gateway =  "monipfy";
                $gateway = (rand(0, 1) === 0) ? "paymentpoint" : "monipfy";
                $account = $depsoit->create_account_details($this->userData, $gateway);
                if(!is_array($account)) return $this->apiMessage("Error Creating wallet try again", 500);
                $account = [$account];
            } catch (\Throwable $th) {
                //throw $th;
                // die($th);
                return $this->apiMessage("Error Creating wallet try again", 500);
            }
        }
        return $this->apiMessage("Account Fetched successfully", 200, $account);    
    }

    function get_all_orders(){
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? $this->limit);
        $type = htmlspecialchars($_GET['type'] ?? "account");
        $select = "ID, userID, accountID, loginIDs, amount, no_of_orders, order_type, order_where, date";
        $limit = ($limit > $this->limit) ? $this->limit : $limit;
        $query = "userID = ? and order_type = ?";
        $params = [$this->userID, $type];
        
        if ($start == null || !is_numeric($start)) {
            $start = 0;
        }

        if($type != "rentals" && $type != "account") {
            return $this->apiMessage("No $type found", 404);
        }
        if($type == "rentals") {
            $select = "ID, userID, serviceCode, serviceName, otpCode, loginIDs AS number, amount, order_type, type, expiration, expire_date, activate_expire_date, status, order_where, date";   
        }
    
        if(isset($_GET['date_from']) && $_GET['date_from'] != null) {
            $to =  htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $query .= "AND date >= ? AND date <= ?";
            $params[] = htmlspecialchars($_GET['date_from']);
            $params[] = $to;
        }

        $query .= " ORDER BY date DESC LIMIT $start, $limit";
        $newDBIndex = $this->get_index();
        $orders = $this->getall("orders", $query, $params, fetch: "all", newDBIndex: $newDBIndex);
        if($orders == "") {
            return $this->apiMessage("No $type found", 404);
        }
        return $this->apiMessage("Orders fetched", 200, data: ["next"=>($orders->rowCount() >= $limit) ? $start + $limit : null, "orders"=>$orders->fetchAll(PDO::FETCH_ASSOC)]);

    }

    function genAPIKey() {
        $rawData = file_get_contents("php://input");
        $_POST = json_decode($rawData, true);
        if(!isset($_POST['password']))  return $this->apiMessage("Password required", 400);
        if(!base64_decode($_POST['password'], true))  return $this->apiMessage("Invaild Password format", 400);
        $_POST['password'] = base64_decode($_POST['password'], true);
        $key = $this->generate_api_key($this->userID);
        if($key != "" && $key != false) return $this->apiMessage("API Key generated", 200, data: ["key"=>$key]);
    }
    function getTransactions() {
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? $this->limit);
        $limit = ($limit > $this->limit) ? $this->limit : $limit;
        $query = "userID = ?";
        $params = [$this->userID];
        
        if ($start == null || !is_numeric($start)) {
            $start = 0;
        }

        if(isset($_GET['for']) && $_GET['for'] != null) {
            $query .= "AND trans_for = ? ";
            $params[] = htmlspecialchars($_GET['for']);
        }
        if(isset($_GET['type']) && $_GET['type'] != null) {
            $query .= "AND action_type = ? ";
            $params[] = htmlspecialchars($_GET['type']);
        }

        if(isset($_GET['amount_from']) && $_GET['amount_from'] != null && isset($_GET['amount_to']) && $_GET['amount_to'] != null) {
            $query .= "AND date >= ? AND date <= ?";
            $params[] = htmlspecialchars($_GET['amount_from']);
            $params[] = htmlspecialchars($_GET['amount_to']);
        }

        if(isset($_GET['date_from']) && $_GET['date_from'] != null) {
            $to =  htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $query .= "AND date >= ? AND date <= ?";
            $params[] = htmlspecialchars($_GET['date_from']);
            $params[] = $to;
        }

        $query .= " ORDER BY date DESC LIMIT $start, $limit";
        $newDBIndex = $this->get_index();
        $trans = $this->getall("transactions", $query, $params, fetch: "all", newDBIndex: $newDBIndex);
        if($trans == "") {
            return $this->apiMessage("No transactions found", 404);
        }
        return $this->apiMessage("Transactions fetched", 200, data: ["next"=>($trans->rowCount() >= $limit) ? $start + $limit : null, "transactions"=>$trans->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // transfer from referral to user balanace
    function transferReferral() {
        $amount = $this->userData['referral_bonus'] ?? 0.0;
        // check the min amount that can be transferred on the go
        $min = (int)$this->get_settings("min_withdraw_to_balance") ?? 5000;
        if((int)$amount < $min) {
            return $this->apiMessage("Minimum amount to transfer is $min", 400);
        }
        return $this->transfer_amount($this->userID, $amount, "referral_bonus", "balance");
    }

    // Get all referrals for the authenticated user (with joined user names)
    function getReferrals() {
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? $this->limit);
        $limit = ($limit > $this->limit) ? $this->limit : $limit;
        if ($start == null || !is_numeric($start)) {
            $start = 0;
        }

        $from = "referrals as r JOIN users AS u ON r.userID = u.ID";
        $select = "r.ID, r.userID, r.amount_earned, r.date, u.first_name, u.last_name";

        $where = "r.referral_code = ?";
        $params = [$this->userData['referral_code']];

        // optional date range filter
        if (isset($_GET['date_from']) && $_GET['date_from'] != null) {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND r.date >= ? AND r.date <= ?";
            $params[] = htmlspecialchars($_GET['date_from']);
            $params[] = $to;
        }

        // optional amount range filter
        if (isset($_GET['min_amount']) && isset($_GET['max_amount'])) {
            $where .= " AND r.amount_earned >= ? AND r.amount_earned <= ?";
            $params[] = htmlspecialchars($_GET['min_amount']);
            $params[] = htmlspecialchars($_GET['max_amount']);
        }

        // final query with ordering and pagination
        $query = $where . " ORDER BY r.date DESC LIMIT $start, $limit";
        $newDBIndex = $this->get_index();
        $refs = $this->getall($from, $query, $params, $select, fetch: "all", newDBIndex: $newDBIndex);

        if ($refs == "") {
            return $this->apiMessage("No referrals found", 404);
        }

        return $this->apiMessage("Referrals fetched", 200, data: [
            "next" => ($refs->rowCount() >= $limit) ? $start + $limit : null,
            "referrals" => $refs->fetchAll(PDO::FETCH_ASSOC)
        ]);
    }

    function referral_stats(){
        $where = "referral_code = ?";
        $params = [$this->userData['referral_code']];
        if(isset($_GET['date_from']) && isset($_GET['date_to'])) {
        // optional date filters (YYYY-MM-DD or full datetime)
        $dateFrom = htmlspecialchars($_GET['date_from'] ?? '');
        $dateTo   = htmlspecialchars($_GET['date_to'] ?? '');

        // normalize date_to to include end of day when only a date is provided
        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $dateFrom = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $dateTo = $dateTo . ' 23:59:59';
        }

         // build where clause and params
        if ($dateFrom !== '') {
            $where .= " AND date >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where .= " AND date <= ?";
            $params[] = $dateTo;
        }
     }   
        // get total rows and sum of amount_earned
        $select = "COUNT(*) AS total_referrals, COALESCE(SUM(amount_earned),0) AS total_amount";
         $newDBIndex = $this->get_index();
        $stats = $this->getall("referrals", $where, $params, $select, fetch: "details");
        $total_amount = $this->userData['referral_bonus'] ?? 0.0;
        if (!is_array($stats)) {
            // no rows found -> return zeros
            $data = ["total_referrals" => 0, "total_amount" => 0.0, "referral_balance"=>$total_amount];
        } else {    
            $data = [
                "total_referrals" => (int)($stats['total_referrals'] ?? 0),
                "total_amount" => (float)($stats['total_amount']), 
                "referral_balance"=>$total_amount
            ];
        }
        return $this->apiMessage("Referral stats fetched", 200, $data);
    }

    function checkWhatsapp(){
        if(!isset($_GET['number']) || $_GET['number'] == "") {
            return $this->apiMessage("No number passed", 400);
        }
        $number = preg_replace('/\D/', '', $_GET['number']);
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://wa.me/'.$number,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            ));

            $response = curl_exec($curl);

            // curl_close($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode == 200) {
            if (strpos($response, "invalid") !== false || strpos($response, "not on WhatsApp") !== false) {
              return  $this->apiMessage("Number not on WhatsApp", 400);
            } else {
              return  $this->apiMessage("Number Might be on WhatsApp", 200);
            }
        } else {
            return $this->apiMessage("Unable to verify number", 400);
        }
    }
}