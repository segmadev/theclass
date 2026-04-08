<?php
class autorize extends database
{
    public function signup($data)
    {
        // Verify the CAPTCHA with Cloudflare
        $captchaResponse = $this->verify_captcha();
        if (!$captchaResponse) {
            $this->message("Robot verification failed. Please try again.", "error");
            return null;
        }    
        $_POST['ID'] = uniqid();
        $info = $this->validate_form($data);
        die(var_dump($info));
        if (!is_array($info)) {
            return null;
        }
        if($info['confrim_password'] != $info['password']) {
            echo $this->message("Password and confirm password do not match", "error");
            return null;
        }
        if(!$this->isValidName($info['first_name'])) {
            echo $this->message("Invalid First name.", "error");
            return null;
        }

        if(!$this->isValidName($info['last_name'])) {
            echo $this->message("Invalid Last name.", "error");
            return null;
        }

        $check = $this->getall("users", "email = ?", [$info['email']]);
        if ($check > 0) {
            echo $this->message("User with email already exit.", "error");
            return null;
        }

        // check for phone number
        // make sure phone number is not empty and 11 digits
        if (!preg_match('/^\d{11}$/', $info['phone_number'])) {
            echo $this->message("Phone number must be 11 digits", "error");
            return null;
        }
        if (!empty($info['phone_number'])) {
            $check = $this->getall("users", "phone_number = ?", [$info['phone_number']]);
            if ($check > 0) {
                echo $this->message("User with phone number already exit.", "error");
                return null;
            }
        }

        $info['password'] = password_hash(trim($info['password']), PASSWORD_DEFAULT);
        unset($info['confrim_password']);
        $info['ip_address'] = $this->get_visitor_details()['ip_address'];
        // check referral code if active.
        // var_dump($info['referral_code']);
        // exit();
        if (!empty($info['referral_code']) && $this->getall("referrals", "referral_code = ? and status = ?", [$info['referral_code'], "active"], fetch: "") == 0) {
            $this->message("You referral code is no more active or doesn't exist", "error");
            return false;
        }
        $insert = $this->quick_insert("users", $info);
        if ($insert) {
            if (!empty($info['referral_code'])) {
                $this->apply_referral_code(htmlspecialchars($info['ID']), $info['referral_code']);
            }
            session_start();
            // session_unset();
            $expiry = strtotime('+6 months'); // Calculate the expiry time for 3 months from now
            // session_set_cookie_params($expiry); // Set the session cookie expiry time
            // session_start();

            // $d->updateadmintoken($value['ID'], "users");
            $_SESSION['userSession'] = htmlspecialchars($info['ID']);
            if (!$this->set_cookies("userSession", htmlspecialchars($info['ID']), time() + 60 * 60 * 24 * 30)) {
                echo $this->message("Your account was created successfuly. But we are having issues logging you in. <a href='login'>Click here</a> to login.", "error");
                return;
            }
            $actInfo = ["userID" => $info['ID'], "date_time" => date("Y-m-d H:i:s"), "action_name" => "Registration", "description" => "Account Registration."];
            $this->new_activity($actInfo);
            $return = [
                "message" => ["Success", "Account Created", "success"],
                "function" => ["loadpage", "data" => ["index", "null"]],
            ];
            return json_encode($return);
        }
    }

    function isValidName($name) {
        // Check if the name contains only letters, spaces, or hyphens
        $name = trim($name);
        return preg_match("/^[a-zA-Z'-]{1,30}$/", $name);
    }

    private function apply_referral_code($userID, $code)
    {
        // check if active
        // insert data into DB as pending

        // check if active
        if ($this->getall("referrals", "referral_code = ? and status = ?", [$code, "active"], fetch: "") == 0) {
            $this->message("Referral code not active anymore", 'error');
            return false;
        }
        $info = ["userID" => $userID, "referral_code" => $code];
        if ($this->quick_insert("referral_allocation", $info)) {
            return true;
        }

    }

    private function check_referral_status()
    {

    }

    public function set_cookies($name, $value, $time = null)
    {
        if ($time == null) {$time = time() + 60 * 2;} // current time + 1 hour
        $secureOnly = true; // Set the cookie to be transmitted only over HTTPS
        if (setcookie($name, $value, $time, "/", "", $secureOnly, true)) {return true;} else {return false;};

    }
    public function signin()
    {
        $d = new database;
        $email = htmlspecialchars($_POST['email']);
        $password = htmlspecialchars($_POST['password']);
    // Verify the CAPTCHA with Cloudflare
    $captchaResponse = $this->verify_captcha();
    if (!$captchaResponse) {
        $d->message("Robot verification failed. Please try again.", "error");
        return;
    }

        if (!empty($email) && !empty($password)) {
            $value = $d->getall("users", "email = ?", [$email]);
            if (is_array($value)) {
                if (password_verify($password, $value['password'])) {
                    if(isset($_POST['referral_code']) && $this->getall("users", "referral_code = ?", [htmlspecialchars($_GET['referral'])], fetch: "") == 0) {
                            echo $this->message("Referral code is not vaild", "error");    
                        }
                    if (htmlspecialchars($value['status']) == 0) {
                        $reason = "";
                        if ($value['reason'] != "") {
                            $reason = htmlspecialchars($value['reason']);
                        }
                        $d->message("We're sorry, your account has been blocked. <br> <b>Reason: </b> " . $reason, "error");
                    } else {
                        // session_start();
                        // $_SESSION['userSession'] = htmlspecialchars($value['ID']);
                        if (isset($_POST['urlgoto']) && !empty($_POST['urlgoto'])) {
                            $urlgoto = str_replace("/localhost", "", $_POST['urlgoto']);
                        }
                        $urlgoto = "index";
                        // reson here
                        session_start();
                        session_unset();
                        // $d->updateadmintoken($value['ID'], "users");
                        if (!$this->set_token(htmlspecialchars($value['ID']))) {
                            echo $this->message("Sorry we are having issues logging you in. Please try again", "error");
                            return;
                        }
                        $actInfo = ["userID" => $value['ID'], "date_time" => date("Y-m-d H:i:s"), "action_name" => "Login", "description" => "Account login access."];
                        $this->new_activity($actInfo);
                        // $d->message("Account logged in Sucessfully <a href='index.php'>Click here to proceed.</a>", "error");
                        if(isset($_POST['referral_code'])) {
                            $this->assign_referral($userID, htmlspecialchars($_POST['referral_code']));
                        }
                        $return = [
                            "message" => ["Success", "Account Logged in", "success"],
                            "function" => ["loadpage", "data" => ["$urlgoto", "null"]],
                        ];
                        return json_encode($return);
                    }
                } else {
                    $d->message("Password Incorrect", "error");
                }
            } else {
                $d->message("Email doesn't exist.", "error");
            }
        } else {
            $d->message("Make sure you enter your email and password", "error");
        }
    }


    private function set_token($userID)
    {
        $token = $this->randcar(rand(20, 40));
        if (!$this->create_table("users", ["token" => []], isCreate: false)) {
            return false;
        }

        $where = "ID ='$userID'";
        if (!$this->update("users", ["token" => $token], $where)) {
            return false;
        }

        if (!$this->set_cookies("userTK", $token, time() + 60 * 60 * 24 * 30)) {
            return false;
        }

        $_SESSION['userSession'] = htmlspecialchars($userID);
        return true;
    }

    public function sendotp()
    {
        $d = new database;
        if (!isset($_POST['email']) || $_POST['email'] == '') {
            return $d->message("Please enter email address", "error");
        }
        $email = htmlspecialchars($_POST['email']);
        $data = $d->getall("users", "email = ?", [$email]);
        if (!is_array($data)) {
            return $d->message("Email not found check and try again", "error");
        }
        $id = $data['ID'];
        $reset = mt_rand(000000, 99999);
        $hashreset = password_hash($reset, PASSWORD_DEFAULT);
        $where = "ID ='$id'";
        $update = $d->update("users", ["reset" => $hashreset], $where);
        if ($update) {
            $smessage = $this->get_email_template("forget_password")['template'];
            $smessage = $this->replace_word(['${first_name}' => $data['first_name'], '${last_name}' => $data['last_name'], '${message_here}' => $reset, '${website_url}' => $this->get_settings("website_url")], $smessage);
            $sendmail = $d->smtpmailer($data['email'], "Password Reset ($reset)", $smessage);
            if ($sendmail) {
                return $d->loadpage("forget_password?reset=".base64_encode($data['email']), true);
            }else{
                return $d->message("Failed to send email. Please try again later.", "error");
            }
        }
        return $d->message("Error", "error");
    }

    public function resetpassword($from_data)
    {
        $d = new database;
        $value = $d->validate_form($from_data);
        if (is_array($value)) {
            if($value['password'] != $_POST['confirm_password']) {
                return $this->message("Password and confrim password do not match", "error");
            }
            $email = $value['email'];
            $data = $d->getall("users", "email = ?", [$email]);
            if (password_verify($value['code'], $data['reset'])) {
                $newpassword = password_hash($value['password'], PASSWORD_DEFAULT);
                $where = "email ='$email'";
                $update = $d->update("users", ["password" => $newpassword, "reset" => ""], $where, message: "Password Reset successfully. You can now <a class='text-dark' href='login'>Login here</a> with your new password");
            } else {
                $d->message("Incorrect Code", "error");
            }
        }
    }

    
}
