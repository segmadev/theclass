<?php
class user extends content
{
    public $userdata;
    public $userID;
    protected $profile_link_root = PATH . "assets/images/profile/";
    // public function __construct(String $userID = null, array $userdata = []){
    //     if($this->userID == null && isset($_SESSION['userID'])) {
    //         $this->userID = htmlspecialchars($_SESSION['userID']);
    //     }
    //     if($this->userID != null && empty($this->userdata)) {
    //         $data = $this->getall("users", "ID = ?", [$userID]);
    //         if(is_array($data)) {
    //             $this->userdata = $data;
    //         }
    //     }
    // }

    public function send_email_verify() {}

    function get_remark($userID) {
        $user = $this->getall("users", "ID = ?", [$userID]);
        if(!is_array($user) || !$this->opay_access($user)) return '';
        if($user['remark'] == "" || $user['remark'] == null) {
            $user['remark'] = $this->randcar();
            $this->update("users", ["remark"=>$user['remark']], "ID = '".$user['ID']."'");
        }
        return $user['remark'];
    }

    function opay_access($user) {
        if((int)$user['total_credit'] >= 500000) return true;
        return false;
    }

    function manualPayment($userID)
    {
        if (!isset($_POST['session_ID']) || $_POST['session_ID'] == "") return $this->message("Session ID or Transaction ID not passed", "error", "json");
        if ($this->getall("awatingPayment", "userID = ?", [$userID], fetch: "") >= 3) return $this->message("You can't have more than three pending payment please wait for them to be resolved.", "error", "json");
        $sessionID = htmlspecialchars(str_replace(' ', '', $_POST['session_ID']));
        if (strlen($sessionID) < 6) return $this->message("Invaid Input", "error");
        if ($this->getall("awatingPayment", "sessionID = ?", [$sessionID], fetch: "") > 0) return $this->message("session already submited or invald", "error", 'json');
        $this->quick_insert("awatingPayment", ["userID" => $userID, "sessionID" => $sessionID]);
        $payment = $this->manualValueAssign($sessionID);
        if (!$payment) return $this->message("Payment request submitted.", "success", "json");
        $return =  [
            "message" => ["Sucess", $payment['amount'] . " allocated. Redirecting...", "success"],
            "function" => ["loadpage", "data" => ["index?p=deposit&action=opay", "success"]],
        ];
        return json_encode($return);
    }

    function removeAwait($userID)
    {
        if (!isset($_POST['awaitID']) || $_POST['awaitID'] == "") return $this->message("No ID Passed", "error", "json");
        $awaitID = htmlspecialchars($_POST['awaitID']);
        if (!$this->delete("awatingPayment", "ID =? and userID = ?", [$awaitID, $userID])) return $this->message("Unable to remove this request. Refresh page and try again.", "error", "json");
        $return = [
            "message" => ["Sucess", "Payment request removed. Redirecting...", "success"],
            "function" => ["loadpage", "data" => ["index?p=deposit&action=opay", "success"]],
        ];
        return json_encode($return);
    }
    protected function manualValueAssign($sessionID = null, $pay = null)
    {
        if ($sessionID == null and $pay == null) return false;
        $pay = (is_array($pay)) ? $pay : $this->getall("opaypayment", "sessionID  = ? or transactionID = ?", [$sessionID, $sessionID]);
        if (!is_array($pay)) return false; 
        $awaiting = $this->getall("awatingPayment", "sessionID = ? or sessionID = ?", [$pay['sessionID'], $pay['transactionID']]);
        if (!is_array($awaiting) && (!isset($pay['remark']) && $pay['remark'] == '')) return false;
        if(!is_array($awaiting)){
            $user = $this->getall("users", "remark = ?", [trim($pay['remark'])]); 
            if(!is_array($user)) return false;
            $awaiting['sessionID'] = ($pay['transactionID'] != "") ? $pay['transactionID'] : $pay['sessionID'];
            $awaiting['userID'] = $user['ID'];
        }
        
         
        if ($pay['status'] == 0 || is_array($this->getall("transactions", "(forID = ? or forID = ?) and (forID IS NOT NULL or forID != ?)", [$pay['transactionID'], $pay['sessionID'], ""]))) {
            $this->dispose_payment($pay['ID'], $awaiting['ID']);
            die($this->message("This payment is already assigned or not active.", "error"));
        }
        $this->credit_debit($awaiting['userID'], $pay['amount'],  for: "opaypayment", forID: $awaiting['sessionID']);
        $this->dispose_payment($pay['ID'], $awaiting['ID'] ?? null);
        return $pay;
    }  

    function dispose_payment($payID, $awaitID = null)
    {
        $this->update("opaypayment", ["status" => 0], "ID = '$payID'");
        if ($awaitID != null) $this->delete("awatingPayment", "ID = ?", [$awaitID]);
        return true;
    }

    public function create_vitual_account($userID)
    {
        $user = $this->getall("users", "ID = ?", [$userID]);
        if (!is_array($user)) {
            return false;
        }
        $end_point = "https://api.flutterwave.com/v3/virtual-account-numbers";
        $info = ["email" => $user['email'], "is_permanent" => false, "tx_ref" => "REF-" . $user['ID'], "phonenumber" => $user['phone_number'], "firstname" => $user['first_name'], "lastname" => $user['last_name'], "narration" => $user['first_name'] . " " . $user['last_name']];
        $header = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->get_settings("flutterwave_secret_key")
        ];
        $call_api = $this->api_call($end_point, $info, $header);
        var_dump($call_api);
    }

    public function generate_api_key($userID)
    {
        if (!isset($_POST['password']) || $_POST['password'] == "") return $this->message("Enter your password", "error");
        $user = $this->getall("users", "ID = ?", [$userID], "password");
        if (!is_array($user)) return $this->message("Can not identify user", "error");
        if (!password_verify(htmlspecialchars(trim($_POST['password'])), $user['password'])) return $this->message("Invaild Password", "error");
        $keys = $this->generateUniqueIdAndKey(54);
        if ($this->is_api_key($userID)) {
            $update = $this->update("api_data", ["api_key" => $keys, "last_updated" => date("Y-m-d H:i:s")], "userID = '$userID'");
        } else {
            $update = $this->quick_insert("api_data", ["userID" => $userID, "api_key" => $keys, "last_updated" => date("Y-m-d H:i:s")]);
        }
        if ($update) {
            return  (defined('ISAPI') && ISAPI == true) ? $keys : "<h5 class='m-3'>API KEY: $keys</h5>";
        }
    }

    public function is_api_key($userID)
    {
        if (is_array($this->getall("api_data", "userID = ?", [$userID]))) return true;
        return false;
    }

    /**
     * Generate a unique ID and a random key of a specified length.
     *
     * @param int $keyLength The length of the random key. Defaults to 16.
     * @return array Associative array containing 'uniqueId' and 'randomKey'.
     * @throws Exception If the length is not a positive even number.
     */
    function generateUniqueIdAndKey($keyLength = 16)
    {
        if ($keyLength <= 0 || $keyLength % 2 !== 0) {
            throw new Exception("Key length must be a positive even number.");
        }

        // $uniqueId = uniqid('', true);                 // Generate a unique ID
        $randomKey = bin2hex(random_bytes($keyLength / 2)); // Generate a random key of the specified length
        return $randomKey;
    }
    public function get_all_emails()
    {
        // SELECT GROUP_CONCAT(email SEPARATOR ',') AS all_emails FROM table_name;
        return $this->getall("users", "acct_type = ?", ["user"], "GROUP_CONCAT(email SEPARATOR ', ') AS all_emails")['all_emails'];
    }
    public function get_profile_icon_link($userID = null, $what = "users")
    {
        $gender = "default";
        $user = $this->getall("$what", "ID  = ?", [$userID], "profile_image", "details");
        if (isset($user["profile_image"]) && $user["profile_image"] != null) {
            if (str_contains($user["profile_image"], "https") || str_contains($user["profile_image"], "http")) {
                return $user["profile_image"];
            }
            return $this->profile_link_root . $user["profile_image"];
        }

        if ($userID == null) {
            return $this->profile_link_root . "default.jpg";
        }

        if (isset($user['gender']) && $user['gender'] != "" | null) {
            $gender = $user['gender'];
        } elseif ($what == "users") {
            $check = $this->getall("users", "ID = ?", [$userID], "gender");
            if (isset($check['gender'])) {
                $gender = $check['gender'];
            }
        }

        return $this->profile_link_root . lcfirst($gender) . ".jpg";
    }

    public function change_password($data, $userID)
    {
        if (!is_array($data)) {
            return null;
        }
        $info = $this->validate_form($data);
        if (!is_array($info)) {
            return null;
        }
        $user = $this->getall("users", "ID = ?", [$userID], "password", "details");
        if (isset($user['password']) && password_verify($info['current_password'], $user['password'])) {
            $update = $this->update("users", ["password" => password_hash($info['password'], PASSWORD_DEFAULT)], "ID = '$userID'", "Password Changed.");
            if ($update) {
                $actInfo = ["userID" => $userID, "date_time" => date("Y-m-d H:i:s"), "action_name" => "Password changed", "description" => "Your password was changed."];
                $this->new_activity($actInfo);
            }
        } else {
            $this->message('Current password incorrect', 'error');
        }
    }

    public function update_profile($data, $userID)
    {

        if(isset($data['email'])) unset($data['email']);
        $info = $this->validate_form($data);
        if (!is_array($info)) {
            return null;
        }
        $user = $this->getall("users", "ID = ?", [$userID], fetch: "details");
        if (!is_array($user)) {
            $this->message("User ot found", "error");
            return null;
        }
        if ($user['email'] != $info['email']) {
            if ($this->getall("users", "email = ?", [$info['email']]) > 0) {
                $this->message("User with email alrady exit", "error");
                return null;
            }
            $info['email_verify'] = 0;
        }
        unlink($info['email']);
        // check phone number
        // make sure phone number is 11 digits
        if (isset($info['phone_number']) && !$this->is_valid_phone_number($info['phone_number'])) {
            $this->message("Phone number must be 11 digits", "error");
            return null;
        }
        if ($this->getall("users", "ID != ? and phone_number = ?", [$userID, $info['phone_number']]) > 0) {
            $this->message("User with phone number alrady exit", "error");
            return null;
        }
        if ($this->update("users", $info, "ID = '$userID'")) {
            $actInfo = ["userID" => $userID, "date_time" => date("Y-m-d H:i:s"), "action_name" => "Account Update", "description" => "Your account got updated."];
            $this->new_activity($actInfo);
            if (defined('ISAPI') && ISAPI == true)  { die($this->apiMessage("Account updated", 200, $info));}
            return $this->message("Account updated", "success");
        }
    }

    function update_phone_number($userID) {
        if(!isset($_POST['phone_number']) || $_POST['phone_number'] == "") return $this->message("Phone number not passed", "error");
        $phone = htmlspecialchars($_POST['phone_number']);
        // make sure the length is 11 digits
        if(!$this->is_valid_phone_number($phone)) return $this->message("Phone number must be 11 digits", "error");
        if($this->getall("users", "ID != ? and phone_number = ?", [$userID, $phone]) > 0) return $this->message("Phone number already in use", "error");
        $update = $this->update("users", ["phone_number"=>$phone], "ID = '$userID'");
        if($update){
            $return = [
                "message" => ["Success", "Phone number updated", "success"],
                "function" => ["loadpage", "data" => ["index?p=deposit", "null"]],
            ];
            return json_encode($return);
            }
        $return = [
            "message" => ["Success", "Fund Added", "success"],
            "function" => ["loadpage", "data" => ["index?p=investment", "null"]],
        ];
        return json_encode($return);
        return $this->message("Unable to update phone number", "error");
        
    }

    public function upload_kyc($data, $userID)
    {
        $_POST['kyc_status'] = "pending";
        $user = $this->validate_form($data);
        if (!is_array($user)) {
            return false;
        }
        $update = $this->update("users", $user, "ID = '$userID'", "KYC submitted for verification. You can still upload a new ID within the verification period.");
        if (!$update) {
            return null;
        }
        $actInfo = ["userID" => $userID, "date_time" => date("Y-m-d H:i:s"), "action_name" => "KYC Upload", "description" => "Upload of ID for KYC verification."];
        $this->new_activity($actInfo);
    }
    public function change_profile_pic($userID)
    {
        $from = [
            "profile_image" => ["input_type" => "file", "file_name" => $userID, "path" => "assets/images/profile/"],
        ];
        $info = $this->validate_form($from);
        $update = $this->update("users", $info, "ID = '$userID'");
        if (!$update) {
            return null;
        }
        $actInfo = ["userID" => $userID, "date_time" => date("Y-m-d H:i:s"), "action_name" => "Change profile picture", "description" => "Profile picture changed."];
        $this->new_activity($actInfo);
        $return = [
            "message" => ["Sucess", "Profile Updated", "success"],
        ];
        return json_encode($return);
    }



    protected function transfer_amount($userID, $amount, $from, $to) {
            if($amount < 1000) return $this->message("Minimum transfer amount is 1000", "error");
            // check the from for the user
            $debit = $this->credit_debit($userID, $amount, $from, "debit", "Transfer", $userID, "Transfer from $from to $to" );
            if(!$debit) return $this->message("Unable to transfer amount", "error");
            // credit the amount to the to
            $credit = $this->credit_debit($userID, $amount, $to, "credit", "Transfer", $userID, "Transfer from $from to $to" );
            if(!$credit) {
                // reverse the debit
                $this->credit_debit($userID, $amount, $from, "credit", "Transfer Reversal", $userID, "Transfer Reversal from $from to $to" );
                return $this->message("Unable to transfer amount", "error");
            }
            return $this->message("Transfer successful", "success");
    }

    public function first_trading($userID)
    {
        return $this->getall("investment", "userID = ? order by date ASC", [$userID], fetch: 'details');
    }

    public function update_last_seen($userID, $time)
    {
        if ($this->getall("users", "ID = ?", [$userID], fetch: "") > 0) {
            if ($this->update("users", ["last_seen" => $time], "ID  = '$userID'")) {
                return true;
            }
            return false;
        }
        return false;
    }
    public function last_from_trading_balance($date, $userID)
    {
        if ($this->get_settings("last_trading_transfer", who: $userID) != "") {
            $update = $this->update("settings", ["meta_value" => $date], "meta_name = 'last_trading_transfer' and meta_for = '$userID'");
            if ($update) {
                return true;
            }
            return false;
        }

        $insert = $this->quick_insert("settings", ["meta_name" => "last_trading_transfer", "meta_value" => $date, "meta_for" => $userID]);
        if ($insert) {
            return true;
        }
        return false;
    }

    public function getUserStage($userID)
    {
        $user = $this->getall("users", "ID = ?", [$userID]);
        if (!is_array($user)) return $this->getStage(0);
        return $this->getStage((int)$user['total_credit']);
    }
    // public function getStage($amount) {
    //     // JSON data
    //     $jsonData = $this->get_settings("discounts");
    //         // Decode the JSON into an associative array
    //     $stages = json_decode($jsonData, true);

    //     // Extract the totalCredit values from all stages
    //     $totalCredits = array_column($stages, 'totalCredit');

    //     // Check if the amount is less than the totalCredit of stage1
    //     if ($amount < $totalCredits[0]) {
    //         return [];
    //     }

    //     // Find the minimum totalCredit that is greater than or equal to the amount
    //     $matchedIndex = array_search(min(array_filter($totalCredits, fn($credit) => $amount <= $credit)), $totalCredits);

    //     // Return the matching stage or a message if no match found
    //     $userStage =  $matchedIndex !== false ? $stages[array_keys($stages)[$matchedIndex]] : $stages[ array_keys($stages)[count($stages) - 1]];
    //     $userStage['position'] = $matchedIndex  !== false ? $matchedIndex +1 : count($stages);
    //     return $userStage;
    // }

    public function user_data($userID)
    {
        if (isset($_COOKIE['user_data'])) {
            // echo "Cookies here";
            // return  unserialize($_COOKIE['user_data']);
        }
        // amount_invest number_invet profit_percent lost_percent profit_amount lost_amount trade_balance trade_bonus balance
        $info = [];
        $user = $this->getall("users", "ID = ?", [$userID], "total_credit, SUM(balance) as balance, referral_bonus");
        if(is_array($user)) $user['referral_code'] = $this->get_refferal_code($userID);
        $info = array_merge($info, $user);
        $stage = $this->getStage($user['total_credit']);
        $info = array_merge($info, $stage);
        // var_dump();
        // array_merge($info, );
        // try {
        //     setcookie("user_data",serialize($info), time()+30*60);
        // } catch (\Throwable $th) {
        //     //throw $th;
        // }
        return $info;
    }

    function get_refferal_code($userID) {
        $user = $this->getall("users", "ID = ?", [$userID], "referral_code");
        if(is_array($user) && isset($user['referral_code']) && $user['referral_code'] != null) {
            return $user['referral_code'];
        }
        $code = strtoupper($this->randcar(6));
        if($this->getall("users", "referral_code = ?", [$code], fetch: "") > 0) {
            return $this->get_refferal_code($userID);
        }
        $this->update("users", ["referral_code"=>$code], "ID = '$userID'");
        return $code;
    }
    public function is_valid_phone_number($phoneNumber) {
        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', $phoneNumber);
    
        // Optional leading + followed by 10 to 15 digits
        return preg_match('/^\+?\d{11}$/', $cleaned);
    }
    public function increaseUserTotalCredit($amount, $userID)
    {
        $this->update("user", ["total_credit" => $amount], "ID = '$userID'");
    }

    public function show_balance($data = null, $showBtn = true)
    {
        if (!is_array($data) && $data != null) {
            $data = $this->user_data($data);
        }
        if (is_array($data)) {
            $balance = $this->money_format($data['balance'], currency);
            $referral_bonus = $this->money_format($data['referral_bonus'], currency);
            $btnString = "<div class='card bg-primary p-2 text-white'><b>Balance: </b><p class='h2 text-white m-0' ><b>$balance</b></p>
            <p><small><b>Referral bonus:</b> $referral_bonus<small></p>
            <p>";
            if ($showBtn) $btnString .= "<a href='index?p=deposit' class='btn btn-sm btn-light-success'>Deposit</a>";
            $btnString .= "</p></div>";
            return $btnString;
        }
        return null;
    }

    public function new_wallet($data)
    {
        $wallet = $this->validate_form($data);
        if (!is_array($wallet)) {
            return null;
        }
    }

    public function profile_picture_default($userID = null)
    {
        $profile_picture_link = $this->get_profile_icon_link($userID);
        if (!file_exists(($profile_picture_link))) {
            return "";
        }
        return "<a class='nav-link pe-0' href='javascript:void(0)' id='drop1' data-bs-toggle='dropdown' aria-expanded='false'>
        <div class='d-flex align-items-center'>
          <div class='user-profile-img'>
            <img src='" . $profile_picture_link . "' class='rounded-circle' width='35' height='35' alt='User Profile picture' />
          </div>
        </div>
      </a>";
    }

    public function newuser($data)
    {
        $_POST['ID'] = uniqid();
        $info = $this->validate_form($data);
        if (!is_array($info)) {
            return null;
        }
        $check = $this->getall("users", "email = ? or phone_number = ?", [$info['email'], $info['phone_number']]);
        if ($check > 0) {
            echo $this->message("User with email or phone number alrady exit.", "error");
            return null;
        }

        $info['password'] = password_hash($info['password'], PASSWORD_DEFAULT);
        unset($info['confrim_password']);

        $this->quick_insert("users", $info, message: "Accont created successfully please login");
    }

    public function edituser($data, $userID = 0)
    {
        $info = $this->validate_form($data);
        if (!is_array($info)) {
            return null;
        }
        $check = $this->getall("users", "ID != ? and email = ? and phone_number = ?", [$info['ID'], $info['email'], $info['phone_number']]);
        if ($check > 0) {
            echo $this->message("User with email or phone number alrady exit.", "error");
            return null;
        }

        if (!isset($_SESSION['adminsession']) && $userID != $info['ID']) {
            echo $this->message("You can not perform this action", "error");
            return null;
        }
        unset($info['ID']);
        $id = $info['ID'];
        $update = $this->update("users", $info, "ID = '$id'");
        if ($update) {
            echo $this->message("Account update successfully", "success");
        }
    }
    public function get_profile() {}

    public function fetchusers($start = '0', $limit = 10, $id = null)
    {
        if ($id !== null) {
            $data = $this->getall("users", "ID = ? LIMIT $start, $limit", data: [$id], fetch: "moredetails");
        } else {
            $data = $this->getall("users", "LIMIT $start, $limit", fetch: "moredetails");
        }
        if (is_array($data) || $data != "") {
            return $data;
        }
        return null;
    }

    public function short_user_table($user, $url = "javascript:void(0)", $target="", $showBalance = false)
    {
        if (!is_array($user) && $user != "") {
            $user = $this->getall("users", "ID = ?", [$user]);
        }
        if (!is_array($user)) {
            return "<b class='text-danger'>User Not Found</b>";
        }
        $balance_details = ($showBalance) ? "<span class='fs-2 text-body-color d-block'>Balance: " . $this->money_format($user['balance'], currency) . "</span> 
                    <span class='fs-2 text-body-color d-block'>Referral Bonus: " . $this->money_format($user['referral_bonus'], currency). "</span> " : "";
        return "<li>
            <a href='$url' target='$target' onclick='display_content(this);' class='p-3 bg-hover-light-black d-flex align-items-center chat-user' id='chat_user_" . $user['ID'] . "'  data-user-id='" . $user['ID'] . "'>
                <span class='position-relative'>
                    <img src='" . $this->get_profile_icon_link($user['ID']) . "' alt='user-4' width='40' height='40' class='rounded-circle'>
                </span>
                <div class='ms-6 d-inline-block w-75'>
                    <h6 class='mb-1 fw-semibold chat-title' data-username='" . $this->get_full_name($user) . "'>" . $this->get_full_name($user) . " </h6>
                    $balance_details
                </div>
            </a>
        </li>";
    }
    public function get_full_name($user)
    {
        if (!is_array($user)) {
            return "Unknown";
        }
        return str_replace("�", " ", $user['first_name'] . ' ' . $user['last_name']);
    }

    public function get_name($id, $what = "users", $type = false)
    {
        switch ($what) {
            case 'users':
                $data = $this->getall("users", "ID = ?", [$id], "first_name, last_name, acct_type");
                $name = $this->get_full_name($data);
                if ($type) {
                    $name .= " - " . $data['acct_type'];
                }
                return $name;
                break;
            case 'groups':
                $data = $this->getall("groups", "ID = ?", [$id], "name");
                if (!is_array($data)) {
                    return "Unknown";
                }
                return $data['name'];
                break;
            default:
                return "Unknown";
                break;
        }
    }

    public function create_default_group_chat($chat_from, $userID)
    {
        $groups = $this->getall("groups", "users = ?", ["all"], fetch: "moredetails");
        //    var_dump($groups->rowCount());
        if ($groups->rowCount() == 0) {
            return true;
        }
        foreach ($groups as $row) {
            if ($this->getall("chat", "user1 = ? and user2 = ?", [$userID, $row['ID']], fetch: "") > 0) {
                continue;
            }
            $_POST['user1'] = $userID;
            $_POST['user2'] = $row['ID'];
            $_POST['is_group'] = "yes";
            $this->create_chat($chat_from);
        }
    }

    public function insert_default_message($userID, $receiverID, $message = "", $is_group = "yes", $time = 0)
    {
        if ($this->getall("message", "senderID = ? and receiverID = ?", [$userID, $receiverID], fetch: "") > 0) {
            return true;
        }
        $chat = $this->getall("chat", "user1 = ? and user2 = ?", [$userID, $receiverID], "ID");
        if (!is_array($chat)) {
            return false;
        }
        $this->quick_insert("message", ["chatID" => $chat['ID'], "senderID" => $userID, "receiverID" => $receiverID, "message" => $message, "is_group" => "$is_group", "time_sent" => $time]);
    }
    public function create_chat($chat_from)
    {
        return $this->validate_form($chat_from, "chat", "insert");
    }

    public function new_user_chat($userID, $user2, $chat_from, $r = true)
    {
        $check = $this->getall("chat", "user1 = ? and user2 = ?", [$userID, $user2]);
        if (is_array($check)) {
            if ($r) {
                $this->loadpage('index?p=chat&id=' . $check['ID']);
            }
            return;
        }
        $check = $this->getall("chat", "user1 = ? and user2 = ?", [$user2, $userID]);
        if (is_array($check)) {
            if ($r) {
                $this->loadpage('index?p=chat&id=' . $check['ID']);
            }
            return;
        }

        if (!$this->getall("users", "ID = ?", [$user2], fetch: "")) {
            return false;
        }
        $_POST['user1'] = $userID;
        $_POST['user2'] = $user2;
        if ($user2 == "admin") {
            $_POST['user2'] = $userID;
            $_POST['user1'] = $user2;
        }
        $_POST['is_group'] = "no";
        if ($this->create_chat($chat_from)) {

            if ($user2 == "admin") {

                $this->insert_default_message("admin", $userID, $this->get_settings("default_support_welcome_message"), "no", time());
            }
            if ($r) {
                $this->new_user_chat($userID, $user2, $chat_from);
            }
        }
    }

    public function get_all_chat_notification($userID) {}

    public function activate_referral($userID, $referralID)
    {
        $data = [
            'ID' => ["input_type" => "number"],
            "userID" => [],
            "referralID" => [],
            "referral_code" => [],
            "investID" => ["is_required" => false],
            "status" => [],
            "input_data" => ["investID" => ""],
        ];
        $this->create_table('referrals', $data);
        unset($data['ID']);
        unset($data['investID']);
        $_POST['userID'] = $userID;
        $_POST['referralID'] = $referralID;
        $_POST['referral_code'] = $this->genrate_code();
        $_POST['status'] = "active";
        $data = $this->validate_form($data);
        // valiadte is the exact plan not active
        if (!is_array($data)) {
            return false;
        }
        if ($this->getall(
            "referrals",
            "userID = ? and referralID = ? and status = ?",
            [$data['userID'], $data['referralID'], "active"],
            fetch: ""
        ) > 0) {
            $this->message("This referral is currently active for you.", "error");
            return false;
        }
        if (!$this->quick_insert("referrals", $data, "Referral Activated")) {
            return false;
        }
        return true;
    }

    public function get_ref_info($code)
    {
        // no of active ref
        // no of pending
        // percentage
        // get referralID from referral where referral_code equal code
        // get referral program details through the ID
        // check percentage of active_ref againts the no_of_users in referral_program
        $info = ["no_allocated" => 0, "no_pending" => 0, "percentage" => 0, "no_of_users" => 0];
        $info['no_allocated'] = $this->getall("referral_allocation", "referral_code = ? and status = ?", [$code, "allocated"], fetch: "");
        $info['no_pending'] = $this->getall("referral_allocation", "referral_code = ? and status = ?", [$code, "pending"], fetch: "");
        $refID = $this->getall("referrals", "referral_code = ?", [$code], "referralID");
        if (!is_array($refID)) {
            return $info;
        }
        $ref_program = $this->getall("referral_programs", "ID = ?", [$refID['referralID']], "no_of_users");
        if (!is_array($ref_program)) {
            return $info;
        }
        $info['no_of_users'] = $ref_program['no_of_users'];
        $info['percentage'] = $this->cal_percentage($info['no_allocated'], $ref_program['no_of_users']);
        return $info;
        // $info = ["no_allocated", "no_pending", "percentage"]
    }

    public function genrate_code()
    {
        $code = $this->RandomCode($length = 5);
        if ($this->getall("referrals", "referral_code = ?", [$code], fetch: "") > 0) {
            $this->genrate_code();
        } else {
            return $code;
        }
    }

    public function RandomCode($length = 5)
    {
        $code = '';
        $total = 0;

        do {
            if (rand(0, 1) == 0) {
                $code .= chr(rand(97, 122)); // ASCII code from **a(97)** to **z(122)**
            } else {
                $code .= rand(0, 9); // Numbers!!
            }
            $total++;
        } while ($total < $length);

        return strtoupper($code);
    }

    function displayProfile($userID, $stage = 0)
    {
        echo '
            <div class="profile linear-gradient-bage1 d-flex align-items-center justify-content-center rounded-circle m-2">
                            <div class="border border-3 border-white d-flex align-items-center justify-content-center rounded-circle overflow-hidden" style="width: 45px; height: 45px; z-index: 1" ;="">
                            <img src="' . $this->get_profile_icon_link($userID) . '" alt="" class="w-100 h-100">
                            </div>
                        ' . $this->stagesBadge($stage) . '
                        </div>';
    }

    function getStage($amount)
    {
        // Decode JSON string into an associative array
        $stages = json_decode($this->get_settings("discounts"), true);
        if (!$stages) {
            return [];
        }

        // Sort the stages by totalCredit to ensure correct order
        usort($stages, fn($a, $b) => $a['totalCredit'] <=> $b['totalCredit']);

        $currentStage = null;
        $nextStage = null;
        // Find the current and next stage based on the amount
        foreach ($stages as $index => $stage) {
            if ($amount >= $stage['totalCredit']) {
                $currentStage = $stage;
                $currentStage['position'] =  $index + 1;
                $nextStage = $stages[$index + 1] ?? null;
            }
        }

        // If the user is in the last stage, return 100% progress
        if (!$nextStage) {
            return [
                'percentage' => 100,
                'amountRemaining' => 0,
                'nextStage' => $nextStage,
                'stage' => $currentStage,
                'isLastStage' => true,
            ];
        }

        // Calculate the remaining amount to reach the next stage
        $amountRemaining = $nextStage['totalCredit'] - $amount;

        // Calculate the progress percentage towards the next stage
        $progress = ($amount - $currentStage['totalCredit']) /
            ($nextStage['totalCredit'] - $currentStage['totalCredit']) * 100;

        return [
            'percentage' => round($progress, 2),
            'amountRemaining' => $amountRemaining,
            'stage' => $currentStage,
            'nextStage' => $nextStage['name'],
            'isLastStage' => false,
        ];
    }
    function stagesBadge($stage)
    {
        // $stage = 4;
        switch ($stage) {
            case 1:
                return '<div class="animated_badge animated_badge_1">
            <svg class="animated_badge_svg" width="75" height="100" viewBox="0 0 75 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                
                <circle class="badge_circle" cx="37.5" cy="37.5" r="33.5" fill="#211502" stroke="#993e06" stroke-width="8" />
                
                <path class="badge_number" 
      d="M36 30C32.69 30 30 32.69 30 36C30 39.31 32.69 42 36 42C39.31 42 42 39.31 42 36C42 32.69 39.31 30 36 30ZM36 40C33.79 40 32 38.21 32 36C32 33.79 33.79 32 36 32C38.21 32 40 33.79 40 36C40 38.21 38.21 40 36 40Z" 
      fill="white">
</path>


                
            </svg>
        </div>';
            case 2:
                return '<div class="animated_badge animated_badge_1">
            <svg class="animated_badge_svg" width="75" height="100" viewBox="0 0 75 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="badge_ribbon left"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#A5ACB9" />
                <path class="badge_ribbon left"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#A5ACB9" />
                <path class="badge_ribbon left"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="url(#paint0_linear)" />
                <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="16" y="57" width="44" height="43">
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="#999999" />
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="url(#paint1_linear)" />
                </mask>
                <g class="badge_ribbon left" mask="url(#mask0)">
                    <rect x="30" y="60" width="15" height="40" fill="#EAEDF2" />
                </g>
                <circle class="badge_circle" cx="37.5" cy="37.5" r="33.5" fill="#6b4c1d" stroke="#facc87" stroke-width="8" />
                <path class="badge_number" d="M36.272 45H38.772V30.7H36.572C36.332 32.28 35.292 32.82 32.832 32.88V34.78H36.272V45Z" fill="white" />
                <defs>
                    <linearGradient id="paint0_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#616468" />
                        <stop offset="1" stop-color="#facc87" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint1_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>
        </div>';

            case 3:
                return '
                <div class="animated_badge animated_badge_2 hide">
            <svg class="animated_badge_svg" width="75" height="100" viewBox="0 0 75 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#71A1F4" />
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#71A1F4" />
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="url(#paint0_linear)" />
                <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="16" y="57" width="44" height="43">
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="#71A1F4" />
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="url(#paint1_linear)" />
                </mask>
                <g class="badge_ribbon" mask="url(#mask0)">
                    <rect x="30" y="60" width="15" height="40" fill="#ABC7F9" />
                </g>
                <circle class="badge_circle" cx="37.5" cy="37.5" r="33.5" fill="#71A1F4" stroke="#CEDCF5" stroke-width="8" />
                <path class="badge_number"
                    d="M41.1783 45V42.86H35.0183C37.2183 40.82 41.1783 38.44 41.1783 34.8C41.1783 32.3 39.3983 30.42 36.4383 30.42C33.3583 30.42 31.6983 32.54 31.6983 35.14C31.6983 35.18 31.7183 35.22 31.7183 35.28H34.0983C34.1183 33.76 34.8383 32.62 36.4383 32.62C37.8183 32.62 38.6383 33.52 38.6383 34.86C38.6383 36.22 37.8583 37.38 36.0583 39.04C34.3783 40.62 31.8183 42.92 31.8183 42.92V45H41.1783Z"
                    fill="white" />
                <defs>
                    <linearGradient id="paint0_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint1_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>
        </div>';
                # code...

            case 4:
                return ' <div class="animated_badge animated_badge_3 hide">
            
            <svg class="animated_badge_svg" width="75" height="100" viewBox="0 0 75 100" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#71A1F4" />
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="#71A1F4" />
                <path class="badge_ribbon"
                    d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                    fill="url(#paint0_linear)" />
                <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="16" y="57" width="44" height="43">
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="#71A1F4" />
                    <path
                        d="M16 86.7506V62C16 59.2386 18.2386 57 21 57H55C57.7614 57 60 59.2386 60 62V86.8112C60 88.7561 58.8722 90.5246 57.1087 91.3448L40.5616 99.0411C39.2517 99.6504 37.7424 99.6633 36.4223 99.0766L18.9693 91.3197C17.1637 90.5172 16 88.7266 16 86.7506Z"
                        fill="url(#paint1_linear)" />
                </mask>
                <g class="badge_ribbon" mask="url(#mask0)">
                    <rect x="23" y="60" width="8" height="40" fill="#ABC7F9" />
                    <rect x="43" y="60" width="8" height="40" fill="#ABC7F9" />
                </g>
                <circle cx="37.5" cy="37.5" r="33.5" fill="#C0C6CF" stroke="#C0C6CF" stroke-width="8" />
                <circle class="badge_circle" cx="37.5" cy="37.5" r="29.5" fill="#C0C6CF" stroke="#EAEDF2" stroke-width="4" />
                <path class="badge_number"
                    d="M32.72 40.88C32.9 43.36 34.62 45.26 37.64 45.26C40.72 45.26 42.66 43.42 42.66 40.78C42.66 39.02 41.72 37.68 40.02 37.12C41.16 36.52 41.94 35.5 41.94 34.16C41.94 31.94 40.28 30.42 37.7 30.42C35.12 30.42 33.48 32.04 33.42 34.26H35.72C35.84 33.14 36.6 32.5 37.64 32.5C38.64 32.5 39.46 33.06 39.48 34.24C39.48 35.76 38.24 36.24 36.66 36.24V38.24H37.22C38.86 38.24 40.14 38.96 40.14 40.7C40.14 42.06 39.2 43.16 37.7 43.16C36.14 43.16 35.32 42.24 35.16 40.88H32.72Z"
                    fill="white" />
                <defs>
                    <linearGradient id="paint0_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint1_linear" x1="38" y1="57" x2="38" y2="89.5" gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>
        </div>
    ';

            case 5:
                return '  <div class="animated_badge animated_badge_4 hide">
            
            <svg class="animated_badge_svg" width="75" height="98" viewBox="0 0 75 98" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="badge_ribbon right"
                    d="M18 96.2589L18 60C18 59.4477 18.4477 59 19 59H57.3843C57.9366 59 58.3843 59.4477 58.3843 60V96.2334C58.3843 96.9469 57.6586 97.4308 56.9999 97.1566L39.0269 89.6734C38.7859 89.573 38.5153 89.5709 38.2728 89.6674L19.3697 97.188C18.7131 97.4493 18 96.9656 18 96.2589Z"
                    fill="#FCC838" />
                <path class="badge_ribbon right"
                    d="M18 96.2589L18 60C18 59.4477 18.4477 59 19 59H57.3843C57.9366 59 58.3843 59.4477 58.3843 60V96.2334C58.3843 96.9469 57.6586 97.4308 56.9999 97.1566L39.0269 89.6734C38.7859 89.573 38.5153 89.5709 38.2728 89.6674L19.3697 97.188C18.7131 97.4493 18 96.9656 18 96.2589Z"
                    fill="url(#paint0_linear)" />
                <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="17" y="58" width="42" height="38">
                    <path
                        d="M18 90.3625V64C18 61.2386 20.2386 59 23 59H53.3843C56.1457 59 58.3843 61.2386 58.3843 64V90.2351C58.3843 93.8024 54.7557 96.2222 51.4624 94.851L40.5301 90.2992C39.3254 89.7977 37.9723 89.7869 36.7599 90.2693L24.8484 95.0083C21.5653 96.3145 18 93.8959 18 90.3625Z"
                        fill="#71A1F4" />
                    <path
                        d="M18 90.3625V64C18 61.2386 20.2386 59 23 59H53.3843C56.1457 59 58.3843 61.2386 58.3843 64V90.2351C58.3843 93.8024 54.7557 96.2222 51.4624 94.851L40.5301 90.2992C39.3254 89.7977 37.9723 89.7869 36.7599 90.2693L24.8484 95.0083C21.5653 96.3145 18 93.8959 18 90.3625Z"
                        fill="url(#paint1_linear)" />
                    <path
                        d="M18 90.3625V64C18 61.2386 20.2386 59 23 59H53.3843C56.1457 59 58.3843 61.2386 58.3843 64V90.2351C58.3843 93.8024 54.7557 96.2222 51.4624 94.851L40.5301 90.2992C39.3254 89.7977 37.9723 89.7869 36.7599 90.2693L24.8484 95.0083C21.5653 96.3145 18 93.8959 18 90.3625Z"
                        stroke="#8C62F5" />
                </mask>
                <g class="badge_ribbon right" mask="url(#mask0)">
                    <rect width="12.9378" height="46.9542" transform="matrix(0.99996 -0.008926 0.0199657 0.999801 32.0576 65.3011)"
                        fill="#FCD977" />
                </g>
                <circle cx="37.5" cy="37.5" r="33.5" fill="#DBDFE7" stroke="#8C62F5" stroke-width="8" />
                <circle class="badge_circle" cx="37.5" cy="37.5" r="29.5" fill="#8C62F5" stroke="#D1C0FB" stroke-width="4" />
                <path class="badge_number"
                    d="M43.16 39.98H41.28V30.7H39.04C39 30.76 37.64 32.78 36.82 33.96L32.54 40.12V42.08H39.02V45H41.28V42.08H43.16V39.98ZM39.06 33.9C39.04 34.58 39.02 35.68 39.02 36.46V39.98H35.02C35.02 39.98 35.84 38.76 36.52 37.78L37.46 36.4C38.1 35.46 38.86 34.16 39.02 33.9H39.06Z"
                    fill="#E3D9FC" />
                <defs>
                    <linearGradient id="paint0_linear" x1="38.1921" y1="59" x2="38.1921" y2="97.1461"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#DFAC28" />
                        <stop offset="1" stop-color="#FCC838" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint1_linear" x1="38.1921" y1="59" x2="38.1921" y2="97.1461"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>
        </div>
    ';
            case 6:
                return ' <div class="animated_badge animated_badge_5 hide">
           
            <svg class="animated_badge_svg" width="84" height="99" viewBox="0 0 84 99" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path class="badge_ribbon"
                    d="M0.761166 82.9447L17.3041 50.8886C17.5574 50.3979 18.1606 50.2053 18.6513 50.4586L43.875 63.4756C44.3658 63.7289 44.5583 64.332 44.305 64.8228L27.7788 96.8466C27.4294 97.5236 26.4817 97.5769 26.0586 96.9435L17.8756 84.694C17.6881 84.4133 17.3716 84.2461 17.0341 84.2495L1.65981 84.4033C0.906487 84.4108 0.415676 83.6142 0.761166 82.9447Z"
                    fill="#F04152" />
                <path class="badge_ribbon"
                    d="M0.761166 82.9447L17.3041 50.8886C17.5574 50.3979 18.1606 50.2053 18.6513 50.4586L43.875 63.4756C44.3658 63.7289 44.5583 64.332 44.305 64.8228L27.7788 96.8466C27.4294 97.5236 26.4817 97.5769 26.0586 96.9435L17.8756 84.694C17.6881 84.4133 17.3716 84.2461 17.0341 84.2495L1.65981 84.4033C0.906487 84.4108 0.415676 83.6142 0.761166 82.9447Z"
                    fill="url(#paint0_linear)" />
                <mask id="mask0" mask-type="alpha" maskUnits="userSpaceOnUse" x="1" y="50" width="44" height="46">
                    <path
                        d="M3.80623 77.0441L15.4697 54.4432C16.7361 51.9893 19.752 51.0266 22.2059 52.293L40.3204 61.6412C42.7743 62.9076 43.737 65.9235 42.4707 68.3774L30.8904 90.817C29.1437 94.2016 24.4052 94.4685 22.2895 91.3014L19.0777 86.4935C18.1402 85.09 16.5578 84.2543 14.8701 84.2712L8.29946 84.3369C4.53283 84.3745 2.07879 80.3915 3.80623 77.0441Z"
                        fill="#71A1F4" />
                    <path
                        d="M3.80623 77.0441L15.4697 54.4432C16.7361 51.9893 19.752 51.0266 22.2059 52.293L40.3204 61.6412C42.7743 62.9076 43.737 65.9235 42.4707 68.3774L30.8904 90.817C29.1437 94.2016 24.4052 94.4685 22.2895 91.3014L19.0777 86.4935C18.1402 85.09 16.5578 84.2543 14.8701 84.2712L8.29946 84.3369C4.53283 84.3745 2.07879 80.3915 3.80623 77.0441Z"
                        fill="url(#paint1_linear)" />
                </mask>
                <g class="badge_ribbon" mask="url(#mask0)">
                    <rect width="9.73445" height="46.9502" transform="matrix(0.894023 0.448022 -0.445194 0.895434 24.272 60.4499)"
                        fill="#FCD977" />
                </g>
                <path class="badge_ribbon right"
                    d="M56.5365 97.1074L38.5 65.8673C38.2239 65.389 38.3877 64.7774 38.866 64.5013L63.4476 50.3091C63.9259 50.0329 64.5375 50.1968 64.8137 50.6751L82.832 81.8838C83.2129 82.5435 82.7458 83.3698 81.9841 83.3836L67.2552 83.6511C66.9177 83.6572 66.6061 83.8332 66.4266 84.1191L58.2494 97.1392C57.8487 97.7772 56.9132 97.7598 56.5365 97.1074Z"
                    fill="#F04152" />
                <path class="badge_ribbon right"
                    d="M56.5365 97.1074L38.5 65.8673C38.2239 65.389 38.3877 64.7774 38.866 64.5013L63.4476 50.3091C63.9259 50.0329 64.5375 50.1968 64.8137 50.6751L82.832 81.8838C83.2129 82.5435 82.7458 83.3698 81.9841 83.3836L67.2552 83.6511C66.9177 83.6572 66.6061 83.8332 66.4266 84.1191L58.2494 97.1392C57.8487 97.7772 56.9132 97.7598 56.5365 97.1074Z"
                    fill="url(#paint2_linear)" />
                <mask id="mask1" mask-type="alpha" maskUnits="userSpaceOnUse" x="38" y="49" width="45" height="47">
                    <path
                        d="M53.2165 91.357L40.5 69.3314C39.1193 66.9399 39.9387 63.882 42.3301 62.5013L59.9835 52.3091C62.375 50.9283 65.4329 51.7477 66.8137 54.1392L79.4394 76.0076C81.3438 79.3061 79.0082 83.4376 75.2001 83.5068L69.419 83.6118C67.7314 83.6424 66.1732 84.5224 65.2756 85.9517L61.7808 91.5162C59.7774 94.7061 55.0999 94.6192 53.2165 91.357Z"
                        fill="#71A1F4" />
                    <path
                        d="M53.2165 91.357L40.5 69.3314C39.1193 66.9399 39.9387 63.882 42.3301 62.5013L59.9835 52.3091C62.375 50.9283 65.4329 51.7477 66.8137 54.1392L79.4394 76.0076C81.3438 79.3061 79.0082 83.4376 75.2001 83.5068L69.419 83.6118C67.7314 83.6424 66.1732 84.5224 65.2756 85.9517L61.7808 91.5162C59.7774 94.7061 55.0999 94.6192 53.2165 91.357Z"
                        fill="url(#paint3_linear)" />
                </mask>
                <g class="badge_ribbon right" mask="url(#mask1)">
                    <rect width="9.73445" height="46.9502" transform="matrix(0.860033 -0.510239 0.512954 0.858416 50.3103 65.1699)"
                        fill="#FCD977" />
                </g>
                <circle cx="40.5" cy="37.5" r="33.5" fill="#DBDFE7" stroke="#FCD977" stroke-width="8" />
                <circle class="badge_circle" cx="40.5" cy="37.5" r="29.5" fill="#FCD977" stroke="#FFA826" stroke-width="4" />
                <path class="badge_number"
                    d="M35.26 38.42C36.04 38.52 36.74 38.66 37.5 38.78C37.86 38.1 38.56 37.56 39.64 37.56C41.26 37.56 42.22 38.8 42.22 40.34C42.22 42 41.28 43.2 39.62 43.2C38.18 43.2 37.4 42.28 37.18 41.08H34.72C34.98 43.56 36.86 45.3 39.68 45.3C42.68 45.3 44.7 43.14 44.7 40.26C44.7 37.42 42.86 35.46 40.16 35.46C39.16 35.46 38.36 35.76 37.68 36.2L37.84 35.06C37.94 34.34 37.98 33.62 38.1 32.9H43.7V30.7H36.14C35.84 33.32 35.58 35.78 35.26 38.42Z"
                    fill="#FFA826" />
                <defs>
                    <linearGradient id="paint0_linear" x1="31.2632" y1="56.9671" x2="13.7695" y2="90.8654"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#A31523" />
                        <stop offset="1" stop-color="#F04152" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint1_linear" x1="31.2632" y1="56.9671" x2="13.7695" y2="90.8654"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint2_linear" x1="51.1568" y1="57.4052" x2="70.2299" y2="90.4407"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#A31523" />
                        <stop offset="1" stop-color="#F04152" stop-opacity="0" />
                    </linearGradient>
                    <linearGradient id="paint3_linear" x1="51.1568" y1="57.4052" x2="70.2299" y2="90.4407"
                        gradientUnits="userSpaceOnUse">
                        <stop stop-color="#27539F" />
                        <stop offset="1" stop-color="#71A1F4" stop-opacity="0" />
                    </linearGradient>
                </defs>
            </svg>
        </div>';
            default:
                # code...
                break;
        }
    }
}
