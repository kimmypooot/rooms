<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Check if user is Admin based on rooms column in system_access
$isAdmin = false;
if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id'];
    $stmt = $conn->prepare("SELECT rooms FROM system_access WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userAccess = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($userAccess && strtolower($userAccess['rooms']) === 'admin') {
        $isAdmin = true;
    }
}

// SQL query to get the count of references grouped by reference_type
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
        AND o.deleted_at IS NULL
        GROUP BY o.reference_type";

// Execute the query
$result = $conn->query($sql);

// Prepare an array to hold the counts
$referenceCounts = [];

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    $referenceCounts[$row['reference_name']] = $row['reference_count'];
}

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
?>

<!DOCTYPE html>
<html lang="en">

<?php
$referenceTypes = [
    1 => "Competency-Based Recruitment and Promotion Issuances",
    2 => "Policies on Alternative Work Arrangements (AWA)",
    3 => "Policies on Attendance and Leave Administration",
    4 => "Regional Office Memorandum",
    5 => "Regional Office Orders",
    6 => "Training Related Issuances",
    7 => "Welfare Fund and Employees' Benefits Related Issuances"
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
?>

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>CSC RO VIII - ROOMS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">
    <?php include 'vendor_css.html' ?>
    <?= csrfMeta() ?>
</head>

<body>
    <?php imis_include ('header') ?>
    <?php include 'inc/sidebar.php' ?>

<main id="main" class="main">
    <div class="container-fluid px-4">
        <!-- Page Header -->
        <div class="pagetitle otrs-ph">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="otrs-ph-icon icon-blue"><i class="bi bi-building"></i></div>
                    <div>
                        <h1>Regional Office Orders &amp; Memoranda System</h1>
                        <p class="text-muted mb-0" style="font-size: var(--otrs-text-sm);">
                            <i class="bi bi-info-circle-fill me-1"></i>
                            Comprehensive document management system for CSC Regional Office VIII
                        </p>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addReferenceModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Add New Document
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Global Search — the fastest path to a specific issuance; kept
             above the fold so it's the first thing a returning user sees,
             rather than sidebar-only. -->
        <form action="search.php" method="get" class="otrs-card mb-4" role="search">
            <div class="otrs-card-body">
                <label for="dashboardSearch" class="form-label fw-semibold mb-2" style="font-size: var(--otrs-text-sm);">
                    <i class="bi bi-search me-1"></i>Search all issuances
                </label>
                <div class="input-group input-group-lg shadow-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="search" name="q" id="dashboardSearch" class="form-control border-start-0"
                           placeholder="Search by document name across every category and year&hellip;"
                           minlength="2" required>
                    <button class="btn btn-primary" type="submit">Search</button>
                </div>
            </div>
        </form>

        <div class="otrs-stat-strip">
            <div class="otrs-stat-pill">
                <div class="sp-icon" style="background: var(--otrs-success-bg); color: var(--otrs-success);">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div>
                    <div class="sp-val"><?= !empty($referenceCounts) ? array_sum($referenceCounts) : 0 ?></div>
                    <div class="sp-lbl">Total Documents</div>
                </div>
            </div>
            <div class="otrs-stat-pill">
                <div class="sp-icon" style="background: var(--otrs-info-bg); color: var(--otrs-info);">
                    <i class="bi bi-folder-fill"></i>
                </div>
                <div>
                    <div class="sp-val"><?= !empty($referenceCounts) ? count($referenceCounts) : 0 ?></div>
                    <div class="sp-lbl">Categories</div>
                </div>
            </div>
            <div class="otrs-stat-pill">
                <div class="sp-icon" style="background: var(--otrs-warning-bg); color: var(--otrs-warning);">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <div class="sp-val" style="font-size: .95rem;"><?= date('M d, Y') ?></div>
                    <div class="sp-lbl">Last Updated</div>
                </div>
            </div>
        </div>

        <div class="otrs-card mb-4">
            <div class="otrs-card-top">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-funnel-fill text-primary"></i>
                    <span style="font-size: var(--otrs-text-md); font-weight: 700;">Filter Documents</span>
                    <span class="text-muted" style="font-size: var(--otrs-text-sm);">— select a year to view specific records</span>
                </div>
                <span class="otrs-count-badge">
                    <i class="bi bi-filter-circle me-1"></i>Active: <span id="activeFilterCount">1</span>
                </span>
            </div>
            <div class="otrs-card-body">
                <div class="seg-tabs" id="yearTabs" role="tablist">
                    <button class="seg-tab active" data-year="All" role="tab">
                        <i class="bi bi-calendar-range-fill"></i> All Years
                    </button>
                    <?php for ($y = 2020; $y <= 2025; $y++): ?>
                    <button class="seg-tab" data-year="<?= $y ?>" role="tab">
                        <i class="bi bi-calendar3"></i> <?= $y ?>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Document Categories Grid -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <span style="font-size: var(--otrs-text-lg); font-weight: 700; color: var(--otrs-navy);">
                        <i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Document Categories
                    </span>
                </div>
            </div>
        </div>

        <div class="rooms-cat-grid mb-5" id="dashboardItem">
            <?php if (empty($referenceCounts)): ?>
                <div class="otrs-card rooms-cat-empty">
                    <div class="otrs-card-body p-5 text-center">
                        <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3">
                            <i class="bi bi-inbox text-info" style="font-size: 2.2rem;"></i>
                        </div>
                        <div style="font-size: var(--otrs-text-lg); font-weight: 700;" class="mb-2">No Documents Available</div>
                        <p class="text-muted mb-4" style="font-size: var(--otrs-text-sm);">There are currently no reference documents in the system.</p>
                        <?php if ($isAdmin): ?>
                        <button type="button" class="btn btn-primary btn-sm rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addReferenceModal">
                            <i class="bi bi-plus-circle me-2"></i>Add Your First Document
                        </button>
                        <?php endif; ?>
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
        </div>

        <!-- Recently Added — surfaces the most common real-world lookup
             ("what got added recently") without drilling into a category. -->
        <div class="otrs-card mb-4">
            <div class="otrs-card-top">
                <span style="font-size: var(--otrs-text-md); font-weight: 700;">
                    <i class="bi bi-clock-history text-primary me-2"></i>Recently Added
                </span>
            </div>
            <div class="otrs-card-body" id="recentIssuances">
                <div class="text-muted text-center py-3" style="font-size: var(--otrs-text-sm);">
                    <i class="bi bi-hourglass-split me-1"></i>Loading&hellip;
                </div>
            </div>
        </div>

        <!-- Statistics Panel -->
        <?php if (!empty($referenceCounts)): ?>
        <div class="otrs-card mb-4">
            <div class="otrs-card-top">
                <span style="font-size: var(--otrs-text-md); font-weight: 700;">
                    <i class="bi bi-bar-chart-fill text-primary me-2"></i>Quick Statistics Overview
                </span>
            </div>
            <div class="otrs-card-body">
                <div class="otrs-stat-strip mb-0">
                    <div class="otrs-stat-pill">
                        <div class="sp-icon" style="background: var(--otrs-info-bg); color: var(--otrs-info);"><i class="bi bi-files"></i></div>
                        <div>
                            <div class="sp-val"><?= array_sum($referenceCounts) ?></div>
                            <div class="sp-lbl">Total Documents</div>
                        </div>
                    </div>
                    <div class="otrs-stat-pill">
                        <div class="sp-icon" style="background: var(--otrs-success-bg); color: var(--otrs-success);"><i class="bi bi-folder2-open"></i></div>
                        <div>
                            <div class="sp-val"><?= count($referenceCounts) ?></div>
                            <div class="sp-lbl">Active Categories</div>
                        </div>
                    </div>
                    <div class="otrs-stat-pill">
                        <div class="sp-icon" style="background: var(--otrs-info-bg); color: var(--otrs-info);"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <div class="sp-val"><?= date('Y') ?></div>
                            <div class="sp-lbl">Current Year</div>
                        </div>
                    </div>
                    <div class="otrs-stat-pill">
                        <div class="sp-icon" style="background: var(--otrs-warning-bg); color: var(--otrs-warning);"><i class="bi bi-graph-up-arrow"></i></div>
                        <div>
                            <div class="sp-val"><?= max($referenceCounts) ?></div>
                            <div class="sp-lbl">Most Popular</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Enhanced Modal for Adding Reference -->
    <?php if ($isAdmin): ?>
    <div class="modal fade" id="addReferenceModal" tabindex="-1" aria-labelledby="addReferenceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 p-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="addReferenceModalLabel">
                            <i class="bi bi-file-earmark-plus me-2"></i>
                            Add New Reference Document
                        </h5>
                        <p class="mb-0 small opacity-75">Fill in the details to add a new document to the system</p>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addReferenceForm" method="POST" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <div class="row g-4">
                            <!-- Reference Type -->
                            <div class="col-12">
                                <div class="card border-0 bg-light rounded-3">
                                    <div class="card-body p-4">
                                        <label for="referenceType" class="form-label fw-semibold d-flex align-items-center mb-2" style="font-size: var(--otrs-text-sm);">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="bi bi-tag-fill text-primary"></i>
                                            </div>
                                            <span>Document Category <span class="text-danger">*</span></span>
                                        </label>
                                        <select class="form-select border-2" id="referenceType" name="referenceType" required>
                                            <option value="">Select a document category</option>
                                            <?php foreach ($referenceTypes as $refId => $title): ?>
                                                <option value="<?= $refId ?>"><?= htmlspecialchars($title) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text mt-2">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Choose the appropriate category for this document
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- File Name -->
                            <div class="col-12">
                                <div class="card border-0 bg-light rounded-3">
                                    <div class="card-body p-4">
                                        <label for="fileName" class="form-label fw-semibold d-flex align-items-center mb-2" style="font-size: var(--otrs-text-sm);">
                                            <div class="bg-success bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="bi bi-file-text-fill text-success"></i>
                                            </div>
                                            <span>Document Name <span class="text-danger">*</span></span>
                                        </label>
                                        <textarea class="form-control border-2" id="fileName" name="fileName" placeholder="Enter a clear and descriptive name for the document" rows="3" required></textarea>
                                        <div class="form-text mt-2">
                                            <i class="bi bi-lightbulb me-1"></i>
                                            Tip: Use a descriptive name that clearly identifies the document purpose
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Date and File Upload Row -->
                            <div class="col-md-6">
                                <div class="card border-0 bg-light rounded-3 h-100">
                                    <div class="card-body p-4">
                                        <label for="issuanceDate" class="form-label fw-semibold d-flex align-items-center mb-2" style="font-size: var(--otrs-text-sm);">
                                            <div class="bg-info bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="bi bi-calendar-event-fill text-info"></i>
                                            </div>
                                            <span>Date of Issuance <span class="text-danger">*</span></span>
                                        </label>
                                        <input type="date" class="form-control border-2" id="issuanceDate" name="issuanceDate" required>
                                        <div class="form-text mt-2">
                                            <i class="bi bi-clock me-1"></i>
                                            Select the official issuance date
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="card border-0 bg-light rounded-3 h-100">
                                    <div class="card-body p-4">
                                        <label for="uploadFile" class="form-label fw-semibold d-flex align-items-center mb-2" style="font-size: var(--otrs-text-sm);">
                                            <div class="bg-warning bg-opacity-10 rounded-circle p-2 me-2">
                                                <i class="bi bi-cloud-upload-fill text-warning"></i>
                                            </div>
                                            <span>Upload PDF File <span class="text-danger">*</span></span>
                                        </label>
                                        <input type="file" class="form-control border-2" id="uploadFile" name="uploadFile" accept=".pdf" required>
                                        <div class="form-text mt-2">
                                            <i class="bi bi-file-pdf me-1"></i>
                                            PDF format only, maximum size 10MB
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Info Alert -->
                        <div class="otrs-alert otrs-alert-info mt-4">
                            <i class="bi bi-info-circle-fill"></i>
                            <div>
                                <strong>Before you submit</strong>
                                <ul class="mb-0 mt-1" style="padding-left: 1.1rem;">
                                    <li>Ensure all required fields are completed</li>
                                    <li>Verify the document is in PDF format</li>
                                    <li>Check that the file size doesn't exceed 10MB</li>
                                    <li>Review the document name for clarity</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light p-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4 py-2 shadow-sm">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Add Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

    <!---------------------------------------------------- Scripts Here ---------------------------------------------------->
    <?php include 'vendor_js.html' ?>
    
    <script>
        // Pass PHP admin status to JavaScript
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    </script>

    <script>
        <?php include 'js/rood_orders.js' ?>
    </script>
    <!-- Year-tab click handling (with skeleton loader + card animation) now
         lives in initializeYearFilter()/reloadDashboard() in rood_orders.js
         above — kept as a single implementation shared with the category
         pages instead of a second, dashboard-only copy. -->

    <!---------------------------------------------------- Footer ---------------------------------------------------->
    <?php include 'inc/footer.php' ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>