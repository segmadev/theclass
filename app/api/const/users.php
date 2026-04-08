<?php 
    $user_form = [
        "ID"=>[],
        "email"=>[],
        "username"=>[],
        "token"=>[],
        "isAdmin"=>["is_required"=>false],
        "password"=>[],
        "status"=>["type"=>"select", "options"=>[1=>"active", 0=>"disable"]],
    ];
    // $db->create_table("users", $user_form);
    