<?php
// Most-recently-added issuances across every category, for the dashboard's
// "Recently Added" widget. Ordered by row id (insertion order), not
// issuance_date, since a newly-added document with an older issuance date
// should still surface as "recently added."
require '../auth_guard.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 8;

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

$stmt = $conn->prepare(
    "SELECT id, reference_type, file_name, issuance_date, upload_file
     FROM rood_orders
     WHERE deleted_at IS NULL
     ORDER BY id DESC
     LIMIT :limit"
);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
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
