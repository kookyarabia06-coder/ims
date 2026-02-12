<?php
// save_multiple_inventory.php
ob_start();
require_once 'auth.php';
require_login();

header('Content-Type: application/json');

// Database connection
$mysqli = new mysqli('localhost', 'root', '', 'inventory_db');
if ($mysqli->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Set charset
$mysqli->set_charset('utf8mb4');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['items']) || !is_array($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$items = $input['items'];
$user_id = $input['user_id'] ?? 0;

$success_count = 0;
$fail_count = 0;
$errors = [];

// Start transaction
$mysqli->begin_transaction();

try {
    // Prepare the insert statement - EXACTLY LIKE YOUR SINGLE INSERT
    $stmt = $mysqli->prepare("
        INSERT INTO inventory (
            article_name, 
            description, 
            property_no, 
            uom,
            qty_property_card, 
            qty_physical_count,
            location_id, 
            condition_text, 
            remarks,
            certified_correct, 
            approved_by, 
            verified_by,
            section_id, 
            fund_cluster, 
            unit_value,
            equipment_id, 
            type_equipment, 
            category,
            allocate_to, 
            barcode_data, 
            barcode_image,
            date_added, 
            date_updated
        ) VALUES (
            ?, ?, ?, ?, 
            ?, ?, ?, ?, 
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            NOW(), NOW()
        )
    ");

    if (!$stmt) {
        throw new Exception($mysqli->error);
    }

    foreach ($items as $index => $item) {
        // ============================================
        // SET DEFAULTS - USE NULL INSTEAD OF 0 FOR FOREIGN KEYS
        // ============================================
        
        // Basic fields
        $article_name = trim($item['article_name'] ?? $item['description'] ?? '');
        $description = trim($item['description'] ?? $item['article_name'] ?? '');
        $property_no = trim($item['property_no'] ?? '');
        $uom = trim($item['uom'] ?? 'Unit');
        
        // Quantity - always 1 for multiple barcodes
        $qty_property_card = 1;
        $qty_physical_count = 1;
        
        // Location - REQUIRED (must be valid)
        $location_id = isset($item['location_id']) && !empty($item['location_id']) 
                      ? intval($item['location_id']) 
                      : null;
        
        // Condition
        $condition_text = trim($item['condition_text'] ?? 'Serviceable');
        
        // Remarks
        $remarks = trim($item['remarks'] ?? 'Batch generated on ' . date('Y-m-d H:i:s'));
        
        // Certified correct - always null
        $certified_correct = null;
        
        // Approved/Verified - use NULL instead of 0
        $approved_by = null;
        $verified_by = null;
        
        // Section - use NULL instead of 0 (Fixes the FK constraint)
        $section_id = isset($item['section_id']) && !empty($item['section_id']) 
                     ? intval($item['section_id']) 
                     : null;
        
        // Fund cluster
        $fund_cluster = trim($item['fund_cluster'] ?? 'IGF');
        
        // Unit value
        $unit_value = floatval($item['unit_value'] ?? 0);
        
        // Equipment - use NULL instead of 0
        $equipment_id = isset($item['equipment_id']) && !empty($item['equipment_id']) 
                       ? intval($item['equipment_id']) 
                       : null;
        
        // Type of equipment
        $type_equipment = trim($item['type_equipment'] ?? 'Semi-expendable Equipment');
        
        // Category
        $category = trim($item['category'] ?? 'Batch Generated');
        
        // Allocate to - use NULL instead of 0
        $allocate_to = isset($item['allocate_to']) && !empty($item['allocate_to']) 
                      ? intval($item['allocate_to']) 
                      : null;
        
        // Barcode data - store property number
        $barcode_data = $property_no;
        
        // Barcode image - from generation
        $barcode_image = $item['barcode_image'] ?? null;

        // ============================================
        // VALIDATION
        // ============================================
        
        // Skip if no barcode image or property number
        if (empty($property_no) || empty($barcode_image)) {
            $fail_count++;
            $errors[] = "Item " . ($index + 1) . ": Missing property number or barcode image";
            continue;
        }

        // Check if property number already exists
        $check_stmt = $mysqli->prepare("SELECT id FROM inventory WHERE property_no = ?");
        $check_stmt->bind_param("s", $property_no);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $fail_count++;
            $errors[] = "Property No '{$property_no}' already exists";
            $check_stmt->close();
            continue;
        }
        $check_stmt->close();

        // ============================================
        // FOREIGN KEY VALIDATION - SET TO NULL IF INVALID
        // ============================================
        
        // Validate location_id - REQUIRED field
        if ($location_id) {
            $check_loc = $mysqli->prepare("SELECT id FROM departments WHERE id = ?");
            $check_loc->bind_param("i", $location_id);
            $check_loc->execute();
            $loc_result = $check_loc->get_result();
            if ($loc_result->num_rows === 0) {
                $location_id = null;
                $fail_count++;
                $errors[] = "Item " . ($index + 1) . " ({$property_no}): Invalid location ID";
                $check_loc->close();
                continue;
            }
            $check_loc->close();
        } else {
            $fail_count++;
            $errors[] = "Item " . ($index + 1) . " ({$property_no}): Location is required";
            continue;
        }
        
        // Validate section_id - set to NULL if invalid
        if ($section_id) {
            $check_sec = $mysqli->prepare("SELECT id FROM sections WHERE id = ?");
            $check_sec->bind_param("i", $section_id);
            $check_sec->execute();
            $sec_result = $check_sec->get_result();
            if ($sec_result->num_rows === 0) {
                $section_id = null; // Set to NULL instead of 0
            }
            $check_sec->close();
        }
        
        // Validate equipment_id - set to NULL if invalid
        if ($equipment_id) {
            $check_eq = $mysqli->prepare("SELECT id FROM equipment WHERE id = ?");
            $check_eq->bind_param("i", $equipment_id);
            $check_eq->execute();
            $eq_result = $check_eq->get_result();
            if ($eq_result->num_rows === 0) {
                $equipment_id = null; // Set to NULL instead of 0
            }
            $check_eq->close();
        }
        
        // Validate allocate_to - set to NULL if invalid
        if ($allocate_to) {
            $check_emp = $mysqli->prepare("SELECT id FROM employees WHERE id = ?");
            $check_emp->bind_param("i", $allocate_to);
            $check_emp->execute();
            $emp_result = $check_emp->get_result();
            if ($emp_result->num_rows === 0) {
                $allocate_to = null; // Set to NULL instead of 0
            }
            $check_emp->close();
        }

        // ============================================
        // BIND PARAMETERS - MODIFIED FOR NULL VALUES
        // ============================================
        
        $stmt->bind_param(
            "ssssddisssiiisdississ",
            $article_name,
            $description,
            $property_no,
            $uom,
            $qty_property_card,
            $qty_physical_count,
            $location_id,
            $condition_text,
            $remarks,
            $certified_correct,
            $approved_by,
            $verified_by,
            $section_id,
            $fund_cluster,
            $unit_value,
            $equipment_id,
            $type_equipment,
            $category,
            $allocate_to,
            $barcode_data,
            $barcode_image
        );

        if ($stmt->execute()) {
            $success_count++;
            
            // Log the activity
            if ($user_id > 0) {
                $log_stmt = $mysqli->prepare("
                    INSERT INTO activity_log (user_id, action, item_id, details, date_created) 
                    VALUES (?, 'add', ?, ?, NOW())
                ");
                if ($log_stmt) {
                    $details = "Batch created item: " . $property_no;
                    $item_id = $mysqli->insert_id;
                    $log_stmt->bind_param("iis", $user_id, $item_id, $details);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
        } else {
            $fail_count++;
            $errors[] = "Item " . ($index + 1) . " ({$property_no}): " . $stmt->error;
        }
    }

    $stmt->close();

    // Commit transaction
    $mysqli->commit();

    // Prepare response
    $response = [
        'success' => true,
        'saved' => $success_count,
        'failed' => $fail_count,
        'total' => count($items),
        'errors' => $errors
    ];

    if ($success_count === count($items)) {
        $response['message'] = "All {$success_count} items saved successfully!";
    } elseif ($success_count > 0) {
        $response['message'] = "Saved {$success_count} items, failed {$fail_count} items.";
    } else {
        $response['success'] = false;
        $response['message'] = "Failed to save any items. Please check the errors.";
    }

    echo json_encode($response);

} catch (Exception $e) {
    // Rollback on error
    $mysqli->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'saved' => $success_count,
        'failed' => $fail_count,
        'errors' => [$e->getMessage()]
    ]);
}

$mysqli->close();
ob_end_flush();
?>