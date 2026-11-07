<?php
// Global keyword search across every issuance category (by file name — the
// only free-text field the schema currently has). Any logged-in user can
// search; no admin requirement, same as the other fetch endpoints.
require '../auth_guard.php';

header('Content-Type: application/json');

$q = trim((string) ($_GET['q'] ?? ''));

if ($q === '' || mb_strlen($q) < 2) {
    echo json_encode(['data' => []]);
    exit;
}

$referenceTypes = [
    1 => "Competency-Based Recruitment and Promotion Issuances",
    2 => "Policies on Alternative Work Arrangements (AWA)",
    3 => "Policies on Attendance and Leave Administration",
    4 => "Regional Office Memorandum",
    5 => "Regional Office Orders",
    6 => "Training Related Issuances",
    7 => "Welfare Fund and Employees' Benefits Related Issuances"
];

$referencePages = [
    1 => "competency_based_recruitment_and_promotion_issuances",
    2 => "policies_awa",
    3 => "policies_on_attendance_and_leave_administration",
    4 => "regional_office_memorandum",
    5 => "regional_office_orders",
    6 => "training_related_issuances",
    7 => "welfare_fund_related_issuances",
];

// Escape LIKE metacharacters in the user's query before wrapping it in %...%.
$likeSafe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);

// MySQL's default LIKE escape character is backslash, matching the escaping
// applied to $likeSafe above, so no explicit ESCAPE clause is needed.
$stmt = $conn->prepare(
    "SELECT id, reference_type, file_name, issuance_date, upload_file
     FROM rood_orders
     WHERE deleted_at IS NULL AND file_name LIKE :q
     ORDER BY issuance_date DESC
     LIMIT 100"
);
$stmt->bindValue(':q', '%' . $likeSafe . '%');
$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = array_map(function ($row) use ($referenceTypes, $referencePages) {
    $refId = (int) $row['reference_type'];

    $formattedDate = '';
    if (!empty($row['issuance_date'])) {
        $date = DateTime::createFromFormat('Y-m-d', $row['issuance_date']);
        if ($date) {
            $formattedDate = $date->format('F j, Y');
        }
    }

    return [
        'id' => $row['id'],
        'reference_name' => $referenceTypes[$refId] ?? 'Unknown Reference',
        'category_page' => $referencePages[$refId] ?? '#',
        'file_name' => $row['file_name'],
        'issuance_date_display' => $formattedDate,
        'file_path' => $row['upload_file'],
    ];
}, $results);

echo json_encode(['data' => $data]);
