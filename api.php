<?php
// api.php - Updated for InfinityFree MySQL with spare parts functionality and updates field
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit();
}

$action = $_GET['action'] ?? '';

function generateOrderNumber($db) {
    $query = "SELECT MAX(CAST(SUBSTRING(order_number, 3) AS INTEGER)) as max_num FROM repair_orders";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $nextNumber = 1;
    if ($row['max_num']) {
        $nextNumber = $row['max_num'] + 1;
    }

    return 'CC' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
}

function validateOrderData($data) {
    $errors = [];

    $customerName = $data['customerName'] ?? $data['customer_name'] ?? '';
    $phone        = $data['phone']        ?? '';
    $device       = $data['device']       ?? '';
    $issue        = $data['issue']        ?? '';

    if (empty($customerName)) $errors[] = "Customer name is required";
    if (empty($phone))        $errors[] = "Phone number is required";
    if (empty($device))       $errors[] = "Device is required";
    if (empty($issue))        $errors[] = "Issue description is required";

    return $errors;
}

function validateSparePartData($data) {
    $errors = [];

    $partNumber = $data['partNumber'] ?? $data['part_number'] ?? '';
    $partName   = $data['partName']   ?? $data['part_name']   ?? '';

    if (empty($partNumber)) $errors[] = "Part number is required";
    if (empty($partName))   $errors[] = "Part name is required";

    return $errors;
}

switch ($action) {

    // ── get_customers — supports live search ──────────────────────────────────
    case 'get_customers':
        $search = $_GET['search'] ?? '';

        // Build customer query with optional search filter
        $custQuery  = "SELECT DISTINCT customer_name, phone, email
                       FROM repair_orders
                       WHERE customer_name IS NOT NULL AND customer_name != ''";
        $custParams = [];

        if ($search) {
            $custQuery .= " AND (customer_name LIKE :search OR phone LIKE :search)";
            $custParams[':search'] = "%$search%";
        }

        $custQuery .= " ORDER BY customer_name ASC";

        $custStmt = $db->prepare($custQuery);
        $custStmt->execute($custParams);
        $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);

        // Distinct devices for the device dropdown (no search filter needed here)
        $devQuery = "SELECT DISTINCT device
                     FROM repair_orders
                     WHERE device IS NOT NULL AND device != ''
                     ORDER BY device ASC";
        $devStmt = $db->prepare($devQuery);
        $devStmt->execute();
        $devices = array_column($devStmt->fetchAll(PDO::FETCH_ASSOC), 'device');

        echo json_encode([
            'success' => true,
            'data'    => $customers,
            'devices' => $devices
        ]);
        break;

    // ── get_orders ────────────────────────────────────────────────────────────
    case 'get_orders':
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';

        $query  = "SELECT * FROM repair_orders WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND (customer_name LIKE :search OR device LIKE :search OR order_number LIKE :search)";
            $params[':search'] = "%$search%";
        }

        if ($status && $status != 'all') {
            $query .= " AND status = :status";
            $params[':status'] = $status;
        }

        $query .= " ORDER BY created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $order['images']            = $order['images']             ? json_decode($order['images'], true)             : [];
            $order['componentsChanged'] = $order['components_changed'] ? json_decode($order['components_changed'], true) : [];
            $order['updates']           = $order['updates']            ? json_decode($order['updates'], true)            : [];
            unset($order['components_changed']);
        }

        echo json_encode(['success' => true, 'data' => $orders]);
        break;

    // ── get_spare_parts ───────────────────────────────────────────────────────
    case 'get_spare_parts':
        $search = $_GET['search'] ?? '';

        $query  = "SELECT * FROM spare_parts WHERE 1=1";
        $params = [];

        if ($search) {
            $query .= " AND (part_number LIKE :search OR part_name LIKE :search OR description LIKE :search OR category LIKE :search)";
            $params[':search'] = "%$search%";
        }

        $query .= " ORDER BY part_name ASC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);

        $spareParts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $spareParts]);
        break;

    // ── get_spare_part ────────────────────────────────────────────────────────
    case 'get_spare_part':
        $id          = $_GET['id']          ?? 0;
        $part_number = $_GET['part_number'] ?? '';

        $query = "SELECT * FROM spare_parts WHERE id = :id OR part_number = :part_number";
        $stmt  = $db->prepare($query);
        $stmt->execute([':id' => $id, ':part_number' => $part_number]);
        $sparePart = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sparePart) {
            echo json_encode(['success' => true, 'data' => $sparePart]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Spare part not found']);
        }
        break;

    // ── get_order ─────────────────────────────────────────────────────────────
    case 'get_order':
        $id           = $_GET['id']           ?? 0;
        $order_number = $_GET['order_number'] ?? '';

        $query = "SELECT * FROM repair_orders WHERE id = :id OR order_number = :order_number";
        $stmt  = $db->prepare($query);
        $stmt->execute([':id' => $id, ':order_number' => $order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($order) {
            $order['images']            = $order['images']             ? json_decode($order['images'], true)             : [];
            $order['componentsChanged'] = $order['components_changed'] ? json_decode($order['components_changed'], true) : [];
            $order['updates']           = $order['updates']            ? json_decode($order['updates'], true)            : [];
            unset($order['components_changed']);
            echo json_encode(['success' => true, 'data' => $order]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
        break;

    // ── create_order ──────────────────────────────────────────────────────────
    case 'create_order':
        $data = json_decode(file_get_contents('php://input'), true);

        $errors = validateOrderData($data);
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            break;
        }

        $order_number      = generateOrderNumber($db);
        $customerName      = $data['customerName']     ?? $data['customer_name']   ?? '';
        $phone             = $data['phone']             ?? '';
        $email             = $data['email']             ?? '';
        $device            = $data['device']            ?? '';
        $issue             = $data['issue']             ?? '';
        $estimatedCost     = $data['estimatedCost']     ?? $data['estimated_cost'] ?? 0;
        $status            = $data['status']            ?? 'in-progress';
        $dateReceived      = $data['dateReceived']      ?? $data['date_received']  ?? date('Y-m-d');
        $endDate           = $data['endDate']           ?? $data['end_date']       ?? null;
        $images            = $data['images']            ?? [];
        $componentsChanged = $data['componentsChanged'] ?? [];
        $updates           = $data['updates']           ?? [];

        $query = "INSERT INTO repair_orders (
            order_number, customer_name, phone, email, device, issue,
            estimated_cost, status, date_received, end_date, images, components_changed, updates
        ) VALUES (
            :order_number, :customer_name, :phone, :email, :device, :issue,
            :estimated_cost, :status, :date_received, :end_date, :images, :components_changed, :updates
        )";

        $stmt   = $db->prepare($query);
        $result = $stmt->execute([
            ':order_number'       => $order_number,
            ':customer_name'      => $customerName,
            ':phone'              => $phone,
            ':email'              => $email,
            ':device'             => $device,
            ':issue'              => $issue,
            ':estimated_cost'     => $estimatedCost,
            ':status'             => $status,
            ':date_received'      => $dateReceived,
            ':end_date'           => $endDate,
            ':images'             => json_encode($images),
            ':components_changed' => json_encode($componentsChanged),
            ':updates'            => json_encode($updates)
        ]);

        if ($result) {
            echo json_encode([
                'success'      => true,
                'message'      => 'Order created successfully',
                'order_number' => $order_number,
                'id'           => $db->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create order: ' . implode(', ', $stmt->errorInfo())]);
        }
        break;

    // ── create_spare_part ─────────────────────────────────────────────────────
    case 'create_spare_part':
        $data = json_decode(file_get_contents('php://input'), true);

        $errors = validateSparePartData($data);
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
            break;
        }

        $partNumber  = $data['partNumber']  ?? $data['part_number']  ?? '';
        $partName    = $data['partName']    ?? $data['part_name']    ?? '';
        $description = $data['description'] ?? '';
        $quantity    = $data['quantity']    ?? 0;
        $unitPrice   = $data['unitPrice']   ?? $data['unit_price']   ?? 0;
        $supplier    = $data['supplier']    ?? '';
        $location    = $data['location']    ?? '';
        $minQuantity = $data['minQuantity'] ?? $data['min_quantity'] ?? 5;
        $category    = $data['category']    ?? 'Other';

        $checkStmt = $db->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number");
        $checkStmt->execute([':part_number' => $partNumber]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Part number already exists']);
            break;
        }

        $query = "INSERT INTO spare_parts (
            part_number, part_name, description, quantity, unit_price,
            supplier, location, min_quantity, category
        ) VALUES (
            :part_number, :part_name, :description, :quantity, :unit_price,
            :supplier, :location, :min_quantity, :category
        )";

        $stmt   = $db->prepare($query);
        $result = $stmt->execute([
            ':part_number'  => $partNumber,
            ':part_name'    => $partName,
            ':description'  => $description,
            ':quantity'     => $quantity,
            ':unit_price'   => $unitPrice,
            ':supplier'     => $supplier,
            ':location'     => $location,
            ':min_quantity' => $minQuantity,
            ':category'     => $category
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Spare part created successfully', 'id' => $db->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create spare part: ' . implode(', ', $stmt->errorInfo())]);
        }
        break;

    // ── update_order ──────────────────────────────────────────────────────────
    case 'update_order':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = $data['id'] ?? 0;

        if (!$id) { echo json_encode(['success' => false, 'message' => 'Order ID is required']); break; }

        $errors = validateOrderData($data);
        if (!empty($errors)) { echo json_encode(['success' => false, 'message' => implode(', ', $errors)]); break; }

        $customerName      = $data['customerName']     ?? $data['customer_name']   ?? '';
        $phone             = $data['phone']             ?? '';
        $email             = $data['email']             ?? '';
        $device            = $data['device']            ?? '';
        $issue             = $data['issue']             ?? '';
        $estimatedCost     = $data['estimatedCost']     ?? $data['estimated_cost'] ?? 0;
        $status            = $data['status']            ?? 'in-progress';
        $dateReceived      = $data['dateReceived']      ?? $data['date_received']  ?? date('Y-m-d');
        $endDate           = $data['endDate']           ?? $data['end_date']       ?? null;
        $images            = $data['images']            ?? [];
        $componentsChanged = $data['componentsChanged'] ?? [];
        $updates           = $data['updates']           ?? [];

        $query = "UPDATE repair_orders SET
            customer_name = :customer_name, phone = :phone, email = :email,
            device = :device, issue = :issue, estimated_cost = :estimated_cost,
            status = :status, date_received = :date_received, end_date = :end_date,
            images = :images, components_changed = :components_changed, updates = :updates,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

        $stmt   = $db->prepare($query);
        $result = $stmt->execute([
            ':id'                 => $id,
            ':customer_name'      => $customerName,
            ':phone'              => $phone,
            ':email'              => $email,
            ':device'             => $device,
            ':issue'              => $issue,
            ':estimated_cost'     => $estimatedCost,
            ':status'             => $status,
            ':date_received'      => $dateReceived,
            ':end_date'           => $endDate,
            ':images'             => json_encode($images),
            ':components_changed' => json_encode($componentsChanged),
            ':updates'            => json_encode($updates)
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update order: ' . implode(', ', $stmt->errorInfo())]);
        }
        break;

    // ── update_order_updates ──────────────────────────────────────────────────
    case 'update_order_updates':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = $data['id'] ?? 0;

        if (!$id) { echo json_encode(['success' => false, 'message' => 'Order ID is required']); break; }

        $update = $data['update'] ?? null;
        if (!$update || !isset($update['text']) || trim($update['text']) === '') {
            echo json_encode(['success' => false, 'message' => 'Update text is required']);
            break;
        }

        // Ensure the updates column exists — safe try/catch, no SHOW COLUMNS
        try { $db->exec("ALTER TABLE repair_orders ADD COLUMN updates TEXT"); } catch(PDOException $e) {}

        // Fetch current updates value
        $stmt = $db->prepare("SELECT id, updates FROM repair_orders WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) { echo json_encode(['success' => false, 'message' => 'Order not found']); break; }

        $currentUpdates   = [];
        if (!empty($order['updates'])) {
            $decoded = json_decode($order['updates'], true);
            if (is_array($decoded)) $currentUpdates = $decoded;
        }

        $newEntry = [
            'text' => trim($update['text']),
            'date' => $update['date'] ?? date('Y-m-d H:i:s'),
            'user' => $update['user'] ?? 'Technician'
        ];
        $currentUpdates[] = $newEntry;
        $encoded = json_encode($currentUpdates);

        $updateStmt = $db->prepare("UPDATE repair_orders SET updates = :updates, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->execute([':id' => $id, ':updates' => $encoded]);
        $affected = $updateStmt->rowCount();

        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Update added successfully']);
        } else {
            // rowCount can be 0 if updated_at didn't change — verify by re-reading
            $check = $db->prepare("SELECT updates FROM repair_orders WHERE id = :id");
            $check->execute([':id' => $id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['updates'] === $encoded) {
                echo json_encode(['success' => true, 'message' => 'Update added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'DB write failed. Column may be missing. Error: ' . implode(', ', $updateStmt->errorInfo())]);
            }
        }
        break;

    // ── update_spare_part ─────────────────────────────────────────────────────
    case 'update_spare_part':
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = $data['id'] ?? 0;

        if (!$id) { echo json_encode(['success' => false, 'message' => 'Spare part ID is required']); break; }

        $errors = validateSparePartData($data);
        if (!empty($errors)) { echo json_encode(['success' => false, 'message' => implode(', ', $errors)]); break; }

        $partNumber  = $data['partNumber']  ?? $data['part_number']  ?? '';
        $partName    = $data['partName']    ?? $data['part_name']    ?? '';
        $description = $data['description'] ?? '';
        $quantity    = $data['quantity']    ?? 0;
        $unitPrice   = $data['unitPrice']   ?? $data['unit_price']   ?? 0;
        $supplier    = $data['supplier']    ?? '';
        $location    = $data['location']    ?? '';
        $minQuantity = $data['minQuantity'] ?? $data['min_quantity'] ?? 5;
        $category    = $data['category']    ?? 'Other';

        $checkStmt = $db->prepare("SELECT id FROM spare_parts WHERE part_number = :part_number AND id != :id");
        $checkStmt->execute([':part_number' => $partNumber, ':id' => $id]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Part number already exists']);
            break;
        }

        $query = "UPDATE spare_parts SET
            part_number = :part_number, part_name = :part_name, description = :description,
            quantity = :quantity, unit_price = :unit_price, supplier = :supplier,
            location = :location, min_quantity = :min_quantity, category = :category,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

        $stmt   = $db->prepare($query);
        $result = $stmt->execute([
            ':id'           => $id,
            ':part_number'  => $partNumber,
            ':part_name'    => $partName,
            ':description'  => $description,
            ':quantity'     => $quantity,
            ':unit_price'   => $unitPrice,
            ':supplier'     => $supplier,
            ':location'     => $location,
            ':min_quantity' => $minQuantity,
            ':category'     => $category
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Spare part updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update spare part: ' . implode(', ', $stmt->errorInfo())]);
        }
        break;

    // ── delete_order ──────────────────────────────────────────────────────────
    case 'delete_order':
        $id = $_GET['id'] ?? 0;
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Order ID is required']); break; }

        $stmt   = $db->prepare("DELETE FROM repair_orders WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        echo json_encode($result
            ? ['success' => true,  'message' => 'Order deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete order']
        );
        break;

    // ── delete_spare_part ─────────────────────────────────────────────────────
    case 'delete_spare_part':
        $id = $_GET['id'] ?? 0;
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Spare part ID is required']); break; }

        $stmt   = $db->prepare("DELETE FROM spare_parts WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        echo json_encode($result
            ? ['success' => true,  'message' => 'Spare part deleted successfully']
            : ['success' => false, 'message' => 'Failed to delete spare part']
        );
        break;

    // ── get_stats ─────────────────────────────────────────────────────────────
    case 'get_stats':
        $query = "SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = 'in-progress' THEN 1 ELSE 0 END) as inProgress,
            SUM(CASE WHEN status = 'completed'   THEN 1 ELSE 0 END) as completed,
            COALESCE(SUM(estimated_cost), 0) as revenue
            FROM repair_orders";

        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'data' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>