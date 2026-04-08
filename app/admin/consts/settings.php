<?php 
    // $logo_path = ROOTFILE."assets/images/logos/";
    $logo_path = "../assets/images/logos/";
    $logo_from = [
        "light_logo"=>["input_type"=>"file", "is_required"=>false, "path"=>$logo_path],
        "dark_logo"=>["input_type"=>"file", "is_required"=>false, "path"=>$logo_path],
        "favicon"=>["input_type"=>"file", "is_required"=>false, "path"=>$logo_path],
    ];
    $logo_from['input_data'] = $s->getdata($logo_from);
    $settings_form = [
        "company_name"=>[],
        "website_url"=>[],
        "tiny_API"=>["is_required"=>false, "input_type"=>"password"],
        "account_preview"=>["type"=>"select", "Title"=>"Allow account preview", "options"=>["1"=>"Yes", "0"=>"No"]],
        "support_email"=>["input_type"=>"email"],
        "phone_number"=>["input_ype"=>"tel"],
        "company_address"=>["type"=>"textarea"],
        "default_currency"=>[],
        "welcome_note"=>["type"=>"textarea", "description"=>"Welcome note will display to new users who login to dashboard for the first time.", "global_class"=>"w-100"],
        "live_chat_widget"=>["type"=>"textarea", "global_class"=>"w-100"],
       ];
       $settings_form['input_data'] = $s->getdata($settings_form);
       $settings_form['input_data']['tiny_API'] = "--placeholder";
       $settings_help =["help_title"=>[], "get_help"=>["type"=>"textarea", "id"=>"richtext_help", "global_class"=>"w-100"]];
       $s->create_settings($settings_help);
       $settings_help['input_data'] = $s->getdata($settings_help);
       $settings_backup = [
            "dropbox_API"=>["input_type"=>"password", "is_required"=>false],
            "backup_interval"=>["input_type"=>"number", "title"=>"Backup Interval (in hour)", "description"=>"Backup Interval in hour"],
        ];
        $settings_backup['input_data'] = $s->getdata($settings_backup);
        $settings_backup['input_data']['dropbox_API'] = "--placeholder";
        $s->create_settings($settings_backup);

        $settings_social_media = [
            "telegram_link" => ["is_required"=>false],
            "whatsapp_channel_link" => ["is_required"=>false],
            "facebook_link" => ["is_required"=>false],
            "instagram_link" => ["is_required"=>false],
            "x_link" => ["is_required"=>false],
            "tiktok_link" => ["is_required"=>false],
        ];
        
        $settings_social_media['input_data'] = $s->getdata($settings_social_media);
        $settings_seo = [
            "seo_title" => ["is_required"=>false],
            "seo_description" => ["is_required"=>false],
            "seo_tags" => ["is_required"=>false],
        ];
        
        $settings_seo['input_data'] = $s->getdata($settings_seo);
   
    // var_dump($settings_form);
    $settings_deposit_form = [
        "offline_buyers"=>["type"=>"textarea", "global_class"=>"col-md-12", "description"=>"User IDs of people who can buy accounts in offline category"],
        
        "paymentpoint_api_key"=>["input_type"=>"password", "is_required"=>false],
        "paymentpoint_secret_key"=>["input_type"=>"password", "is_required"=>false],
        "paymentpoint_businessId"=>["input_type"=>"password", "is_required"=>false],
        "monipfy_contract_code"=>["input_type"=>"password", "is_required"=>false],
        "monipfy_api_key"=>["input_type"=>"password", "is_required"=>false],
        "monipfy_secret_key"=>["input_type"=>"password", "is_required"=>false],
        "flutterwave_public_key"=>["input_type"=>"password", "is_required"=>false],
        "flutterwave_secret_key"=>["input_type"=>"password", "is_required"=>false],
        "flutterwave_encyption_key"=>["input_type"=>"password", "is_required"=>false],
        "korapay_public_key"=>["input_type"=>"password", "is_required"=>false],
        "korapay_secret_key"=>["input_type"=>"password", "is_required"=>false],

        "bvn"=>["input_type"=>"number", "is_required"=>false],
        "min_deposit"=>["input_type"=>"number"],
        "max_deposit"=>["input_type"=>"number", "is_required"=>false],
        "exchange_rate"=>[],
        "exchange_rate_update_interval"=>["input_type"=>"number","atb"=>'step="0.0001"', "description"=>"value in minutes"],
        "fix_exchange_rate"=>["type"=>"select", "options"=>["yes"=>"Yes", "no"=>"No"]],
        "exchange_rate_API"=>["input_type"=>"password", "is_required"=>false, "description"=>"You can get your API key <a target='_BLANK' href='https://www.exchangerate-api.com'>here</a>"],
        
        "cryptomus_ID"=>["input_type"=>"password", "is_required"=>false, "title"=>"cryptomus merchant ID"],
        "cryptomus_api"=>["input_type"=>"password", "is_required"=>false, "title"=>"cryptomus API Key"],
        "share_percentage_commission_on_crypto"=>["input_type"=>"number", "description"=>"Min 0 and max should be 100"],
        "input_data"=>array_merge(["cryptomus_ID"=>"--placeholder", "cryptomus_api"=>"--placeholder", "exchange_rate_API"=>"--placeholder", 
        "paymentpoint_api_key"=>"--placeholder", 
        "paymentpoint_secret_key"=>"--placeholder", 
        "paymentpoint_businessId"=>"--placeholder", 

        "monipfy_contract_code"=>"--placeholder", 
        "monipfy_api_key"=>"--placeholder", 
        "monipfy_secret_key"=>"--placeholder", 
        "flutterwave_public_key"=>"--placeholder", 
        "korapay_public_key"=>"--placeholder", 
        "korapay_secret_key"=>"--placeholder", 
        "flutterwave_secret_key"=>"--placeholder", "flutterwave_encyption_key"=>"--placeholder", "bvn"=>"00000000000"], $s->getdata(["offline_buyers"=>[], "min_deposit"=>[], "exchange_rate_update_interval"=>[], "max_deposit"=>[], "fix_exchange_rate"=>[], "exchange_rate"=>[],  "share_percentage_commission_on_crypto"=>[]])),
    ];

    $term_and_policy_condition = [
        "terms_and_conditions"=>["type"=>"textarea", "id"=>"richtext", "global_class"=>"col-md-12"],
        "policy"=>["type"=>"textarea", "id"=>"richtext2", "global_class"=>"col-md-12"],
    ];
    $term_and_policy_condition['input_data'] = $s->getdata($term_and_policy_condition);

    $admin = $d->getall("admins", "ID = ?", [$adminID]);
    if(!is_array($admin)) {exit();}
    $admin_account = [
        "email"=>["input_type"=>"email"],
        "current_password"=>["input_type"=>"password"],
        "password"=>["title"=>"Change Password","input_type"=>"password", "is_required"=>false],
        "confirm_password"=>["title"=>"Re-type Change Password", "input_type"=>"password", "is_required"=>false],
        "input_data"=>["email"=>$admin['email']],
    ];

    $rentals_settings = [
        "rental_number_expire_time"=>["input_type"=>"number","title"=>"Default rental number expire time (In mins)", "description"=>"How long should it take for rented numbers to expire, value in mins."],
        "rental_number_expire_time_daisysms"=>["input_type"=>"number","title"=>"Daisysms rental number expire time (In mins)", "description"=>"How long should it take for rented numbers to expire, value in mins."],
        "rental_number_expire_time_sms_activation"=>["input_type"=>"number","title"=>"SMS Activation rental number expire time (In mins)", "description"=>"How long should it take for rented numbers to expire, value in mins."],
        "rental_number_expire_time_sms_bower"=>["input_type"=>"number","title"=>"SMS Bower rental number expire time (In mins)", "description"=>"How long should it take for rented numbers to expire, value in mins."],
        "rentals_API"=>["input_type"=>"password", "title"=>"Daisysms API Key", "is_required"=>false],
        "anosim_API"=>["input_type"=>"password", "title"=>"Anosim API Key", "is_required"=>false],
        "getatext_api_key"=>["input_type"=>"password", "title"=>"Getatext API Key", "is_required"=>false],
        "sms_bower_API"=>["input_type"=>"password", "title"=>"sms bower API API Key", "is_required"=>false],
        "sms_activation_API"=>["input_type"=>"password", "title"=>"sms activation service API API Key", "is_required"=>false],
        "sms_activate_two_API"=>["input_type"=>"password", "title"=>"sms activate API Key", "is_required"=>false],
        
        "majorphones_api_key"=>["input_type"=>"password", "title"=>"Major phones API Key", "is_required"=>false],
        
        "nonvoipusnumber_email"=>["input_type"=>"password", "is_required"=>false],
        "nonvoipusnumber_password"=>["input_type"=>"password", "is_required"=>false],
        "added_value_amount"=>["title"=>"Added Value Amount Default", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_daisysms_short_term"=>["title"=>"Added Value Amount for dailysms (In NGN)", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        
        "added_value_amount_anosim_short_term"=>["title"=>"Added Value Amount for Anosim <b> Short term. </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_24"=>["title"=>"Added Value Amount for Anosim <b> 1 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_168"=>["title"=>"Added Value Amount for Anosim <b> 7 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_720"=>["title"=>"Added Value Amount for Anosim <b> 30 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_2160"=>["title"=>"Added Value Amount for Anosim <b> 90 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_4320"=>["title"=>"Added Value Amount for Anosim <b> 180 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_anosim_long_term_8640"=>["title"=>"Added Value Amount for Anosim <b> 365 day </b>", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        
        "added_value_amount_sms_bower_short_term"=>["title"=>"Added Value Amount for smsbower.net (In NGN)", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_sms_activate_two_short_term"=>["title"=>"Added Value Amount for sms-activate.io (In NGN)", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_sms_activation_short_term"=>["title"=>"Added Value Amount for sms activation service (In NGN)", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_nonvoipusnumber_short_term"=>["title"=>"Added Value Amount for nonvoipusnumber short (In NGN)", "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_nonvoipusnumber_long_term"=>[ "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_nonvoipusnumber_3days"=>[ "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "added_value_amount_sms_bower_email"=>[ "description"=>"Amount to be added on the rental's API price. Value in <b class='text-danger'>NGN</b> "],
        "notification_email"=>["input_type"=>"email", "description"=>"Active email to get notification on low balance"],
        "notify_low_balance_amount"=>["is_required"=>false, "description"=>"At what balance do you want to get notifications. Set to (0)zero to turn off notifications"],
        "notify_low_balance_amount_sms_activate_two"=>["is_required"=>false, "description"=>"At what balance do you want to get notifications."]
    ];
    $rentals_settings['input_data'] = $s->getdata($rentals_settings);
    $rentals_settings['input_data']['rentals_API'] = "--placeholder";
    $rentals_settings['input_data']['exchange_rate_API'] = '--placeholder';
    $rentals_settings['input_data']['nonvoipusnumber_email'] = '--placeholder';
    $rentals_settings['input_data']['nonvoipusnumber_password'] = '--placeholder';
    $rentals_settings['input_data']['sms_activation_API'] = '--placeholder';
    $rentals_settings['input_data']['sms_bower_API'] = '--placeholder';
    $rentals_settings['input_data']['anosim_API'] = '--placeholder';
    $rentals_settings['input_data']['getatext_api_key'] = '--placeholder';
    $rentals_settings['input_data']['majorphones_api_key'] = '--placeholder';