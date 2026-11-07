<?php
require '../auth_guard.php';

// Get selected year from GET parameter, default is 'All'
$year = isset($_GET['year']) ? $_GET['year'] : 'All';

// Map Reference Names to their Target PHP Pages
$referencePages = [
    "Policies on Alternative Work Arrangements (AWA)" => "policies_awa",
    "Policies on Attendance and Leave Administration" => "policies_on_attendance_and_leave_administration",
    "Competency-Based Recruitment and Promotion Issuances" => "competency_based_recruitment_and_promotion_issuances",
    "Training Related Issuances" => "training_related_issuances",
    "Welfare Fund and Employees Benefits Related Issuances" => "welfare_fund_related_issuances",
    "Regional Office Memorandum" => "regional_office_memorandum",
    "Regional Office Orders" => "regional_office_orders"
];

// Icon mapping for each reference type
$referenceIcons = [
    "Competency-Based Recruitment and Promotion Issuances" => "bi-person-badge",
    "Policies on Alternative Work Arrangements (AWA)" => "bi-laptop",
    "Policies on Attendance and Leave Administration" => "bi-calendar-check",
    "Regional Office Memorandum" => "bi-file-earmark-text",
    "Regional Office Orders" => "bi-clipboard-check",
    "Training Related Issuances" => "bi-book",
    "Welfare Fund and Employees Benefits Related Issuances" => "bi-heart-pulse"
];

// Color schemes for cards
$cardColors = [
    "Competency-Based Recruitment and Promotion Issuances" => "primary",
    "Policies on Alternative Work Arrangements (AWA)" => "info",
    "Policies on Attendance and Leave Administration" => "success",
    "Regional Office Memorandum" => "warning",
    "Regional Office Orders" => "danger",
    "Training Related Issuances" => "secondary",
    "Welfare Fund and Employees Benefits Related Issuances" => "dark"
];

// Prepare base SQL
$sql = "SELECT r.reference_name, COUNT(o.id) AS reference_count 
        FROM rood_orders o
        JOIN rood_reference_type r ON o.reference_type = r.id
        WHERE r.reference_name IN (
            'Competency-Based Recruitment and Promotion Issuances',
            'Policies on Alternative Work Arrangements (AWA)',
            'Policies on Attendance and Leave Administration',
            'Regional Office Memorandum',
            'Regional Office Orders',
            'Training Related Issuances',
            'Welfare Fund and Employees Benefits Related Issuances'
        )
        AND o.deleted_at IS NULL";

// Filter by year if not "All" — range comparison so the issuance_date index
// can be used, instead of YEAR(o.issuance_date) = :year.
$isYearFiltered = $year !== 'All' && is_numeric($year);
if ($isYearFiltered) {
    $sql .= " AND o.issuance_date >= :year_start AND o.issuance_date < :year_end";
}

$sql .= " GROUP BY o.reference_type";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);

if ($isYearFiltered) {
    $yearInt = (int) $year;
    $stmt->execute([
        'year_start' => sprintf('%04d-01-01', $yearInt),
        'year_end'   => sprintf('%04d-01-01', $yearInt + 1),
    ]);
} else {
    $stmt->execute();
}

// Prepare an array to hold the counts
$referenceCounts = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $referenceCounts[$row['reference_name']] = $row['reference_count'];
}

// Close the database connection
$conn = null;

// Maps the Bootstrap color names above to the design system's fixed
// .otrs-ph-icon color-chip variants (icon-blue/green/purple/amber/red/navy).
$iconColorMap = [
    "primary"   => "blue",
    "info"      => "blue",
    "success"   => "green",
    "warning"   => "amber",
    "danger"    => "red",
    "secondary" => "navy",
    "dark"      => "purple",
];

// Output the HTML
if (empty($referenceCounts)): ?>
    <div class="otrs-alert otrs-alert-info rooms-cat-empty">
        <i class="bi bi-info-circle-fill"></i>
        <div>
            <strong>No Data Available.</strong>
            There are no reference documents for <?= $year === 'All' ? 'the selected criteria' : 'year ' . htmlspecialchars($year) ?>. Try selecting a different year or add new references.
        </div>
    </div>
<?php else: ?>
    <?php foreach ($referenceCounts as $referenceName => $count): ?>
        <?php
            $page = isset($referencePages[$referenceName]) ? $referencePages[$referenceName] : '#';
            $icon = isset($referenceIcons[$referenceName]) ? $referenceIcons[$referenceName] : 'bi-file-text';
            $color = isset($cardColors[$referenceName]) ? $cardColors[$referenceName] : 'primary';
        ?>
        <a href="<?= htmlspecialchars($page) ?>" class="rooms-cat-card">
            <div class="otrs-card h-100">
                <div class="otrs-card-top">
                    <div class="otrs-ph-icon icon-<?= $iconColorMap[$color] ?? 'blue' ?>"><i class="bi <?= $icon ?>"></i></div>
                    <span class="otrs-count-badge"><?= $count ?></span>
                </div>
                <div class="otrs-card-body d-flex flex-column">
                    <div class="rooms-cat-title flex-grow-1"><?= htmlspecialchars($referenceName) ?></div>
                    <div class="d-flex align-items-center justify-content-between mt-2">
                        <small class="text-muted" style="font-size: var(--otrs-text-xs);">
                            <i class="bi bi-file-earmark-text me-1"></i><?= $count ?> <?= $count == 1 ? 'Document' : 'Documents' ?>
                        </small>
                        <i class="bi bi-arrow-right-circle-fill text-primary"></i>
                    </div>
                </div>
            </div>
        </a>
    <?php endforeach; ?>
<?php endif; ?>