<?php
// Shared page template for every issuance category (Regional Office Orders,
// Regional Office Memorandum, Policies on AWA, etc). These pages were
// previously 7 near-identical ~320-line files differing only by $refId and
// the header subtitle text; this template collapses them into one.
//
// Callers must set, before requiring this file:
//   $refId            int    1-7, matches rood_reference_type.id
//   $pageDescription  string subtitle shown under the page title
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($refId) || !isset($pageDescription)) {
    throw new RuntimeException('issuance_page.php requires $refId and $pageDescription to be set before inclusion.');
}

$roomsDir = dirname(__DIR__);

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

$referenceTypes = [
    1 => "Competency-Based Recruitment and Promotion Issuances",
    2 => "Policies on Alternative Work Arrangements (AWA)",
    3 => "Policies on Attendance and Leave Administration",
    4 => "Regional Office Memorandum",
    5 => "Regional Office Orders",
    6 => "Training Related Issuances",
    7 => "Welfare Fund and Employees' Benefits Related Issuances"
];

$title = $referenceTypes[$refId] ?? 'Unknown Reference';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <title>CSC RO VIII - ROOMS</title>
    <meta content="" name="description">
    <meta content="" name="keywords">

    <?php include $roomsDir . '/vendor_css.html' ?>
    <?= csrfMeta() ?>
</head>

<style>
    /* Loading spinner styles */
.loading-spinner-overlay {
    position: fixed;
    z-index: 9999;
    background-color: rgba(255, 255, 255, 0.7);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    animation: pulse 1s infinite alternate;
}

.loading-spinner-content .spinner-border {
    width: 40px;
    height: 40px;
    border-width: 4px;
}

@keyframes pulse {
    from { transform: scale(1); opacity: 0.8; }
    to { transform: scale(1.1); opacity: 1; }
}

/* Action buttons container */
.action-buttons {
    display: flex;
    gap: 5px;
    transition: all 0.3s ease;
}
</style>

<!-- SweetAlert2 already loaded by imis/inc/header.php, included just below. -->

<body>
<?php
imis_include('header');
include $roomsDir . '/inc/sidebar.php';
?>

<main id="main" class="main">
    <div class="container-fluid px-4 py-2">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-2">
            <ol class="breadcrumb mb-0" style="font-size: var(--otrs-text-sm);">
                <li class="breadcrumb-item"><a href="index">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($title); ?></li>
            </ol>
        </nav>

        <!-- Page Header -->
        <div class="pagetitle otrs-ph">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="otrs-ph-icon icon-blue"><i class="bi bi-file-earmark-text-fill"></i></div>
                    <div>
                        <h1><?php echo htmlspecialchars($title); ?></h1>
                        <p class="text-muted mb-0" style="font-size: var(--otrs-text-sm);">
                            <i class="bi bi-info-circle me-1"></i><?php echo htmlspecialchars($pageDescription); ?>
                        </p>
                    </div>
                </div>
                <?php if ($isAdmin): ?>
                <button type="button" class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addReferenceModal">
                    <i class="bi bi-plus-circle me-2"></i>Add New Issuance
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter by Year — same segmented-tab pattern as the dashboard -->
        <div class="otrs-card mb-4">
            <div class="otrs-card-top">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-funnel-fill text-primary"></i>
                    <span style="font-size: var(--otrs-text-md); font-weight: 700;">Filter by Year</span>
                </div>
            </div>
            <div class="otrs-card-body">
                <div class="seg-tabs" id="yearTabs" role="tablist">
                    <button class="seg-tab active" data-year="All" role="tab">
                        <i class="bi bi-calendar-range-fill"></i> All Years
                    </button>
                    <?php
                        $currentYear = date('Y');
                        for ($y = $currentYear; $y >= 2020; $y--):
                    ?>
                    <button class="seg-tab" data-year="<?= $y ?>" role="tab">
                        <i class="bi bi-calendar3"></i> <?= $y ?>
                    </button>
                    <?php endfor; ?>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="otrs-card mb-4">
            <div class="otrs-card-body">
                <div class="reference-table" id="reference-<?= $refId ?>">
                    <div class="table-responsive">
                        <table id="tableRef<?= $refId ?>" class="table table-bordered table-hover table-striped table-select otrs-table" style="width: 100%">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 fw-semibold">File Name</th>
                                    <th class="text-center py-3 fw-semibold">Issuance Date</th>
                                    <th class="text-center py-3 fw-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTable content will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Add Reference Modal — same design as the dashboard's, so admins see
         one consistent "add a document" pattern everywhere in this module. -->
    <div class="modal fade" id="addReferenceModal" tabindex="-1" aria-labelledby="addReferenceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 p-4">
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="addReferenceModalLabel">
                            <i class="bi bi-file-earmark-plus me-2"></i>
                            Add New Issuance
                        </h5>
                        <p class="mb-0 small opacity-75">Fill in the details to add a new document to this category</p>
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
                                            <?php foreach ($referenceTypes as $id => $type): ?>
                                                <option value="<?= $id ?>" <?= ($refId == $id) ? 'selected' : ''; ?>><?= htmlspecialchars($type) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text mt-2">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Pre-selected to this category — change it if the document belongs elsewhere
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

    <!-- Edit/View Reference Modal -->
    <div class="modal fade" id="editReferenceModal" tabindex="-1" aria-labelledby="editReferenceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
                <div class="modal-header bg-info text-white border-0 p-4">
                    <h5 class="modal-title fw-bold" id="editReferenceModalLabel">
                        <i class="bi bi-eye me-2"></i>View Reference
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editReferenceForm" enctype="multipart/form-data">
                    <div class="modal-body p-4">
                        <input type="hidden" id="editId" name="id">
                        <div class="row g-4">
                            <div class="col-12">
                                <label for="editReferenceType" class="form-label fw-semibold text-muted" style="font-size: var(--otrs-text-sm);">Reference Type</label>
                                <input type="text" class="form-control bg-light" id="editReferenceType" name="referenceType" readonly>
                            </div>

                            <div class="col-12">
                                <label for="editFileName" class="form-label fw-semibold text-muted" style="font-size: var(--otrs-text-sm);">File Name</label>
                                <textarea class="form-control bg-light" id="editFileName" name="fileName" rows="4"></textarea>
                            </div>

                            <div class="col-md-6">
                                <label for="editIssuanceDate" class="form-label fw-semibold text-muted" style="font-size: var(--otrs-text-sm);">Date of Issuance</label>
                                <input type="date" class="form-control bg-light" id="editIssuanceDate" name="issuanceDate">
                            </div>

                            <div class="col-md-6">
                                <label for="editUploadFile" class="form-label fw-semibold text-muted" style="font-size: var(--otrs-text-sm);">Replace File (optional)</label>
                                <input type="file" class="form-control" id="editUploadFile" name="uploadFile" accept=".pdf" disabled>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0 p-4">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 py-2" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-2"></i>Close
                        </button>
                        <?php if ($isAdmin): ?>
                        <button type="button" id="enableEditBtn" class="btn btn-warning rounded-pill px-4 py-2">
                            <i class="bi bi-pencil-square me-2"></i>Edit
                        </button>
                        <button type="submit" id="saveEditBtn" class="btn btn-success rounded-pill px-4 py-2 d-none">
                            <i class="bi bi-save me-2"></i>Save Changes
                        </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

    <!---------------------------------------------------- Scripts Here ---------------------------------------------------->
    <?php include $roomsDir . '/vendor_js.html' ?>
    <script>
        // Pass PHP admin status to JavaScript
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;
    </script>
    <script>
        <?php include $roomsDir . '/js/rood_orders.js' ?>
    </script>

    <!---------------------------------------------------- Footer ---------------------------------------------------->
    <?php include $roomsDir . '/inc/footer.php' ?>
    <a href="#" class="back-to-top d-flex align-items-center justify-content-center">
        <i class="bi bi-arrow-up-short"></i>
    </a>
</body>

</html>
