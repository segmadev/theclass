<?php
$base_path = '';
if ((defined('side') && side == "admin") || (defined('ISAPI') && ISAPI == true)) $base_path = '../';
require_once $base_path.'vendor/autoload.php';

use Flutterwave\Flutterwave;
use Flutterwave\Helper\Config;
use Flutterwave\Service\VirtualAccount;

require_once $base_path.'functions/crypto.php';
class deposit extends user
{
    protected $crypto;
    protected $monipfy_base_url = 'https://api.monnify.com';
    protected $debug = false;
    public function __construct()
    {
        parent::__construct();
        $this->crypto = new CryptomusService($this->get_settings("cryptomus_ID"), $this->get_settings("cryptomus_api"));
    }
    public function get_payments($userID, $start, $limit =10)
    {
        return $this->getall("payment", "userID = ? order by date DESC LIMIT $start, $limit", [$userID], fetch: "moredetails");
    }
    public function get_account_details($userID)
    {
        $user = $this->getall("users", "ID =?", [$userID]);
        if (!is_array($user)) {
            return null;
        }

        $account = $this->getall("user_accounts", "userID = ? and payment_gateway != ? GROUP BY account_number order by date desc limit 2", [$userID, "flutterwave"], fetch: "all");
        if ($account != "") return $account;
        return null;
        
        // $account = $this->getall("user_accounts", "userID = ?", [$userID]);
        // if (!is_array($account)) {
        //     return null;
        // }

        return $account;
    }
    function requestRefund($transactionId, $amount = null, $narration = "Refund for deposit") {
        // get transaction details
        $transaction = $this->getall("transactions", "ID = ?", [$transactionId]);
        if(!is_array($transaction)) {
            return $this->message("Transaction not found", "error");
        }
        if($this->getall("transactions", "forID = ? and trans_for = ?", [$transactionId, "refund"], fetch: "") > 0) {
            return $this->message("A refund request is already made for this transaction", "error");
        }
        // try make a debit
        $debit = $this->credit_debit($transaction['userID'], ($amount ?? $transaction['amount']), action: "debit", for: "refund", forID: $transactionId, narration: $narration, request: false);
        if(!$debit) {
            return $this->message("Unable to make a refund. A pending refund request is already made.", "error");
        }
        return $this->message("Refund request made successfully. The amount debited from user's account.", "success");
    }

    function clear_refund_request($transactionId) {

        $delete = $this->delete("transactions", "ID = '$transactionId' and status = 'pending'", []);
         if(!$delete) {
            return $this->message("Faild to Refund request canceled", "error");
        }

         if($delete) {
            return $this->message("Refund request canceled", "success");
        }
    }


    function process_pending_request() {
        
    }

    public function create_wallet_address(array $user)
    {
        $check = $this->getall("user_wallets", 'userID = ?', [$user['ID']]);
        if (is_array($check)) {
            return $check;
        }

        $wallet = $this->crypto->createPaymentWallet($user['ID']);
        if (!is_array($wallet) || !isset($wallet['uuid'])) {
            return $this->message("Unable to create wallet", "error");
        }

        if ($this->getall("user_wallets", "uuid = ?", [$wallet['uuid']], fetch: "") > 0) {
            return;
        }

        $wallet = array_merge(["userID" => $user['ID']], $wallet);
        $this->quick_insert("user_wallets", $wallet);
        return $wallet;
    }

    private function create_paymentpoint_account($user) {
        $phoneNumber = $user['phone_number'];
        if (!$this->is_valid_phone_number($phoneNumber)) {
             $this->message("Unable to create <b>Palmpay Account</b>  for you because of Invalid phone number (Phone number should be 11 digit). <a target='_BLANK' class='text-info' href='index?p=profile'>Update phone number</a> and try again", "error");
             return false;
        }


       $endpoint = "https://api.paymentpoint.co/api/v1/createVirtualAccount";
       $data =  json_encode([
            "email" => $user['email'],
            "name" => $user['first_name'].' '.$user['last_name'],
            "phoneNumber" => $user['phone_number'],
            "bankCode"=> ['20946'],
            "businessId" => $this->get_settings("paymentpoint_businessId"),
        ], true);
        $header = [
            'Authorization: Bearer ' . $this->get_settings("paymentpoint_secret_key"),
            'Content-Type: application/json',
            'api-key: '.$this->get_settings("paymentpoint_api_key")
        ];
        $response = json_decode($this->api_call($endpoint, $data, $header, true), true);
        if(!isset($response['customer']['customer_id'])) {
            $this->message("Unable to create new account for you. Please try again later","error");
            return false;
        }
        $account = [
            "userID" => $user['ID'],
            "tx_ref" => $response['bankAccounts'][0]['Reserved_Account_Id'],
            "order_ref" => $response['customer']['customer_id'],
            "account_number" => (int) $response['bankAccounts'][0]['accountNumber'],
            "bank_name" => $response['bankAccounts'][0]['bankName'],
            "note" => $response['bankAccounts'][0]['accountName'],
            "expiry_date" => "N/A",
            "payment_gateway"=> "paymentpoint"
        ];
        if ($this->quick_insert("user_accounts", $account)) {
            return $account;
        }
        return false;
    }

    private function get_monipfy_token($force_new = false) {
        // Check if we have a cached token
        $cached_token = $this->getall("settings", "meta_name = ?", ["monipfy_token"]);
        $current_time = time();

        if (!$force_new && is_array($cached_token)) {
            $token_data = json_decode($cached_token['meta_value'], true);
            
            // Check if token exists and is still valid (with 5 min buffer)
            if (isset($token_data['token']) && 
                isset($token_data['expires_at']) && 
                $token_data['expires_at'] > ($current_time + 300)) {
                return $token_data['token'];
            }
        }

        // Get credentials from settings
        $apiKey = $this->get_settings("monipfy_api_key");
        $secretKey = $this->get_settings("monipfy_secret_key");
        $baseUrl = $this->monipfy_base_url;

        if (!$apiKey || !$secretKey || !$baseUrl) {
            if(isset($_GET['debug'])) $this->message("Monipfy credentials not properly configured", "error");
            return false;
        }

        // Create basic auth token
        $credentials = base64_encode($apiKey . ':' . $secretKey);
        $headers = [
            'Authorization: Basic ' . $credentials,
            'Content-Type: application/json'
        ];

        // Get access token
        $authResponse = $this->api_call($baseUrl . '/api/v1/auth/login', [], $headers, method: "POST");
        if (!isset($authResponse->requestSuccessful) || !$authResponse->requestSuccessful) {
            if(isset($_GET['debug'])) $this->message("Unable to authenticate with Monipfy. Please try again later.", "error");
            return false;
        }

        // Calculate expiry time (subtract 5 minutes for safety margin)
        $expiresIn = $authResponse->responseBody->expiresIn ?? 3600;
        $expires_at = $current_time + $expiresIn - 300;

        // Store token data
        $token_data = [
            'token' => $authResponse->responseBody->accessToken,
            'expires_at' => $expires_at
        ];

        // Update or insert token in settings
        if (is_array($cached_token)) {
            $this->update("settings", 
                ["meta_value" => json_encode($token_data)], 
                "meta_name = 'monipfy_token'"
            );
        } else {
            $this->quick_insert("settings", [
                "meta_name" => "monipfy_token",
                "meta_value" => json_encode($token_data),
                "meta_for" => "all"
            ]);
        }

        return $token_data['token'];
    }

    private function create_monipfy_account($user) {
        // Get base URL from settings
        $baseUrl = $this->monipfy_base_url;
        if (!$baseUrl) {
            // $this->message("Monipfy base URL not configured", "error");
            return false;
        }

        // Get access token
        $accessToken = $this->get_monipfy_token();
        if (!$accessToken) {
            return false;
        }

        // Prepare account creation request
        $accountRef = 'ACC_' . time() . '_' . $user['ID']; // Generate unique reference
        $payload = [
            "accountReference" => $accountRef,
            "accountName" => $user['first_name'] . ' ' . $user['last_name'],
            "currencyCode" => "NGN",
            "contractCode" => $this->get_settings("monipfy_contract_code"),
            "customerEmail" => $user['email'],
            "customerName" => $user['first_name'] . ' ' . $user['last_name'],
            "bvn" => $this->get_settings("bvn"), // Using system BVN as default
            "getAllAvailableBanks" => true
        ];

        // Set headers for account creation
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        // Make API call to create account
        $response = $this->api_call(
            $baseUrl . '/api/v2/bank-transfer/reserved-accounts',
            json_encode($payload),
            $headers
        );
        // If token expired, try once more with a fresh token
        if (!isset($response->requestSuccessful) && 
            isset($response->responseCode) && 
            $response->responseCode === "99") {
            
            // Force new token generation
            $accessToken = $this->get_monipfy_token(true);
            if (!$accessToken) {
                return false;
            }

            // Update headers with new token
            $headers = [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ];

            // Retry the request
            $response = $this->api_call(
                $baseUrl . '/api/v2/bank-transfer/reserved-accounts',
                json_encode($payload),
                $headers
            );
        }

        if (!isset($response->requestSuccessful) || !$response->requestSuccessful) {
            $errorMsg = $response->responseMessage ?? "Unable to create account. Please try again later.";
            if(isset($_GET['debug'])) $this->message($errorMsg, "error");
            return false;
        }

        $responseData = $response->responseBody;
        $accountInfo = $responseData->accounts[0]; // Get first account

        $account = [
            "userID" => $user['ID'],
            "tx_ref" => $accountRef,
            "order_ref" => $responseData->reservationReference,
            "account_number" => (int) $accountInfo->accountNumber,
            "bank_name" => $accountInfo->bankName,
            "note" => $accountInfo->accountName,
            "expiry_date" => "N/A",
            "payment_gateway" => "monipfy"
        ];

        if ($this->quick_insert("user_accounts", $account)) {
            return $account;
        }

        return false;
    }

    

    // public function delete_monipfy_reserved_account($accountReference, $deleteLocal = true) {
    //     $accountReference = trim((string)$accountReference);
    //     if ($accountReference === '') {
    //         return false;
    //     }
    //     $baseUrl = $this->monipfy_base_url;
    //     $accessToken = $this->get_monipfy_token();
    //     if (!$accessToken) {
    //         return false;
    //     }
    //     $headers = [
    //         'Authorization: Bearer ' . $accessToken,
    //         'Content-Type: application/json'
    //     ];
    //     $url = $baseUrl . '/api/v1/bank-transfer/reserved-accounts/reference/' . rawurlencode($accountReference);
    //     $response = $this->api_call($url, [], $headers, method: "DELETE");
    //     if (!isset($response->requestSuccessful) || !$response->requestSuccessful) {
    //         if (isset($response->responseCode) && $response->responseCode === "99") {
    //             $accessToken = $this->get_monipfy_token(true);
    //             if (!$accessToken) {
    //                 return false;
    //             }
    //             $headers = [
    //                 'Authorization: Bearer ' . $accessToken,
    //                 'Content-Type: application/json'
    //             ];
    //             $response = $this->api_call($url, [], $headers, method: "DELETE");
    //         }
    //     }
    //     if (isset($response->requestSuccessful) && $response->requestSuccessful) {
    //         if ($deleteLocal) {
    //             $this->delete("user_accounts", "tx_ref = ? AND payment_gateway = ?", [$accountReference, "monipfy"]);
    //         }
    //         return true;
    //     }
    //     return false;
    // }

    public function create_account_details(array $user, $gateway = "paymentpoint")
    {
        $account = $this->getall("user_accounts", "userID = ? and payment_gateway = ?", [$user['ID'], $gateway]);
        if(is_array($account)) return $account;
        if($gateway == "paymentpoint") {
            // check if user already have an account
            return $this->create_paymentpoint_account($user);
        }
        if($gateway == "monipfy") {
            return $this->create_monipfy_account($user);
        }
        if ($this->get_settings("bvn") == "") {
            return false;
        }

        if ($this->getall("user_accounts", "userID = ?", [$user['ID']], fetch: "") > 0) {
            return false;
        }

        $myConfig = Config::setUp(
            $this->get_settings("flutterwave_secret_key"),
            $this->get_settings("flutterwave_public_key"),
            $this->get_settings("flutterwave_encyption_key"),
            'staging'
        );
        Flutterwave::bootstrap($myConfig);
        $service = new VirtualAccount(config: $myConfig);
        $tx_ref = time() . uniqid();
        $payload = [
            "email" => $user['email'],
            "tx_ref" => $tx_ref,
            "bvn" => $this->get_settings("bvn"),
            "narration" => $user['first_name'] . ' ' . $user['last_name'],
            "is_permanent" => true,
        ];
        $response = $service->create($payload);
        if (!isset($response->status) || $response->status != "success" || !isset($response->data)) {
            return false;
        }

        $data = (array) $response->data;
        $account = [
            "userID" => $user['ID'],
            "tx_ref" => $tx_ref,
            "order_ref" => $data['order_ref'],
            "account_number" => (int) $data['account_number'],
            "bank_name" => $data['bank_name'],
            "note" => $data['note'],
            "expiry_date" => $data['expiry_date'],
        ];
        if ($this->quick_insert("user_accounts", $account)) {
            return $account;
        }
        return false;
    }
    public function ini_payment($userID, $website_url = null, $gateway = "flutterwave", $orderID = null, $amount = null)
    {
        $amount = $amount ?? (float)($_POST['amount'] ?? 0);

        if ($amount <= 0) {
            echo $this->message("Enter a valid amount", "error");
            return false;
        }
        // If orderID is present, skip min/max deposit check as it's a payment, not a deposit
        if ($orderID == null && !$this->check_min_max_deposit($amount)) {
            return false;
        }

        $user = $this->getall("users", "ID = ?", [$userID]);
        if (!is_array($user)) {
            return false;
        }

        // Verify Order if orderID is provided
        if ($orderID != null) {
            $order = $this->getall("products_group_orders", "ID = ? AND userID = ?", [$orderID, $userID]);
            if (!is_array($order)) {
                echo $this->message("Invalid Order ID", "error");
                return false;
            }
            if ($order['status'] != 'pending' && $order['status'] != 'waiting_payment') {
                echo $this->message("Order status is " . $order['status'], "error");
                return false;
            }
        }
        $website_url = 'http://localhost' ?? $this->get_settings("website_url");
        $website_url .=  ($orderID != null && $orderID != "") ? "/shop/validate/$orderID" : "/dashboard/deposit/validate";
        $gateway = strtolower($gateway);

        if ($gateway === 'korapay') {
            return $this->process_korapay_payment($user, $amount, $website_url, $orderID);
        }

        // Default to flutterwave
        return $this->process_flutterwave_payment($user, $amount, $website_url, $orderID);
    }

    private function process_flutterwave_payment($user, $amount, $website_url, $orderID = null)
    {
        $tx_ref = uniqid('tx-ref-');
        $redirect_url = $website_url;
        
        $data = [
            'tx_ref' => $tx_ref,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => $redirect_url,
            'meta' => [
                'consumer_id' => $user['ID'],
                'orderID' => $orderID
            ],
            'customer' => [
                'email' => $user['email'],
                'phonenumber' => $user['phone_number'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
            ],
            'customizations' => [
                'title' => $orderID ? "Payment for Order #$orderID" : $user['first_name'] . " Fund Account",
                'logo' => "http://localhost/images/logo-2.svg",
            ],
        ];

        $payment = [
            "userID" => $user['ID'], 
            "tx_ref" => $tx_ref, 
            "amount" => $amount, 
            "title" => $data['customizations']['title'],
            "orderID" => $orderID
        ];

        if (!$this->quick_insert("payment", $payment)) {
            return false;
        }

        $headers = [
            'Authorization: Bearer ' . $this->get_settings("flutterwave_secret_key"),
            'Content-Type: application/json',
        ];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.flutterwave.com/v3/payments',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return $this->message("cURL Error: " . $err, "error");
        }

        $response = json_decode($response, true);
        $response = (array) $response;

        if (isset($response['status']) && $response['status'] == "success" && isset($response['data']['link'])) {
            $link = $response['data']['link'];
            $this->update("payment", ["status" => "pending", "pay_url" => $link], "tx_ref = '" . $tx_ref . "'");
            
            $data['pay_url'] = $link;
            if(defined('ISAPI') && ISAPI == true) {
                return $data;
            }
            $return = [
                "message" => ["Success", "Redirecting...", "success"],
                "function" => ["loadpage", "data" => [$link, "success"]],
            ];
            return json_encode($return);
        } else {
            $msg = isset($response['message']) ? $response['message'] : "Unknown error";
            return $this->message("Error: " . $msg, "error");
        }
    }

    private function process_korapay_payment($user, $amount, $website_url, $orderID = null)
    {
        $tx_ref = uniqid('kp-ref-');
        $website_url = rtrim($website_url, '/');
        $redirect_url = $website_url;
        
        $data = [
            'reference' => $tx_ref,
            'amount' => $amount,
            'currency' => 'NGN',
            'redirect_url' => $redirect_url,
            'notification_url' => $website_url . '/webhook/korapay', // Optional, assuming webhook structure
            'customer' => [
                'email' => $user['email'],
                'name' => $user['first_name'] . ' ' . $user['last_name'],
            ],
            'merchant_bears_cost' => true,
            'metadata' => [
                'consumer_id' => $user['ID'],
                'orderID' => $orderID
            ]
        ];

        $payment = [
            "userID" => $user['ID'], 
            "tx_ref" => $tx_ref, 
            "amount" => $amount, 
            "title" => $orderID ? "Payment for Order #$orderID (Korapay)" : "Fund Account (Korapay)",
            "orderID" => $orderID
        ];

        if (!$this->quick_insert("payment", $payment)) {
            return false;
        }

        $headers = [
            'Authorization: Bearer ' . $this->get_settings("korapay_secret_key"),
            'Content-Type: application/json'
        ];

        // Use raw curl to ensure exact compliance with Korapay docs and avoid any api_call middleware issues
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.korapay.com/merchant/api/v1/charges/initialize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        // var_dump($response);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return $this->message("cURL Error: " . $err, "error");
        }

        $result = json_decode($response, true);

        if (isset($result['status']) && ($result['status'] === true || $result['status'] === 'success') && isset($result['data']['checkout_url'])) {
            $link = $result['data']['checkout_url'];
            $this->update("payment", ["status" => "pending", "pay_url" => $link], "tx_ref = '" . $tx_ref . "'");
            
            $data['pay_url'] = $link;
            if(defined('ISAPI') && ISAPI == true) {
                return $data;
            }
            $return = [
                "message" => ["Success", "Redirecting...", "success"],
                "function" => ["loadpage", "data" => [$link, "success"]],
            ];
            return json_encode($return);
        } else {
            $msg = isset($response['message']) ? $response['message'] : "Unknown error";
            return $this->message("Error: " . $msg, "error");
        }
    }

    public function verifyPayment($txref)
    {
        if (strpos($txref, 'kp-ref-') === 0) {
            return $this->verifyKorapayPayment($txref);
        }
        return $this->verifyFlutterwavePayment($txref);
    }

    private function verifyFlutterwavePayment($txref)
    {
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->get_settings("flutterwave_secret_key"),
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/$txref/verify",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ['status' => 'error', 'message' => "cURL Error: " . $err];
        }

        return json_decode($response, true);
    }

    private function verifyKorapayPayment($txref)
    {
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer " . $this->get_settings("korapay_secret_key"),
        ];

        // Korapay verify endpoint uses reference
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.korapay.com/merchant/api/v1/charges/$txref",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if($this->debug) var_dump($response);
        
        if ($err) {
            return ['status' => 'error', 'message' => "cURL Error: " . $err];
        }
        
        $response = json_decode($response, true);
        if($this->debug) var_dump($response);
        
        if (isset($response['status']) && $response['status'] === true) {
            // Map Korapay status to Flutterwave-like status for validate_payment compatibility
            // Korapay data.status can be 'success', 'pending', 'failed'
            if (isset($response['data']['status'])) {
                $response['status'] = ($response['data']['status'] == 'success') ? 'success' : $response['data']['status'];
            }
            // Ensure data field exists
        }
        if($this->debug) var_dump($response);
        // die(var_dump($response));
        
        return $response;
    }

    public function validate_payment($txref, $transID = null, $userID = null, $debug = false)
    {
        // $pay = $this->verifyPayment($transID);
        // die(var_dump($pay));
        // check if txref is valid is own by userID
        if($debug) $this->debug = true;
        $trans = $this->getall("payment", "userID = ? and tx_ref = ?", [$userID, $txref]);
        if (!is_array($trans)) {
            if($debug) var_dump($trans);
            // error_log("No trans for $userID, $txref");
            return false;
        }
        
        $gateway = "flutterwave";
        if((strpos($txref, 'kp-ref-') === 0)) { 
            $gateway = "korapay";
            $transID = $txref;
        }
        if($transID == null || $transID == ""){
             if($debug) var_dump("TRANSID: ".$transID);
            $this->message("Invalid transaction ID", "error");
            return false;
        }
        if ($trans['status'] == "successful" || $trans['status'] == "success") {
            
            // error_log("All ready maked success");
            // $this->message("Transaction amount already added to balance", "success");
            return true;
        }
        
        if ($trans['status'] != "" && $trans['status'] != "initiate" && $trans['status'] != "pending" && $trans['status'] != "successful" && $trans['status'] != "success" && $trans['status'] != "processing") {
            // error_log("Failed");
            if($debug) var_dump("Some pay status issue", $trans);
            $this->message("Faild Transaction.", "error");
            return false;
        }
        // var_dump($txref, $userID); die();
        // verifyPayment
        $pay = $this->verifyPayment($transID);
        if($debug) var_dump($pay);
        if (!$pay) {
            // die(var_dump($pay));
            // error_log("Error vaildate "+ var_dump($pay));
            $this->message("Error verifying payment", "error");
            return false;
        }
        // https://checkout.flutterwave.com/v3/hosted/pay/flwlnk-01jhz6czmmhhmw9kjybbghd18b
        // confrim if the amount match the amount
        if ($pay['status'] != "successful" && $pay['status'] != "success") {
            if (isset($_GET['continue'])) {
                return $this->loadpage($pay['pay_url']);
            }
            // error_log("Payment faild");
            
            // $this->message("Payment Faild please try again", "error");
            $this->update("payment", ["transaction_id" => $transID, "status" => $pay['status']], "ID = '" . $trans['ID'] . "'");
            return false;
        }
        
        // check of txt_ref match
        // check of userID match $pay['customer']['id']
        $pay = $pay['data'];
        
        if ($pay['payment_type'] == "bank_transfer" && $gateway == "flutterwave") {
            $this->message("Payment successful. The amount will be credited to your account shortly.", "success");
            // echo $this->message("This is a transfer", "error");
            return false;
        }
        if ($this->getall("transactions", "userID = ? and forID = ?", [$userID, $pay["flw_ref"]], fetch: "") > 0) {
            // error_log("Value assiged in the past");
            // echo $this->apiMessage("Value assigned in the past", 401);
            return false;
        }
        $payUserID = $pay['meta']["consumer_id"] ?? $pay['metadata']['consumer_id'] ?? null;
        $paytxref  = $pay["tx_ref"] ?? $pay['reference'] ?? null;
        if ($paytxref != $txref || ($payUserID != $userID)) {
            // error_log("Not ur user ID");
            // $this->message("Payment Faild please try again. <br> Seems the payment do not belong to you. <br> if you think this is an error send an email to: " . $this->get_settings("support_email"), "error");
            return false;
        }
        $amount = $pay['amount'];
        
        // Check if this is an order payment
        $orderID = $trans['orderID'] ?? ($pay['meta']['orderID'] ?? ($pay['metadata']['orderID'] ?? null));
        
        if ($orderID != null) {
             // Verify order exists
             $order = $this->getall("products_group_orders", "ID = ?", [$orderID]);
             if (is_array($order) && $order['status'] == "pending") {
                 // Update Order Status
                 $this->update("products_group_orders", ["status" => "order_placed", "paymentID" => $trans['ID']], "ID = '$orderID'");
                 $this->update("products_order", ["status" => "order_placed", "transID" => $trans['ID']], "orderID = '$orderID'");
                 // Update Payment Status
                 $this->update("payment", ["transaction_id" => $transID, "amount" => $amount, "status" => $pay['status'], "meta_data" => json_encode($pay)], "ID = '" . $trans['ID'] . "'");
                 try {
                    if (!class_exists('ApiA2a')) { require_once __DIR__ . '/../api/controllers/a2a.php'; }
                    $a2a = new ApiA2a();
                    $notifyTitle = "$orderID: New Successful Order Placed";
                    $notifyBody = "A new order just came in with the total amount: " . $amount
                    . " and status: order_placed"
                    . ". ID: " . $orderID
                    . ". User: " . $userID
                    . ". Transaction: " . $transID;
                    $a2a->broadcastFcmNotification($notifyTitle, $notifyBody);
                 } catch (\Throwable $th) {
                    //throw $th;
                 }
                 $this->message("Order Placed Successfully", "success");
                 return true;
             }
             $this->message("Order failed or already Placed in the past", "error");
             die();
            //  return false;
        }

        if (isset($pay['flw_ref']) && !empty($pay['flw_ref'])) {
            if ($this->credit_debit($userID, $amount, for :"payment", forID: $pay['flw_ref'], narration: "Account Funding", raw_data: $pay, applyReferralBonus: true)) {
                $this->update("payment", ["transaction_id" => $transID, "amount" => $amount, "status" => $pay['status']], "ID = '" . $trans['ID'] . "'");
                // $this->message("Payment successfull", "success");
                $this->quick_insert("assiged_value", ["transID" => $pay['id'], "userID" => $userID]);
                return true;
                // credit amount and update the payment status and amount
            }
        }

        // $this->message("Issue crediting your account. Please try again or contact us" . $this->get_settings("support_email"), "error");
        return true;
    }

    public function handle_double()
    {
        $trans = $this->getall("transactions", "ID != ? and forID is null and transID is null order by date desc", [""], fetch: "all");
        // var_dump($trans->rowCount());
        $datas = [];
        foreach ($trans as $tran) {
            $datas[$tran['userID']] = $tran['current_balance'];
            $userID = $tran['userID'];
            $amount = $tran['current_balance'];

        }
        print($datas);
    }

    public function handleuserissue($userID, $amount)
    {
        $users = $this->getall("users", "userID LIKE '$userID%' and balance = $amount LIMIT 50", [""]);
        foreach ($users as $user) {
            $last_trans = $this->getall("transactions", "userID = ? order by date desc", [$user['ID']]);
            $current_balance = $last_trans['current_balance'];
            echo "<p> Balance: " . $user['balance'] . "</p>";
            echo "<p> Current Balance: $current_balance</p>";
            echo "<hr>";
            $duserID = $user['ID'];
            // $this->update("users", ["balance"=>$current_balance], "ID = '$duserID'");
        }
    }

    public function check_min_max_deposit($amount)
    {
        $max = (int) $this->get_settings("max_deposit");
        $min = $this->get_settings("min_deposit");
        if ($amount < $min) {
            $this->message("The minimum amount you can deposit is " . $this->money_format($min, currency), "error");
            return false;
        }

        if ($amount > $max && $max > 0) {
            $this->message("The maximum amount you can deposit is " . $this->money_format($max, currency), "error");
            return false;
        }
        return true;
    }

    public function new_deposit($data, $userID)
    {
        // $check = $this->getall("deposit", "userID = ? and status = ?", [$userID, "pending"], fetch: "");
        if ($this->get_deposit_max($userID)) {
            return $this->message("You have alot of pending deposit please wait for approval before you can make a new deposit", "error");
        }
        if (!is_array($data)) {
            return null;
        }
        $info = $this->validate_form($data);
        if (!is_array($info)) {
            return false;
        }
        $insert = $this->quick_insert("deposit", $info);
        if ($insert) {
            $actInfo = ["userID" => $userID, "date_time" => date("Y-m-d H:i:s"), "action_name" => "New Deposit", "description" => "A deposit of " . $this->money_format($info['amount'], currency) . " was made into your account."];
            $this->new_activity($actInfo);
            $return = [
                "message" => ["Sucess", "Deposit Submited for approval", "success"],
                "function" => ["loadpage", "data" => ["?p=deposit&action=list", "success"]],
            ];
            return json_encode($return);
        }
        return $this->message("Error submiting your request", "error");
    }

    public function get_total_pending($userID, $status, $data = null)
    {
        if ($data == null) {
            $data = $this->getall("deposit", "userID = ? and status = ? LIMIT 50", [$userID, $status], fetch: "moredetails");
        }
        if ($data->rowCount() == 0) {
            return $info = ['data' => [], 'number' => 0, 'total' => 0];
        }
        $info['total'] = 0;
        $info['data'] = $data;
        $info['number'] = $data->rowCount();
        foreach ($data as $row) {
            $info['total'] = $info['total'] + (float) $row['amount'];
        }
        // var_dump($total_amount);
        return $info;
    }
    public function get_deposit_max($userID)
    {
        $check = $this->getall("deposit", "userID = ? and status = ?", [$userID, "pending"], fetch: "");
        if ($check > $this->get_settings("deposit_max") || $check == $this->get_settings("deposit_max")) {
            return true;
        }
        return false;
    }

    /**
     * Process a Cryptomus webhook: validate the transaction and assign the converted amount to the user.
     *
     * Expects a JSON payload with:
     * - order_id: Unique order identifier (format: "WALLET_{userId}_{timestamp}")
     * - amount: Amount of cryptocurrency received.
     * - currency: Cryptocurrency code (e.g., "BTC" or "USDT").
     * - status: Payment status (e.g., "paid").
     *
     * @return void Outputs a response and updates the user's balance.
     */
    public function processWebhookTransaction() {
        // Set debug mode: true to print errors on screen, false to only log to file.
        $debugMode = false;
        // Read the raw POST payload.
        $rawPayload = file_get_contents("php://input");
        file_put_contents("payload.txt", "Raw Payload:\n" . $rawPayload . "\n", FILE_APPEND);
        if ($debugMode) {
            echo "Raw Payload: " . htmlspecialchars($rawPayload) . "<br>";
        }
    
        // Verify webhook authenticity.
        if (!$this->crypto->verifyWebhook($debugMode)) {
            $errorMsg = "Webhook verification failed.";
            // error_log($errorMsg);
            file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
            if ($debugMode) {
                echo "Error: " . $errorMsg . "<br>";
            }
            http_response_code(400);
            exit($errorMsg);
        }
    
        // Decode the payload.
        $data = json_decode($rawPayload, true);
        if (!$data) {
            $errorMsg = "Invalid JSON payload.";
            // error_log($errorMsg);
            file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
            if ($debugMode) {
                echo "Error: " . $errorMsg . "<br>";
            }
            http_response_code(400);
            exit($errorMsg);
        }
    
        // Validate required fields.
        $requiredFields = ['uuid', 'order_id', 'amount', 'currency', 'status', 'txid', 'merchant_amount', 'commission'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $errorMsg = "Missing required field: " . $field;
                // error_log($errorMsg);
                file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
                if ($debugMode) {
                    echo "Error: " . $errorMsg . "<br>";
                }
                http_response_code(400);
                exit($errorMsg);
            }
        }
    
        if ($data['status'] !== "paid") {
            $errorMsg = "Transaction not paid.";
            // error_log($errorMsg);
            file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
            if ($debugMode) {
                echo "Error: " . $errorMsg . "<br>";
            }
            http_response_code(400);
            exit($errorMsg);
        }
    
        // Prevent duplicate processing using txid.
        $txid = $data['txid'];
        if ($this->getall("transactions", "forID = ?", [$txid], fetch: "") > 0) {
            $errorMsg = "Value already assigned (duplicate txid).";
            // error_log($errorMsg);
            file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
            if ($debugMode) {
                echo "Error: " . $errorMsg . "<br>";
            }
            http_response_code(400);
            exit($errorMsg);
        }
    
        // Extract user id from order_id (assumes format "WALLET_{userId}_{timestamp}").
        $parts = explode('_', $data['order_id']);
        if (count($parts) < 3) {
            $errorMsg = "Invalid order_id format.";
            // error_log($errorMsg);
            file_put_contents("payload.txt", "Error: " . $errorMsg . "\n", FILE_APPEND);
            if ($debugMode) {
                echo "Error: " . $errorMsg . "<br>";
            }
            http_response_code(400);
            exit($errorMsg);
        }
        // Use GET parameter if provided, otherwise extract from order_id.
        $userId = htmlspecialchars($_GET['userID'] ?? $parts[1]);
    
        // Retrieve amounts from the payload.
        $merchantAmount = floatval($data['merchant_amount']); // Net amount credited by Cryptomus.
        $commission     = floatval($data['commission']);        // Commission taken by Cryptomus.
    
        // Get the commission sharing percentage from settings.
        // This variable represents the percentage of the commission the owner is willing to share with the user.
        $sharePercentageCommissionOnCrypto = $this->get_settings("share_percentage_commission_on_crypto"); // e.g. 50 means owner shares 50% of commission.
        // If the value is above 100 or below 0, treat it as 0.
        if ($sharePercentageCommissionOnCrypto > 100 || $sharePercentageCommissionOnCrypto < 0) {
            $sharePercentageCommissionOnCrypto = 0;
        }
    
        // Calculate the user's commission refund.
        // This is the percentage of the commission that the owner shares with the user.
        $userCommissionRefund = $commission * ($sharePercentageCommissionOnCrypto / 100);
    
        // Calculate the effective crypto amount credited to the user.
        $userCryptoCredit = $merchantAmount + $userCommissionRefund;
    
        // Convert the effective crypto amount to NGN.
        $nairaAmount = $this->crypto->convertCryptoToNaira($userCryptoCredit, strtoupper($data['currency']));
        $nairaAmount = round($nairaAmount, 2);
        file_put_contents("payload.txt", "User Crypto Credit: {$userCryptoCredit}\nConverted to NGN: {$nairaAmount}\n", FILE_APPEND);
        if ($debugMode) {
            echo "User Crypto Credit: " . $userCryptoCredit . "<br>";
            echo "Converted to NGN: " . $nairaAmount . "<br>";
        }

        $crypto_paymnent = [
            'txid'            => $data['txid'] ?? null,
            'userID'            => $userId ?? null,
            'status'          => $data['status'] ?? null,
            'from_wallet'     => $data['from'] ?? null,
            'amount'          => $data['amount'] ?? null,
            'amount_after_commission' => $userCryptoCredit ?? null,
            'naira_amount'    => $nairaAmount, // Already rounded to 2 decimal places.
            'commission_fee'  => $commission ?? null,
            'exchange_rate'   =>  $this->get_settings("exchange_rate"),
            'payment_type' => $data['type']
        ];
        if($this->getall("crypto_payment", "txid = ?", [$crypto_paymnent['txid']], fetch: "") == 0) $this->quick_insert("crypto_payment", $crypto_paymnent);
        // Update the user's balance in the database.
        $this->credit_debit($userId, $nairaAmount, for:"crypto", forID:$txid);
    }
    
    /**
     * Generate wallet details HTML in a styled Bootstrap card.
     *
     * @param array $wallet Associative array with keys: 'address', 'network', 'currency'.
     * @return string The HTML string for displaying the wallet details.
     */
    public function display_wallet($wallet)
    {
        // Start output buffering to capture HTML content.
        ob_start();
        ?>
    <div class="card mx-auto bg-light">
      <div class="card-body">
        <!-- Wallet Address -->
        <div class="mb-4">
            <h5>Deposit with crypto</h5>
            <small class="mb-0 text-muted">Please scan the QR code below or copy the wallet address to proceed with your transaction.</small>
          <h6 class="text-muted">Wallet Address</h6>
          <p class="fs-4 text-dark"><?php echo $wallet['address'] . $this->copy_text($wallet['address']) ?></p>
        </div>
        <!-- Network and Currency -->
        <div class="row mb-4">
          <div class="col-sm-4 col-4">
            <h6 class="text-muted">Currency</h6>
            <p><?php echo  htmlspecialchars($wallet['currency']); ?></p>
          </div>
          <div class="col-sm-4 col-4">
            <h6 class="text-muted">Network</h6>
            <p><?php echo $wallet['network']; ?></p>
          </div>
          <div class="col-sm-4">
            <h6 class="text-muted">Exchange Rate</h6>
            <!-- <p><small>Not fixed and can change anytime.</small></p> -->
            <p><?php echo $this->money_format($this->get_settings("exchange_rate")); ?></p>
          </div>
        </div>
        <!-- QR Code generated for the wallet address -->
        <div class="text-center">
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?php echo urlencode($wallet['address']); ?>"
            alt="Wallet QR Code" class="img-fluid" style="max-width: 200px;">
            <div>
            <h6 class="mb-3 badge bg-light-danger text-dark">Scan QR Code</h6>
            </div>
        </div>
      </div>
    </div>
    <?php
// Get the HTML from the buffer and clean it.
        $html = ob_get_clean();
        return $html;
    }
}
