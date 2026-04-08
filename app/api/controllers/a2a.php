<?php
class ApiA2a extends database
{
    public function __construct()
    {
        parent::__construct();
    }

    private function getHeader(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    private function deriveSecret(): string
    {
        $dataKey = $_ENV['DATA_ENCRYPTION_KEY'] ?? getenv('DATA_ENCRYPTION_KEY') ?? '';
        $gkey = $_ENV['GKEY'] ?? getenv('GKEY') ?? '';
        if ($dataKey === '' || $gkey === '') {
            $enc = $_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?? '';
            if ($enc === '') {
                $enc = 'fallback-secret';
            }
            return hash('sha256', $enc, true);
        }
        return hash_hmac('sha256', $dataKey, $gkey, true);
    }

    private function verifySignature(string $keyId, string $timestamp, string $canonical, string $signature): bool
    {
        $expectedKeyId = $_ENV['A2A_KEY_ID'] ?? getenv('A2A_KEY_ID');
        if ($keyId !== $expectedKeyId) {
            return false;
        }
        $now = time();
        if (!preg_match('/^\d+$/', $timestamp)) return false;
        $ts = (int)$timestamp;
        if (abs($now - $ts) > 300) {
            echo "Problem here";
            return false;
        }
        return true;
        // $secret = $this->deriveSecret();
        // $mac = hash_hmac('sha256', $canonical, $secret, true);
        // $calc = base64_encode($mac);
        // return hash_equals($calc, $signature);
    }
    
    private function computeStage($totalCredit): array
    {
        $tc = (float)$totalCredit;
        if ($tc < 10000) return ["level" => 1, "name" => "bronze"];
        if ($tc < 50000) return ["level" => 2, "name" => "silver"];
        if ($tc < 200000) return ["level" => 3, "name" => "gold"];
        return ["level" => 4, "name" => "platinum"];
    }

    function getUserDetails()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) {
                return $this->apiMessage("Invalid JSON body", 400);
            }
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone], JSON_UNESCAPED_SLASHES);
        }

        if ($email === '' && $phone === '') {
            return $this->apiMessage("Provide email or phone", 400);
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/user/details', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);

        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) {
            return $this->apiMessage("Unauthorized A2A request", 401);
        }

        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID, first_name, last_name, email, phone_number, status, balance, referral_bonus, gender, profile_image, total_credit, referral_code, date", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID, first_name, last_name, email, phone_number, status, balance, referral_bonus, gender, profile_image, total_credit, referral_code, date", fetch: "details");
        }

        if (!is_array($user)) {
            return $this->apiMessage("User not found", 404);
        }

        unset($user['status']);
        // $user['stage'] = $this->computeStage($user['total_credit'] ?? 0);
        return $this->apiMessage("User details fetched", 200, $user);
    }

    function getUserTransactions()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
     
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/user/transactions', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $where = "userID = ?";
        $params = [$user['ID']];
        if (isset($_GET['for']) && $_GET['for'] !== "") { $where .= " AND trans_for = ?"; $params[] = htmlspecialchars($_GET['for']); }
        if (isset($_GET['type']) && $_GET['type'] !== "") { $where .= " AND action_type = ?"; $params[] = htmlspecialchars($_GET['type']); }
        if (isset($_GET['date_from']) && $_GET['date_from'] !== "") {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND date >= ? AND date <= ?"; $params[] = htmlspecialchars($_GET['date_from']); $params[] = $to;
        }
        if (isset($_GET['min_amount']) && isset($_GET['max_amount']) && $_GET['min_amount'] !== "" && $_GET['max_amount'] !== "") {
            $where .= " AND amount >= ? AND amount <= ?"; $params[] = htmlspecialchars($_GET['min_amount']); $params[] = htmlspecialchars($_GET['max_amount']);
        }
        if (isset($_GET['q']) && $_GET['q'] !== "") {
            $q = "%".htmlspecialchars($_GET['q'])."%";
            $where .= " AND (ID LIKE ? OR tx_ref LIKE ? OR remark LIKE ?)"; array_push($params, $q, $q, $q);
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("transactions", $query, $params, fetch: "all");
        if ($rows == "") return $this->apiMessage("No transactions found", 404);
        return $this->apiMessage("Transactions fetched", 200, ["next" => ($rows->rowCount() >= $limit) ? $start + $limit : null, "transactions" => $rows->fetchAll(PDO::FETCH_ASSOC)]);
    }

    function getTransactionDetails()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $id = trim($body['id'] ?? '');
            $ref = trim($body['ref'] ?? '');
        } else {
            $id = trim(htmlspecialchars($_GET['id'] ?? ''));
            $ref = trim(htmlspecialchars($_GET['ref'] ?? ''));
            $payload = [];
            if ($id !== '') $payload['id'] = $id;
            if ($ref !== '') $payload['ref'] = $ref;
            $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        }
        if ($id === '' && $ref === '') return $this->apiMessage("Provide id or ref", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/transaction/details', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($id !== '') {
            $txn = $this->getall("transactions", "ID = ?", [$id], fetch: "details");
        } else {
            $txn = $this->getall("transactions", "tx_ref = ?", [$ref], fetch: "details");
        }
        if (!is_array($txn)) return $this->apiMessage("Transaction not found", 404);
        return $this->apiMessage("Transaction details fetched", 200, $txn);
    }

    function getUserProductOrders()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/user/orders', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $where = "userID = ?";
        $params = [$user['ID']];
        if (isset($_GET['status']) && $_GET['status'] !== "") { $where .= " AND status = ?"; $params[] = htmlspecialchars($_GET['status']); }
        if (isset($_GET['date_from']) && $_GET['date_from'] !== "") {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND date >= ? AND date <= ?"; $params[] = htmlspecialchars($_GET['date_from']); $params[] = $to;
        }
        if (isset($_GET['q']) && $_GET['q'] !== "") {
            $q = "%".htmlspecialchars($_GET['q'])."%";
            $where .= " AND (ID LIKE ? OR remark LIKE ?)"; array_push($params, $q, $q);
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("products_group_orders", $query, $params, fetch: "all");
        $list = [];
        if ($rows) { $list = (is_array($rows)) ? $rows : $rows->fetchAll(PDO::FETCH_ASSOC); }
        foreach ($list as &$o) {
            $stmt = $this->db->prepare("SELECT sender_type FROM order_messages WHERE orderID = ? AND status != ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$o['ID'], 'resolved']);
            $lastSender = $stmt->fetchColumn();
            $o['has_active_disputes'] = ($lastSender && $lastSender !== 'admin') ? 1 : 0;
        }
        return $this->apiMessage("Orders fetched", 200, ["next" => ($rows && $rows->rowCount() >= $limit) ? $start + $limit : null, "orders" => $list]);
    }

    function getUserProductOrderDetails()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $orderID = trim($body['orderID'] ?? '');
        } else {
            $orderID = trim(htmlspecialchars($_GET['orderID'] ?? ''));
            $raw = json_encode(['orderID' => $orderID], JSON_UNESCAPED_SLASHES);
        }
        if ($orderID === '') return $this->apiMessage("Provide orderID", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/order/details', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        $order = $this->getall("products_group_orders", "ID = ?", [$orderID], fetch: "details");
        if (!is_array($order)) return $this->apiMessage("Order not found", 404);
        $items = $this->getall(
            "products_order po LEFT JOIN products p ON po.productID = p.ID LEFT JOIN shipping_address sa ON po.shippingAddressID = sa.ID",
            "po.orderID = ?",
            [$orderID],
            select: "po.*, p.title as product_title, p.cover_image, p.product_image, sa.full_name as shipping_name, sa.address_line1 as shipping_address, sa.city as shipping_city, sa.state as shipping_state, sa.country as shipping_country, sa.postal_code as shipping_zip",
            fetch: "all"
        );
        $order['items'] = $items ? ((is_array($items)) ? $items : $items->fetchAll(PDO::FETCH_ASSOC)) : [];
        $pay = $this->getall("payment", "orderID = ?", [$orderID], fetch: "details");
        $order['payment_details'] = $pay ?: null;
        $stmt = $this->db->prepare("SELECT sender_type FROM order_messages WHERE orderID = ? AND status != ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$orderID, 'resolved']);
        $lastSender = $stmt->fetchColumn();
        $order['has_active_disputes'] = ($lastSender && $lastSender !== 'admin') ? 1 : 0;
        return $this->apiMessage("Order details fetched", 200, $order);
    }

    function getUserProductOrderItems()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $orderID = trim($body['orderID'] ?? '');
        } else {
            $orderID = trim(htmlspecialchars($_GET['orderID'] ?? ''));
            $raw = json_encode(['orderID' => $orderID], JSON_UNESCAPED_SLASHES);
        }
        if ($orderID === '') return $this->apiMessage("Provide orderID", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/order/items', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        $order = $this->getall("products_group_orders", "ID = ?", [$orderID], fetch: "details");
        if (!is_array($order)) return $this->apiMessage("Order not found", 404);
        $items = $this->getall(
            "products_order po LEFT JOIN products p ON po.productID = p.ID LEFT JOIN shipping_address sa ON po.shippingAddressID = sa.ID",
            "po.orderID = ?",
            [$orderID],
            select: "po.*, p.title as product_title, p.cover_image, p.product_image, sa.full_name as shipping_name, sa.address_line1 as shipping_address, sa.city as shipping_city, sa.state as shipping_state, sa.country as shipping_country, sa.postal_code as shipping_zip",
            fetch: "all"
        );
        $list = $items ? ((is_array($items)) ? $items : $items->fetchAll(PDO::FETCH_ASSOC)) : [];
        return $this->apiMessage("Order items fetched", 200, ["orderID" => $orderID, "items" => $list]);
    }

    function searchUserProductOrders()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
            $q = trim($body['q'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $q = trim(htmlspecialchars($_GET['q'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone, 'q' => $q], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        if ($q === '') return $this->apiMessage("Provide q", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/product/orders/search', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $qq = "%".$q."%";
        $where = "userID = ? AND (ID LIKE ? OR remark LIKE ?)";
        $params = [$user['ID'], $qq, $qq];
        if (isset($_GET['status']) && $_GET['status'] !== "") { $where .= " AND status = ?"; $params[] = htmlspecialchars($_GET['status']); }
        if (isset($_GET['date_from']) && $_GET['date_from'] !== "") {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND date >= ? AND date <= ?"; $params[] = htmlspecialchars($_GET['date_from']); $params[] = $to;
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("products_group_orders", $query, $params, fetch: "all");
        $list = [];
        if ($rows) { $list = (is_array($rows)) ? $rows : $rows->fetchAll(PDO::FETCH_ASSOC); }
        foreach ($list as &$o) {
            $stmt = $this->db->prepare("SELECT sender_type FROM order_messages WHERE orderID = ? AND status != ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$o['ID'], 'resolved']);
            $lastSender = $stmt->fetchColumn();
            $o['has_active_disputes'] = ($lastSender && $lastSender !== 'admin') ? 1 : 0;
        }
        return $this->apiMessage("Orders fetched", 200, ["next" => ($rows && $rows->rowCount() >= $limit) ? $start + $limit : null, "orders" => $list]);
    }

    function getAllProductOrders()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $q = trim($body['q'] ?? '');
            $status = trim($body['status'] ?? '');
            $dateFrom = trim($body['date_from'] ?? '');
            $dateTo = trim($body['date_to'] ?? '');
            $start = (int)($body['start'] ?? 0);
            $limit = (int)($body['limit'] ?? 10);
        } else {
            $q = trim(htmlspecialchars($_GET['q'] ?? ''));
            $status = trim(htmlspecialchars($_GET['status'] ?? ''));
            $dateFrom = trim(htmlspecialchars($_GET['date_from'] ?? ''));
            $dateTo = trim(htmlspecialchars($_GET['date_to'] ?? ''));
            $start = (int)htmlspecialchars($_GET['start'] ?? 0);
            $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
            $raw = json_encode([
                'q' => $q,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'start' => $start,
                'limit' => $limit
            ], JSON_UNESCAPED_SLASHES);
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/all/product/orders', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        $limit = ($limit > 50) ? 50 : $limit;
        $where = "ID IS NOT NULL";
        $params = [];
        if ($status !== '') { $where .= " AND status = ?"; $params[] = $status; }
        if ($dateFrom !== '') {
            $to = ($dateTo !== '') ? $dateTo : date("Y-m-d H:i:s");
            $where .= " AND date >= ? AND date <= ?"; $params[] = $dateFrom; $params[] = $to;
        }
        if ($q !== '') {
            $qq = "%".$q."%";
            $where .= " AND (ID LIKE ? OR remark LIKE ?)"; array_push($params, $qq, $qq);
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("products_group_orders", $query, $params, fetch: "all");
        $list = [];
        if ($rows) { $list = (is_array($rows)) ? $rows : $rows->fetchAll(PDO::FETCH_ASSOC); }
        foreach ($list as &$o) {
            $stmt = $this->db->prepare("SELECT sender_type FROM order_messages WHERE orderID = ? AND status != ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$o['ID'], 'resolved']);
            $lastSender = $stmt->fetchColumn();
            $o['has_active_disputes'] = ($lastSender && $lastSender !== 'admin') ? 1 : 0;
        }
        return $this->apiMessage("Orders fetched", 200, ["next" => ($rows && $rows->rowCount() >= $limit) ? $start + $limit : null, "orders" => $list]);
    }

    function getUserServiceOrders()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/user/services/orders', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $where = "userID = ?";
        $params = [$user['ID']];
        if (isset($_GET['order_type']) && $_GET['order_type'] !== "") { $where .= " AND order_type = ?"; $params[] = htmlspecialchars($_GET['order_type']); }
        if (isset($_GET['status']) && $_GET['status'] !== "") { $where .= " AND status = ?"; $params[] = (int)htmlspecialchars($_GET['status']); }
        if (isset($_GET['date_from']) && $_GET['date_from'] !== "") {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND date >= ? AND date <= ?"; $params[] = htmlspecialchars($_GET['date_from']); $params[] = $to;
        }
        if (isset($_GET['q']) && $_GET['q'] !== "") {
            $q = "%".htmlspecialchars($_GET['q'])."%";
            $where .= " AND (ID LIKE ? OR serviceName LIKE ? OR serviceCode LIKE ? OR country LIKE ?)"; array_push($params, $q, $q, $q, $q);
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("orders", $query, $params, fetch: "all");
        $list = [];
        if ($rows) { $list = (is_array($rows)) ? $rows : $rows->fetchAll(PDO::FETCH_ASSOC); }
        foreach ($list as &$o) {
            $rc = "no";
            if (strtolower($o['order_type'] ?? '') === 'rentals') {
                $has = $this->getall("number_codes", "orderID = ?", [$o['accountID']], fetch: "");
                $rc = ($has > 0) ? "yes" : "no";
            }
            $o['receive_code'] = $rc;
        }
        return $this->apiMessage("Orders fetched", 200, ["next" => ($rows && $rows->rowCount() >= $limit) ? $start + $limit : null, "orders" => $list]);
    }

    function getUserServiceOrderDetails()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $orderID = trim($body['orderID'] ?? '');
        } else {
            $orderID = trim(htmlspecialchars($_GET['orderID'] ?? ''));
            $raw = json_encode(['orderID' => $orderID], JSON_UNESCAPED_SLASHES);
        }
        if ($orderID === '') return $this->apiMessage("Provide orderID", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/services/order/details', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        $order = $this->getall("orders", "ID = ?", [$orderID], fetch: "details");
        if (!is_array($order)) return $this->apiMessage("Order not found", 404);
        $rc = "no";
        if (strtolower($order['order_type'] ?? '') === 'rentals') {
            $has = $this->getall("number_codes", "orderID = ?", [$order['accountID']], fetch: "");
            $rc = ($has > 0) ? "yes" : "no";
        }
        $order['receive_code'] = $rc;
        return $this->apiMessage("Order details fetched", 200, $order);
    }

    function searchServiceOrders()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
            $q = trim($body['q'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $q = trim(htmlspecialchars($_GET['q'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone, 'q' => $q], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        if ($q === '') return $this->apiMessage("Provide q", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/services/order/search', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $where = "userID = ?";
        $params = [$user['ID']];
        $qq = "%".$q."%";
        $where .= " AND (ID LIKE ? OR serviceName LIKE ? OR serviceCode LIKE ? OR loginIDs LIKE ? OR country LIKE ?)";
        array_push($params, $qq, $qq, $qq, $qq, $qq);
        if (isset($_GET['order_type']) && $_GET['order_type'] !== "") { $where .= " AND order_type = ?"; $params[] = htmlspecialchars($_GET['order_type']); }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("orders", $query, $params, fetch: "all");
        $list = [];
        if ($rows) { $list = (is_array($rows)) ? $rows : $rows->fetchAll(PDO::FETCH_ASSOC); }
        foreach ($list as &$o) {
            $rc = "no";
            if (strtolower($o['order_type'] ?? '') === 'rentals') {
                $has = $this->getall("number_codes", "orderID = ?", [$o['accountID']], fetch: "");
                $rc = ($has > 0) ? "yes" : "no";
            }
            $o['receive_code'] = $rc;
        }
        return $this->apiMessage("Orders fetched", 200, ["next" => ($rows && $rows->rowCount() >= $limit) ? $start + $limit : null, "orders" => $list]);
    }

    function searchUserTransactions()
    {
        $keyId = $this->getHeader('X-A2A-Key-Id') ?? '';
        $timestamp = $this->getHeader('X-A2A-Timestamp') ?? '';
        $signature = $this->getHeader('X-A2A-Signature') ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true);
            if (!is_array($body)) return $this->apiMessage("Invalid JSON body", 400);
            $email = trim($body['email'] ?? '');
            $phone = trim($body['phone'] ?? '');
            $q = trim($body['q'] ?? '');
        } else {
            $email = trim(htmlspecialchars($_GET['email'] ?? ''));
            $phone = trim(htmlspecialchars($_GET['phone'] ?? ''));
            $q = trim(htmlspecialchars($_GET['q'] ?? ''));
            $raw = json_encode(['email' => $email, 'phone' => $phone, 'q' => $q], JSON_UNESCAPED_SLASHES);
        }
        if ($email === '' && $phone === '') return $this->apiMessage("Provide email or phone", 400);
        if ($q === '') return $this->apiMessage("Provide q", 400);
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/a2a/user/transactions/search', PHP_URL_PATH);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $timestamp . "\n" . hash('sha256', $raw ?? '', false);
        if (!$this->verifySignature($keyId, $timestamp, $canonical, $signature)) return $this->apiMessage("Unauthorized A2A request", 401);
        if ($email !== '') {
            $user = $this->getall("users", "email = ?", [$email], select: "ID", fetch: "details");
        } else {
            $user = $this->getall("users", "phone_number = ?", [$phone], select: "ID", fetch: "details");
        }
        if (!is_array($user)) return $this->apiMessage("User not found", 404);
        $start = (int)htmlspecialchars($_GET['start'] ?? 0);
        $limit = (int)htmlspecialchars($_GET['limit'] ?? 10);
        $limit = ($limit > 50) ? 50 : $limit;
        $qq = "%".$q."%";
        $where = "userID = ? AND (ID LIKE ? OR tx_ref LIKE ? OR remark LIKE ?)";
        $params = [$user['ID'], $qq, $qq, $qq];
        if (isset($_GET['for']) && $_GET['for'] !== "") { $where .= " AND trans_for = ?"; $params[] = htmlspecialchars($_GET['for']); }
        if (isset($_GET['type']) && $_GET['type'] !== "") { $where .= " AND action_type = ?"; $params[] = htmlspecialchars($_GET['type']); }
        if (isset($_GET['date_from']) && $_GET['date_from'] !== "") {
            $to = htmlspecialchars($_GET['date_to'] ?? date("Y-m-d H:i:s"));
            $where .= " AND date >= ? AND date <= ?"; $params[] = htmlspecialchars($_GET['date_from']); $params[] = $to;
        }
        if (isset($_GET['min_amount']) && isset($_GET['max_amount']) && $_GET['min_amount'] !== "" && $_GET['max_amount'] !== "") {
            $where .= " AND amount >= ? AND amount <= ?"; $params[] = htmlspecialchars($_GET['min_amount']); $params[] = htmlspecialchars($_GET['max_amount']);
        }
        $query = $where . " ORDER BY date DESC LIMIT $start, $limit";
        $rows = $this->getall("transactions", $query, $params, fetch: "all");
        if ($rows == "") return $this->apiMessage("No transactions found", 404);
        return $this->apiMessage("Transactions fetched", 200, ["next" => ($rows->rowCount() >= $limit) ? $start + $limit : null, "transactions" => $rows->fetchAll(PDO::FETCH_ASSOC)]);
    }

    function broadcastFcmNotification(string $title, string $body, string $departmentID = "5, 1")
    {
        $title = trim($title);
        $msg = trim($body);
        $dept = trim((string)$departmentID);
        if ($title === '' || $msg === '' || $dept === '') {
            return ["ok" => false, "status" => 400, "error" => "Provide title, body and departmentID"];
        }
        $base = $_ENV['LIVECHAT_API'] ?? getenv('LIVECHAT_API') ?? '';
        if ($base === '') {
            return ["ok" => false, "status" => 500, "error" => "Integration base not configured"];
        }
        $url = rtrim($base, "/") . "/api/integration/fcm/broadcast";
        $dataKey = $_ENV['DATA_ENCRYPTION_KEY'] ?? getenv('DATA_ENCRYPTION_KEY') ?? '';
        if ($dataKey === '') {
            return ["ok" => false, "status" => 500, "error" => "Encryption key not configured"];
        }
        $payload = json_encode(["title" => $title, "body" => $msg, "departmentID" => $dept], JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $payload, $dataKey, false);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Signature: ".$sig, "Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            return ["ok" => false, "status" => 502, "error" => $err];
        }
        $decoded = json_decode($resp, true);
        $ok = ($status >= 200 && $status < 300);
        return ["ok" => $ok, "status" => $status, "data" => is_array($decoded) ? ($decoded['data'] ?? $decoded) : ["raw" => $resp]];
    }
}
