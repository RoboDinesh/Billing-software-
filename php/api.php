<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? '';
$conn = get_db_connection();

try {
    switch ($action) {
        case 'get_settings':
            $res = $conn->query("SELECT * FROM settings WHERE id = 1");
            echo json_encode($res->fetch_assoc());
            break;

        case 'save_settings':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE settings SET company_name=?, gstin=?, address=?, city=?, state_name=?, state_code=?, phone=?, email=?, bank_name=?, bank_account=?, bank_ifsc=?, bank_branch=?, terms_conditions=?, logo_url=?, signature_url=?, bank_reptid=?, show_logo=?, show_bank=?, show_signature=? WHERE id = 1");
            $stmt->bind_param("ssssssssssssssssiii", $data['name'], $data['gstin'], $data['address'], $data['city'], $data['state'], $data['stateCode'], $data['phone'], $data['email'], $data['bankName'], $data['bankAccount'], $data['bankIFSC'], $data['bankBranch'], $data['termsConditions'], $data['logo'], $data['signature'], $data['reptid'], $data['showLogo'], $data['showBank'], $data['showSignature']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_users':
            $res = $conn->query("SELECT id, username, name, role FROM users");
            $users = []; while($row = $res->fetch_assoc()) $users[] = $row;
            echo json_encode($users);
            break;

        case 'login':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
            if (!$stmt) throw new Exception($conn->error);
            $stmt->bind_param("ss", $data['username'], $data['password']);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            if ($user) {
                unset($user['password']);
                echo json_encode(['ok' => true, 'user' => $user]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid credentials']);
            }
            break;

        case 'get_customers':
            $res = $conn->query("SELECT * FROM customers ORDER BY created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_customer':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO customers (id, name, gstin, address, phone, email, state_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $data['id'], $data['name'], $data['gstin'], $data['address'], $data['phone'], $data['email'], $data['stateCode']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_customer':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE customers SET name=?, gstin=?, address=?, phone=?, email=?, state_code=? WHERE id=?");
            $stmt->bind_param("sssssss", $data['name'], $data['gstin'], $data['address'], $data['phone'], $data['email'], $data['stateCode'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_products':
            $res = $conn->query("SELECT * FROM products ORDER BY name ASC");
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_product':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO products (id, name, hsn, unit, price, gst_rate) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdd", $data['id'], $data['name'], $data['hsnCode'], $data['unit'], $data['rate'], $data['gstRate']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_product':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE products SET name=?, hsn=?, unit=?, price=?, gst_rate=? WHERE id=?");
            $stmt->bind_param("ssssdds", $data['name'], $data['hsnCode'], $data['unit'], $data['rate'], $data['gstRate'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_invoices':
            $res = $conn->query("SELECT * FROM invoices ORDER BY invoice_date DESC, created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'], true);
                $row['items'] = json_decode($row['items_json'], true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO invoices (id, invoice_no, invoice_date, due_date, customer_id, customer_json, items_json, subtotal, cgst, sgst, igst, total, notes, payment_status, paid_amount, terms_conditions, gst_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssdddddssdss", $data['id'], $data['invoiceNo'], $data['date'], $data['duedate'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['notes'], $data['paymentStatus'], $data['paidAmount'], $data['termsConditions'], $data['gstType']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_invoice':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE invoices SET invoice_no=?, invoice_date=?, due_date=?, customer_id=?, customer_json=?, items_json=?, subtotal=?, cgst=?, sgst=?, igst=?, total=?, notes=?, payment_status=?, paid_amount=?, terms_conditions=?, gst_type=? WHERE id=?");
            $stmt->bind_param("sssssssdddddssdss", $data['invoiceNo'], $data['date'], $data['duedate'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['notes'], $data['paymentStatus'], $data['paidAmount'], $data['termsConditions'], $data['gstType'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_quotations':
            $res = $conn->query("SELECT * FROM quotations ORDER BY quotation_date DESC, created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'] ?? '{}', true);
                $row['items'] = json_decode($row['items_json'] ?? '[]', true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_quotation':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO quotations (id, quotation_no, quotation_date, customer_id, customer_json, items_json, subtotal, cgst, sgst, igst, total, valid_until, status, gst_type, terms_conditions, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdddddsssss", $data['id'], $data['quotationNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['validUntil'], $data['status'], $data['gstType'], $data['termsConditions'], $data['notes']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_quotation':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE quotations SET quotation_no=?, quotation_date=?, customer_id=?, customer_json=?, items_json=?, subtotal=?, cgst=?, sgst=?, igst=?, total=?, valid_until=?, status=?, gst_type=?, terms_conditions=?, notes=? WHERE id=?");
            $stmt->bind_param("sssssdddddssssss", $data['quotationNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['validUntil'], $data['status'], $data['gstType'], $data['termsConditions'], $data['notes'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_challans':
            $res = $conn->query("SELECT * FROM challans ORDER BY challan_date DESC, created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'] ?? '{}', true);
                $row['items'] = json_decode($row['items_json'] ?? '[]', true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_challan':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO challans (id, challan_no, challan_date, customer_id, customer_json, items_json, vehicle_no, status, terms_conditions, notes, reference_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssssss", $data['id'], $data['challanNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['vehicleNo'], $data['status'], $data['termsConditions'], $data['notes'], $data['referenceNo']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_challan':
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE challans SET challan_no=?, challan_date=?, customer_id=?, customer_json=?, items_json=?, vehicle_no=?, status=?, terms_conditions=?, notes=?, reference_no=? WHERE id=?");
            $stmt->bind_param("sssssssssss", $data['challanNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['vehicleNo'], $data['status'], $data['termsConditions'], $data['notes'], $data['referenceNo'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_labors':
            $res = $conn->query("SELECT * FROM labors ORDER BY created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_labor':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO labors (id, name, phone, address, joined_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $data['id'], $data['name'], $data['phone'], $data['address'], $data['joinedDate']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_labor':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE labors SET name=?, phone=?, address=?, joined_date=? WHERE id=?");
            $stmt->bind_param("sssss", $data['name'], $data['phone'], $data['address'], $data['joinedDate'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_labor_ledger':
            $res = $conn->query("SELECT * FROM labor_ledger ORDER BY entry_date DESC, created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_labor_ledger':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO labor_ledger (id, labor_id, entry_date, type, amount, description, reference_no) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdss", $data['id'], $data['laborId'], $data['entryDate'], $data['type'], $data['amount'], $data['description'], $data['referenceNo']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_materials':
            $res = $conn->query("SELECT * FROM materials ORDER BY date DESC, created_at DESC");
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_material':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO materials (id, name, quantity, rate, gst_rate, cgst, sgst, total_amount, date, supplier, bill_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdddddddss", $data['id'], $data['name'], $data['quantity'], $data['rate'], $data['gstRate'], $data['cgst'], $data['sgst'], $data['totalAmount'], $data['date'], $data['supplier'], $data['billUrl']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_material':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE materials SET name=?, quantity=?, rate=?, gst_rate=?, cgst=?, sgst=?, total_amount=?, date=?, supplier=?, bill_url=? WHERE id=?");
            $stmt->bind_param("sdddddddsss", $data['name'], $data['quantity'], $data['rate'], $data['gstRate'], $data['cgst'], $data['sgst'], $data['totalAmount'], $data['date'], $data['supplier'], $data['billUrl'], $data['id']);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $table = $data['table'] ?? '';
            $id = $data['id'] ?? '';
            $allowed = ['customers', 'products', 'invoices', 'quotations', 'challans', 'labors', 'labor_ledger', 'materials'];
            if (in_array($table, $allowed) && $id) {
                $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
                $stmt->bind_param("s", $id);
                echo json_encode(['ok' => $stmt->execute()]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Invalid table or id']);
            }
            break;

        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(200); // Return 200 so JS can parse the JSON error
    echo json_encode(['ok' => false, 'error' => "Database Error: " . $e->getMessage()]);
}

if (isset($conn) && $conn) {
    $conn->close();
}
?>
