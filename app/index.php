
<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
if ($host !== 'localhost' && $host !== '127.0.0.1' && strpos($host, '::1') === false) {
    echo '<script>window.location.href = "https://web.cruisetechlogs.com/dashboard/";</script>';
    exit;
}

require_once "content/header.php";

if ($page != "") {
    require_once "pages/page-ini.php";
}
require_once "content/footer.php";
