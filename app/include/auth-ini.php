<?php 
    require_once 'consts/main.php';
    require_once 'consts/Regex.php';
    require_once 'admin/include/database.php';
    $d = new database;
    require_once "consts/general.php";
    require_once 'content/content.php';
    $c = new content;
    require_once 'functions/autorize.php'; 
    require_once 'consts/user.php';
    $a = new autorize;  
    $reset_form = [
        "email"=>["input_type"=>"hidden", "description"=>"Not your email? <a href='forget_password'>Change email</a>"],
        "code"=>["input_type"=>"number","global_class"=>"w-100", "description"=>"Code sent to your email"],
        "password"=>["input_type"=>"password", "title"=>"New Password"],
        "confirm_password"=>["input_type"=>"password"],
        "input_data"=>[]
    ];
    
    if(isset($_GET['reset']) && $_GET['reset'] != "") {
        $reset_form['input_data']['email'] = base64_decode($_GET['reset']);
    }
?>
