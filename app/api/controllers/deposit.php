<?php 
    require_once "controllers/user.php";
    require_once ROOT . "functions/deposit.php";
    class ApiDeposit extends deposit {
        public $user;
        public function __construct()
        {
            // Call parent constructor to set the name
            parent::__construct();
            $this->user = new ApiUser;
        }
        function inideposit() {
            $rawData = file_get_contents("php://input");
            // Decode JSON
            $_POST = json_decode($rawData, true);
            try {
                $ini = $this->ini_payment($this->user->userID, ($_ENV['fontendurl'] ?? null));
                if(is_array($ini)) return $this->apiMessage("Payment ini sucessfully", 200, $ini);
                return $this->apiMessage("Payment faild", 500);
            } catch (\Throwable $th) {
                //throw $th;
                return $this->apiMessage("Int Error: Payment faild", 500);

            }
        }

        function validatedeposit() {
            if(!isset($_GET['txref']) && !isset($_GET['transID'])) {
                return $this->apiMessage("Pass all required datas", 400);
            }
            try {
                $txref = htmlspecialchars($_GET['txref']);
                $transID = htmlspecialchars($_GET['transID'] ?? null);
                $verify = $this->validate_payment($txref, $transID, $this->user->userID);
                // die(var_dump($verify));
                if($verify) return $this->apiMessage("Payment validated sucessfully", 200, []);
                return $this->apiMessage("Error Vaildating payment", 400);
            } catch (\Throwable $th) {
                return $this->apiMessage("Int: Error Vaildating payment", 500);
            }
        }
    }