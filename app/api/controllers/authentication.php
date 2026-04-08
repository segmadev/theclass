<?php 
// require_once ROOT."functions/helper.php";
require_once ROOT."functions/formhandler.php";
class apiAuthentication extends database {
    private $user_form;
    private $user;
    function __construct() {
        parent::__construct();
        $this->user_form = [
            "ID"=>["is_required"=>false],
            "first_name"=>["is_required"=>true],
            "last_name"=>[],
            "email"=>[],
            "phone_number"=>["is_required"=>true],
            "referral_code"=>["is_required"=>false],
            "password"=>["is_required"=>true],
            "confirm_password"=>["is_required"=>true]
        ];
    }

    function register() {
        // $first_name = htmlspecialchars($_POST['first_name'] ?? "nothing");
        // if(!isset($_POST['first_name']) || empty($first_name)) return $this->apiMessage("First name is required", 400);
        $this->user = $this->validate_form($this->user_form);
        // check if phone exit 
        // check if gmail exit
        $check = $this->getall("users", "email = ? or phone_number = ?", [$this->user['email'], $this->user['phone_number']], fetch:"");
        if($check > 0) return $this->apiMessage("User with email or phone number already exists", 400);
        $this->user["ID"] = $this->validID(uniqid());
        $this->user["password"] = password_hash($this->user["password"], PASSWORD_DEFAULT);
        if($this->quick_insert("users", $this->user)) return $this->apiMessage("User registered successfully", 201);
        return $this->apiMessage("Failed to register user", 500);
    }

    function signin() {
        $login_form = [
            "email"=>[],
            "password"=>[]
        ];
        $userInput = $this->validate_form($login_form);
        $this->user = $this->getall("users", "email = ?", [$userInput['email']], "ID, first_name, last_name, email, phone_number, balance, password");
        if(!is_array($this->user)) return $this->apiMessage("User with email or password invalid", 400);
        if(!password_verify($userInput['password'], $this->user['password'])) return $this->apiMessage("User with email or password invalid", 400);
        $this->user['token'] = $this->set_token();
        unset($this->user['password']);
        if($this->user['token']) return $this->apiMessage("User signed in successfully", 200, $this->user);
        return $this->apiMessage("Failed to sign in user", 500);
    }


    private function set_token()
    {
        $user = $this->user;
        $userID = $user['ID'];
        $token = bin2hex(random_bytes(32)); // Access token
        $tokenHash = hash('sha256', $token);

        $refreshToken = bin2hex(random_bytes(32));
        $refreshTokenHash = hash('sha256', $refreshToken);

        $userDetails = $this->get_visitor_details() ?? ['ip_address' => '', 'device' => '', 'country' => '', 'state' => ''];
        $log = [
            "userID" => $userID,
            "token" => $tokenHash,
            "refresh_token" => $refreshTokenHash,
            "ip" => $userDetails['ip_address'],
            "device" => $userDetails['device'],
            "date_time" => time(),
            "expiry_date" => time() + (60 * 60 * 20), // 20 hour for access token
            "refresh_expiry" => time() + (60 * 60 * 24 * 7), // 7 days for refresh token
            "otp" => "",
        ];
        $this->delete("user_logs", "userID = ? and ip = ?", [$log['userID'], $log['ip']]);
        $this->quick_insert("user_logs", $log);

        // Return the raw tokens to the user (never store them in plaintext)
        unset($log['userID'], $log['ip'], $log['device'], $log['otp'], $log['token'], $log['refresh_token']);
        $log['token'] = $token;
        $log['refresh_token'] = $refreshToken;
        return $log;
    }


    private function validID($ID = null) {
        $ID =  $ID ??  $this->genID(6);
        $user = $this->getall("users", "ID = ?", [$ID], fetch: "");
        if($user > 0) {
            $this->validID();
        }
        return $ID;
    }

    function genID($length = 6) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}