<?php
require '../auth_guard.php';

$ref = isset($_GET['ref']) ? intval($_GET['ref']) : 0;
$year = $_GET['year'] ?? 'All';

// Build the query based on the year filter
$query = "SELECT id, reference_type, file_name, issuance_date, upload_file FROM rood_orders WHERE reference_type = :ref AND deleted_at IS NULL";
$params = [':ref' => $ref];

if ($year !== 'All' && is_numeric($year)) {
    // Range comparison instead of YEAR(issuance_date) = :year so the
    // (reference_type, issuance_date) index can actually be used.
    $yearInt = (int) $year;
    $query .= " AND issuance_date >= :year_start AND issuance_date < :year_end";
    $params[':year_start'] = sprintf('%04d-01-01', $yearInt);
    $params[':year_end']   = sprintf('%04d-01-01', $yearInt + 1);
}

$stmt = $conn->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Reference types array
$referenceTypes = [
    1 => "Competency-Based Recruitment and Promotion Issuances",
    2 => "Policies on Alternative Work Arrangements (AWA)",
    3 => "Policies on Attendance and Leave Administration",
    4 => "Regional Office Memorandum",
    5 => "Regional Office Orders",
    6 => "Training Related Issuances",
    7 => "Welfare Fund and Employees' Benefits Related Issuances"
];

// Format results
$data = array_map(function($row) use ($referenceTypes) {
    $referenceName = $referenceTypes[$row['reference_type']] ?? 'Unknown Reference';
    
    // Format the date
    $formattedDate = '';
    if (!empty($row['issuance_date'])) {
        $date = DateTime::createFromFormat('Y-m-d', $row['issuance_date']);
        if ($date) {
            $formattedDate = $date->format('F j, Y');
        }
    }

    return [
        'id' => $row['id'],
        'reference_name' => $referenceName,
        'file_name' => $row['file_name'],
        'issuance_date' => $row['issuance_date'],
        'issuance_date_display' => $formattedDate,
        'file_path' => $row['upload_file']
    ];

}, $results);

echo json_encode(["data" => $data]);
?>