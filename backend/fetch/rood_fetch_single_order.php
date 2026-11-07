<?php
require '../auth_guard.php';

// Clear any previous output
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
http_response_code(200);

try {
    // Validate ID parameter
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('Invalid ID parameter');
    }
    
    $id = (int)$_GET['id'];
    if ($id <= 0) {
        throw new Exception('ID must be a positive integer');
    }

    // Prepare and execute query
    $stmt = $conn->prepare("SELECT id, reference_type, file_name, issuance_date, upload_file
                          FROM rood_orders
                          WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new Exception('Record not found');
    }

    // Reference types mapping
    $referenceTypes = [
        1 => "Competency-Based Recruitment and Promotion Issuances",
        2 => "Policies on Alternative Work Arrangements (AWA)",
        3 => "Policies on Attendance and Leave Administration",
        4 => "Regional Office Memorandum",
        5 => "Regional Office Orders",
        6 => "Training Related Issuances",
        7 => "Welfare Fund and Employees' Benefits Related Issuances"
    ];
    
    // Prepare response
    $response = [
        'success' => true,
        'data' => [
            'id' => $row['id'],
            'reference_name' => $referenceTypes[$row['reference_type']] ?? 'Unknown Reference',
            'file_name' => $row['file_name'],
            'issuance_date' => $row['issuance_date'],
            'file_path' => $row['upload_file']
        ]
    ];

    echo json_encode($response);
    exit;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}