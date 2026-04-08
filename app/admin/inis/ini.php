<?php
// define("ROOT", $_SERVER['DOCUMENT_ROOT']."/invest2/");
require_once "include/session.php";
require_once "include/side.php";
require_once "../consts/main.php";
require_once "../include/phpmailer/PHPMailerAutoload.php";
require_once '../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(rootFile);
$dotenv->load();
require_once "include/database.php";
require_once "functions/roles.php";
$r = new roles;
$d = new database;
$user_log = $d->getall("user_logs", "token = ? and expiry_date >= ? and status = ?", [$adminToken, time(), 1]);
if (!is_array($user_log)) {
    $d->message("Unable to identify admin or login session expired", "error");
    $d->loadpage("login2");
    exit();
}

if($user_log['otp'] != "") {
    $user = $d->getall("admins", "ID = ?", [$user_log['userID']], 'email');
    $d->loadpage( "otp?em=".base64_encode($user['email']));
    exit();
}
$admin = $d->getall("admins", "token = ?", [htmlspecialchars($adminSession)]);
if (!is_array($admin)) {
    $d->message("Unable to identify admin", "error");
    exit();
}
$adminID = $admin['ID'];
define("adminID", $adminID);
require_once "../consts/general.php";
require_once "../consts/Regex.php";
require_once "../content/content.php";
require_once "../functions/notifications.php";
require_once "../functions/users.php";
require_once "functions/users.php";
$u = new users;
$c = new content;
$route = "";
$page = "dashboard";
$script = [];
$userID = "admin";
define("ADMINROLE", $r->get_role($admin['roleID']));
if (isset($_GET['p'])) {
    $page = htmlspecialchars($_GET['p']);
}

if (isset($_GET['action'])) {
    $route = htmlspecialchars($_GET['action']);
}

$navs = [
    "home" => [
        "title" => "Home",
        "links" => [
            "dashboard" => [
                "list" => [
                    "a" => "index",
                    "title" => "Dashboard",
                ],
                "icon" => "ti ti-home" // Main icon for Dashboard
            ],
        ]
    ],
    "accountManagement" => [
        "title" => "Account Management",
        "links" => [
            "account" => [
                "new" => [
                    "a" => "index?p=account&action=new",
                    "title" => "Publish Account"
                ],
                "list" => [
                    "a" => "index?p=account&action=list",
                    "title" => "Manage Accounts"
                ],
                "icon" => "ti ti-layout-sidebar" // Main icon for Accounts
            ],
            "platform" => [
                "new" => [
                    "a" => "index?p=platform&action=new",
                    "title" => "Create Platform"
                ],
                "list" => [
                    "a" => "index?p=platform&action=list",
                    "title" => "List of Platforms"
                ],
                "icon" => "ti ti-layout-grid" // Main icon for Platforms
            ],

            "category" => [
                "new" => [
                    "a" => "index?p=category&action=new",
                    "title" => "Create category"
                ],
                "list" => [
                    "a" => "index?p=category&action=list",
                    "title" => "List of categories"
                ],
                "view" => [
                    "a" => "index?p=category&action=view",
                    "title" => "Offline Report"
                ],
                "icon" => "ti ti-layout-grid" // Main icon for Platforms
            ],

          

            "orders" => [
                "list" => [
                    "a" => "index?p=orders",
                    "title" => "Orders",
                ],
                "icon" => "ti ti-list" // Main icon for Dashboard
            ],

           

        ]
    ],

    "Products"=>[
        "title"=>"Gifts Section",
        "links"=>[
             "products" => [
                "list" => [
                    "a" => "index?p=products",
                    "title" => "Manage Products",
                ],
                "new" => [
                    "a" => "index?p=products&action=new",
                    "title" => "Add Product",
                ],
                "pages" => [
                    "category" => [
                        "list" => [
                            "a" => "index?p=products&sub=category",
                            "title" => "Categories",
                        ],
                        "new" => [
                            "a" => "index?p=products&sub=category&action=new",
                            "title" => "Add Category",
                        ],
                        "listout"=>true,
                        "icon" => "ti ti-package"
                    ],
                      "tags" => [
                            "new" => [
                                "a" => "index?p=products&sub=tags&action=new",
                                "title" => "Create tag"
                            ],
                            "list" => [
                                "a" => "index?p=products&sub=tags",
                                "title" => "Tags"
                            ],
                            "listout"=>true,
                            "icon" => "ti ti-tags" // Main icon for Platforms
                        ],
                        "orders" => [
                           
                            "list" => [
                                "a" => "index?p=products&sub=orders",
                                "title" => "Product Orders"
                            ],
                            "listout"=>true,
                            "icon" => "ti ti-list"
                        ],
                        "sections" => [
                            "list" => [
                                "a" => "index?p=products&sub=sections",
                                "title" => "Sections"
                            ],
                            "new" => [
                                "a" => "index?p=products&sub=sections&action=new",
                                "title" => "Add Section"
                            ],
                            "listout"=>true,
                            "icon" => "ti ti-layout-grid"
                        ],
                        "discount" => [
                            "list" => [
                                "a" => "index?p=products&sub=discount",
                                "title" => "Discounts"
                            ],
                            "new" => [
                                "a" => "index?p=products&sub=discount&action=new",
                                "title" => "Add Discount"
                            ],
                            "listout"=>true,
                            "icon" => "ti ti-percentage"
                        ],
                ],
                "icon" => "ti ti-package"
            ],
        ]
    ],
    "userManagement" => [
        "title" => "Users",
        "links" => [
            "users" => [
                "new" => [
                    "a" => "index?p=users&action=new",
                    "title" => "Create",
                    "icon" => "ti ti-user-plus"
                ],
                "list" => [
                    "a" => "index?p=users",
                    "title" => "Manage Users",
                ],
                "transactions" => [
                    "a" => "index?p=users&action=transactions",
                    "title" => "Search Transactions",
                ],
                "icon" => "ti ti-user"
            ],
            "referral"=>[
                "list" => [
                    "a" => "index?p=referral",
                    "title" => "Manage Referrals",
                ],
                "icon" => "ti ti-share"
            ],
            "notification_control" => [
                "new" => [
                    "a" => "index?p=notification_control&action=new",
                    "title" => "Set New Notification",
                    "icon" => "ti ti-user-plus"
                ],
                "list" => [
                    "a" => "index?p=notification_control",
                    "title" => "Manage Notifications",
                ],
               
                "icon" => "ti ti-bell"
            ],

            "deposits" => [
                "list" => [
                    "a" => "index?p=deposit",
                    "title" => "Payments",
                ],
                "icon" => "ti ti-users"
            ]

        ]
    ],

    "Management" => [
        "title" => "Management",
        "links" => [
            "admins" => [
                "new" => [
                    "a" => "index?p=admins&action=new",
                    "title" => "Add New Admin",
                ],
                "list" => [
                    "a" => "index?p=admins",
                    "title" => "Manage Admins",
                ],
                "icon" => "ti ti-users"
            ],
            "settings" => [
                "list" => [
                    "a" => "index?p=settings",
                    "title" => "Manage Settings",
                ],
                "icon" => "ti ti-settings"
            ],

            "profile" => [
                "edit" => [
                    "a" => "index?p=profile&action=edit",
                    "title" => "Edit profile",
                ],
                "icon" => "ti ti-fingerprint"
            ],
            "roles" => [
                "new" => [
                    "a" => "index?p=roles&action=new",
                    "title" => "Create",
                ],
                "list" => [
                    "a" => "index?p=roles",
                    "title" => "Manage",
                ],
                "icon" => "ti ti-key"
            ],
            "cache" => [
                "list" => [
                    "a" => "index?p=cache",
                    "title" => "Manage Cache",
                ],
                "icon" => "ti ti-folder"
            ],

        ]
    ],

    "content" => [
        "title" => "Website Content",
        "links" => [
            "content" => [
                "home" => [
                    "a" => "index?p=content&action=home",
                    "title" => "Home Page",
                ],

                "icon" => "ti ti-home"
            ],
            "features" => [
                "new" => [
                    "a" => "index?p=fetures&action=new",
                    "title" => "Add new feature",
                ],
                "list" => [
                    "a" => "index?p=fetures",
                    "title" => "Manage fetures",
                ],
                "icon" => "ti ti-components"
            ],

            "how_it_works" => [
                "new" => [
                    "a" => "index?p=how_it_works&action=new",
                    "title" => "Add stage",
                ],
                "list" => [
                    "a" => "index?p=how_it_works",
                    "title" => "Manage stages",
                ],
                "icon" => "ti ti-table-filled"
            ],

            "testimonies" => [
                "new" => [
                    "a" => "index?p=testimonies&action=new",
                    "title" => "Add new testimony",
                ],
                "list" => [
                    "a" => "index?p=testimonies",
                    "title" => "Manage testimonies",
                ],
                "icon" => "ti ti-stars"
            ],
        ]
    ],

    "templates" => [
        "title" => "Templates",
        "links" => [
            "email_template" => [
                "list" => [
                    "a" => "index?p=email_template",
                    "title" => "Email Templates",
                ],

                "icon" => "ti ti-inbox"
            ],

        ]
    ],


];
