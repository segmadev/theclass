<?php
    $admin_from = [
        "ID"=>[],
        "username"=>[],
        "email"=>[],
        "token"=>[],
        "password"=>[],
    ];
    try {
        $db->create_table("admin", $admin_from);
    } catch (\Throwable $th) {
        //throw $th;
    }
    

    $userlogin = "users";