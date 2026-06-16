<?php
header('Content-Type: application/json');
require_once 'config.php';

$action = $_GET['action'] ?? '';
$conn = get_db_connection();

// Multi-tenant helper
function get_tenant_id() {
    $headers = apache_request_headers();
    return $headers['X-Company-ID'] ?? $headers['x-company-id'] ?? null;
}

try {
    $company_id = get_tenant_id();

    switch ($action) {
        case 'get_settings':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM settings WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_assoc());
            break;

        case 'save_settings':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE settings SET company_name=?, gstin=?, address=?, city=?, state_name=?, state_code=?, phone=?, email=?, bank_name=?, bank_account=?, bank_ifsc=?, bank_branch=?, terms_conditions=?, logo_url=?, signature_url=?, bank_reptid=?, show_logo=?, show_bank=?, show_signature=? WHERE company_id = ?");
            $stmt->bind_param("ssssssssssssssssiiis", $data['name'], $data['gstin'], $data['address'], $data['city'], $data['state'], $data['stateCode'], $data['phone'], $data['email'], $data['bankName'], $data['bankAccount'], $data['bankIFSC'], $data['bankBranch'], $data['termsConditions'], $data['logo'], $data['signature'], $data['reptid'], $data['showLogo'], $data['showBank'], $data['showSignature'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_users':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT id, username, name, role FROM users WHERE company_id = ?");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
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
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM customers WHERE company_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_customer':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO customers (id, name, gstin, address, phone, email, state_code, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $data['id'], $data['name'], $data['gstin'], $data['address'], $data['phone'], $data['email'], $data['stateCode'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_customer':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE customers SET name=?, gstin=?, address=?, phone=?, email=?, state_code=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssssss", $data['name'], $data['gstin'], $data['address'], $data['phone'], $data['email'], $data['stateCode'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_products':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM products WHERE company_id = ? ORDER BY name ASC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_product':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO products (id, name, hsn, unit, price, gst_rate, manufacturer, mrp, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdddds", $data['id'], $data['name'], $data['hsnCode'], $data['unit'], $data['rate'], $data['gstRate'], $data['mfr'], $data['mrp'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_product':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE products SET name=?, hsn=?, unit=?, price=?, gst_rate=?, manufacturer=?, mrp=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssdddss", $data['name'], $data['hsnCode'], $data['unit'], $data['rate'], $data['gstRate'], $data['mfr'], $data['mrp'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_invoices':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM invoices WHERE company_id = ? ORDER BY invoice_date DESC, created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'], true);
                $row['items'] = json_decode($row['items_json'], true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_invoice':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO invoices (id, invoice_no, invoice_date, due_date, customer_id, customer_json, items_json, subtotal, cgst, sgst, igst, total, notes, payment_status, paid_amount, terms_conditions, gst_type, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssdddddssdsss", $data['id'], $data['invoiceNo'], $data['date'], $data['duedate'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['notes'], $data['paymentStatus'], $data['paidAmount'], $data['termsConditions'], $data['gstType'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_invoice':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE invoices SET invoice_no=?, invoice_date=?, due_date=?, customer_id=?, customer_json=?, items_json=?, subtotal=?, cgst=?, sgst=?, igst=?, total=?, notes=?, payment_status=?, paid_amount=?, terms_conditions=?, gst_type=? WHERE id=? AND company_id=?");
            $stmt->bind_param("sssssssdddddssdsss", $data['invoiceNo'], $data['date'], $data['duedate'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['notes'], $data['paymentStatus'], $data['paidAmount'], $data['termsConditions'], $data['gstType'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_quotations':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM quotations WHERE company_id = ? ORDER BY quotation_date DESC, created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'] ?? '{}', true);
                $row['items'] = json_decode($row['items_json'] ?? '[]', true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_quotation':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO quotations (id, quotation_no, quotation_date, customer_id, customer_json, items_json, subtotal, cgst, sgst, igst, total, valid_until, status, gst_type, terms_conditions, notes, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssdddddssssss", $data['id'], $data['quotationNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['validUntil'], $data['status'], $data['gstType'], $data['termsConditions'], $data['notes'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_quotation':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE quotations SET quotation_no=?, quotation_date=?, customer_id=?, customer_json=?, items_json=?, subtotal=?, cgst=?, sgst=?, igst=?, total=?, valid_until=?, status=?, gst_type=?, terms_conditions=?, notes=? WHERE id=? AND company_id=?");
            $stmt->bind_param("sssssdddddsssssss", $data['quotationNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['subtotal'], $data['totalCGST'], $data['totalSGST'], $data['totalIGST'], $data['grandTotal'], $data['validUntil'], $data['status'], $data['gstType'], $data['termsConditions'], $data['notes'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_challans':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM challans WHERE company_id = ? ORDER BY challan_date DESC, created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) {
                $row['customer'] = json_decode($row['customer_json'] ?? '{}', true);
                $row['items'] = json_decode($row['items_json'] ?? '[]', true);
                $list[] = $row;
            }
            echo json_encode($list);
            break;

        case 'add_challan':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("INSERT INTO challans (id, challan_no, challan_date, customer_id, customer_json, items_json, vehicle_no, status, terms_conditions, notes, reference_no, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssss", $data['id'], $data['challanNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['vehicleNo'], $data['status'], $data['termsConditions'], $data['notes'], $data['referenceNo'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_challan':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $cust_json = json_encode($data['customer'] ?? []);
            $items_json = json_encode($data['items'] ?? []);
            $stmt = $conn->prepare("UPDATE challans SET challan_no=?, challan_date=?, customer_id=?, customer_json=?, items_json=?, vehicle_no=?, status=?, terms_conditions=?, notes=?, reference_no=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssssssssss", $data['challanNo'], $data['date'], $data['customerId'], $cust_json, $items_json, $data['vehicleNo'], $data['status'], $data['termsConditions'], $data['notes'], $data['referenceNo'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_labors':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM labors WHERE company_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_labor':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO labors (id, name, phone, address, joined_date, company_id) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $data['id'], $data['name'], $data['phone'], $data['address'], $data['joinedDate'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_labor':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE labors SET name=?, phone=?, address=?, joined_date=? WHERE id=? AND company_id=?");
            $stmt->bind_param("ssssss", $data['name'], $data['phone'], $data['address'], $data['joinedDate'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_labor_ledger':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM labor_ledger WHERE company_id = ? ORDER BY entry_date DESC, created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_labor_ledger':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO labor_ledger (id, labor_id, entry_date, type, amount, description, reference_no, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssdsss", $data['id'], $data['laborId'], $data['entryDate'], $data['type'], $data['amount'], $data['description'], $data['referenceNo'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_materials':
            if (!$company_id) throw new Exception("Company ID required");
            $stmt = $conn->prepare("SELECT * FROM materials WHERE company_id = ? ORDER BY date DESC, created_at DESC");
            $stmt->bind_param("s", $company_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_material':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("INSERT INTO materials (id, name, quantity, rate, gst_rate, cgst, sgst, total_amount, date, supplier, bill_url, company_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssdddddddsss", $data['id'], $data['name'], $data['quantity'], $data['rate'], $data['gstRate'], $data['cgst'], $data['sgst'], $data['totalAmount'], $data['date'], $data['supplier'], $data['billUrl'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'update_material':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $conn->prepare("UPDATE materials SET name=?, quantity=?, rate=?, gst_rate=?, cgst=?, sgst=?, total_amount=?, date=?, supplier=?, bill_url=? WHERE id=? AND company_id=?");
            $stmt->bind_param("sdddddddssss", $data['name'], $data['quantity'], $data['rate'], $data['gstRate'], $data['cgst'], $data['sgst'], $data['totalAmount'], $data['date'], $data['supplier'], $data['billUrl'], $data['id'], $company_id);
            echo json_encode(['ok' => $stmt->execute()]);
            break;

        case 'get_companies':
            // Only allow if company_id is C001 (Super Admin)
            if ($company_id !== 'C001') throw new Exception("Unauthorized");
            $stmt = $conn->prepare("SELECT company_id, company_name, city, phone, created_at FROM settings ORDER BY id DESC");
            $stmt->execute();
            $res = $stmt->get_result();
            $list = []; while($row = $res->fetch_assoc()) $list[] = $row;
            echo json_encode($list);
            break;

        case 'add_company':
            if ($company_id !== 'C001') throw new Exception("Unauthorized");
            $data = json_decode(file_get_contents('php://input'), true);
            $new_cid = 'C' . time();
            
            // 1. Create Settings
            $stmt = $conn->prepare("INSERT INTO settings (company_id, company_name, city, phone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $new_cid, $data['name'], $data['city'], $data['phone']);
            $stmt->execute();

            // 2. Create Admin User
            $stmt = $conn->prepare("INSERT INTO users (company_id, username, password, name, role) VALUES (?, ?, ?, ?, 'admin')");
            $stmt->bind_param("ssss", $new_cid, $data['username'], $data['password'], $data['adminName']);
            $stmt->execute();

            echo json_encode(['ok' => true, 'company_id' => $new_cid]);
            break;

        case 'delete':
            if (!$company_id) throw new Exception("Company ID required");
            $data = json_decode(file_get_contents('php://input'), true);
            $table = $data['table'] ?? '';
            $id = $data['id'] ?? '';
            $allowed = ['customers', 'products', 'invoices', 'quotations', 'challans', 'labors', 'labor_ledger', 'materials'];
            if (in_array($table, $allowed) && $id) {
                $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ? AND company_id = ?");
                $stmt->bind_param("ss", $id, $company_id);
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
