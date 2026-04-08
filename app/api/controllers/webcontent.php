<?php 
    class ApiWebcontent extends database
    {
        public function __construct()
        {
            // Call parent constructor to set the name
            parent::__construct();
        }

        function test() {
            $data = [
                "message" => "This is a test message",
                "status" => "success"
            ];
            return $this->apiMessage("Test data fetched", 200, $data);
        }
        function get_helper() {
            $title = $this->get_settings("help_title") ?? "Help";
            $descriptions = htmlspecialchars_decode($this->get_settings("get_help")) ?? "<p>We are here to help you with any issues you may have. Please contact us through our support channels.</p>";
            return $this->apiMessage("Help Data fetched", 200, ["help"=> [
                "title" => $title,
                "description" => $descriptions,
            ]]);
        }

        function get_stages() {
            $stages = json_decode($this->get_settings("discounts"), true);
            if (empty($stages)) {
                return $this->apiMessage("No stages found", 404, []);
            }
            return $this->apiMessage("Help Data fetched", 200, ["stages"=>  $stages]);
        }

        function get_live_chat() {
            $live_chat = $this->get_settings("live_chat_widget");
            if (empty($live_chat)) {
                return $this->apiMessage("Live chat not available", 404, []);
            }
            return $this->apiMessage("Live chat data fetched", 200, ["live_chat"=>  $live_chat]);
        }

        function get_terms(){
            return $this->apiMessage("Terms and conditions fetched", 200, ["content"=>htmlspecialchars_decode($this->get_settings("terms_and_conditions"))]);
        }
        function get_policy(){
            return $this->apiMessage("Policy fetched", 200, ["content"=>htmlspecialchars_decode($this->get_settings("policy"))]);
        }

        function referral_settings() {
            $referral_settings = (array)json_decode(htmlspecialchars_decode($this->get_settings("referral_settings")));
            if(!is_array($referral_settings))  return $this->apiMessage("No settings found", 400);
            unset($referral_settings['credit'], $referral_settings['status'], $referral_settings['usage']);
            $referral_settings['min_withdraw_to_balance'] = $this->get_settings("min_withdraw_to_balance");
            return $this->apiMessage("Referral settings fetched", 200, $referral_settings);
        }
    }