<?php
require '../auth_guard.php';
rood_require_admin();
rood_enforce_rate_limit('write');
validateCsrfOrAbort();

$response = ['success' => false, 'message' => ''];

try {
    // Validate required fields
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $fileName = isset($_POST['fileName']) ? trim($_POST['fileName']) : '';
    $issuanceDate = isset($_POST['issuanceDate']) ? trim($_POST['issuanceDate']) : '';

    if (empty($id) || empty($fileName) || empty($issuanceDate)) {
        throw new Exception('All fields are required.');
    }

    // Fetch current record to get reference_type and existing file (for possible renaming)
    $stmt = $conn->prepare("SELECT reference_type, upload_file FROM rood_orders WHERE id = ? AND deleted_at IS NULL");
    $stmt->execute([$id]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingRecord) {
        throw new Exception('Record not found.');
    }

    $updatedBy = (int) $_SESSION['id'];

    $referenceType = (int) $existingRecord['reference_type'];
    $oldFileName = $existingRecord['upload_file'];

    // Folder mapping
    $referenceFolders = [
        1 => "Competency-Based Recruitment and Promotion Issuances",
        2 => "Policies on Alternative Work Arrangements (AWA)",
        3 => "Policies on Attendance and Leave Administration",
        4 => "Regional Office Memorandum",
        5 => "Regional Office Orders",
        6 => "Training Related Issuances",
        7 => "Welfare Fund and Employees' Benefits Related Issuances"
    ];

    $baseUploadDir = '../../uploads/';
    $typeFolder = $referenceFolders[$referenceType] ?? 'Other';
    $year = date('Y', strtotime($issuanceDate));
    $uploadDir = $baseUploadDir . $typeFolder . '/' . $year . '/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Check if a new file was uploaded
    if (isset($_FILES['uploadFile']) && $_FILES['uploadFile']['error'] === UPLOAD_ERR_OK) {
        // --- NEW FILE UPLOADED ---
        $file = $_FILES['uploadFile'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // File type validation — unlike insert, this path previously accepted
        // any extension on replace. Same two-step check: extension, then
        // actual file content.
        if ($ext !== 'pdf') {
            throw new Exception("Only PDF files are allowed.");
        }
        if (!rood_is_valid_pdf($file['tmp_name'])) {
            throw new Exception("The uploaded file is not a valid PDF.");
        }

        // Sanitize and format file name (same as insert)
        $cleanFileName = preg_replace('/[^a-zA-Z0-9_\-\s.]/', '', $fileName);
        $cleanFileName = preg_replace('/\s+/', ' ', $cleanFileName);
        $cleanFileName = trim($cleanFileName);

        if (empty($cleanFileName)) {
            throw new Exception("File name contains invalid characters or is empty after sanitization.");
        }

        $datePart = date('Y-m-d', strtotime($issuanceDate));
        $safeName = "{$cleanFileName} - {$datePart}.{$ext}";

        // Prevent filename collision
        $originalName = $safeName;
        $counter = 1;
        while (file_exists($uploadDir . $safeName)) {
            $safeName = pathinfo($originalName, PATHINFO_FILENAME) . " ({$counter}).{$ext}";
            $counter++;
        }

        $targetPath = $uploadDir . $safeName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("File upload failed.");
        }

        // Delete old file if needed
        if ($oldFileName) {
            // Handle both old format (with uploads/) and new format
            $oldFilePath = (strpos($oldFileName, 'uploads/') === 0) 
                ? '../../' . $oldFileName 
                : $baseUploadDir . $oldFileName;
            
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }

        // Store relative path with folder including 'uploads/' and year
        $relativePath = 'uploads/' . $typeFolder . '/' . $year . '/' . $safeName;

        // Update with new file
        $stmt = $conn->prepare("UPDATE rood_orders SET file_name = ?, issuance_date = ?, upload_file = ?, updated_by = ? WHERE id = ?");
        $stmt->execute([$fileName, $issuanceDate, $relativePath, $updatedBy, $id]);
    } else {
        // --- NO NEW FILE UPLOADED --- but filename or date might have changed
        
        // Extract just the filename from the old path (in case it has folder)
        $oldFileNameOnly = basename($oldFileName);
        $oldPath = $uploadDir . $oldFileNameOnly;
        $oldExt = strtolower(pathinfo($oldFileNameOnly, PATHINFO_EXTENSION));

        // Sanitize and format file name (same as insert)
        $cleanFileName = preg_replace('/[^a-zA-Z0-9_\-\s.]/', '', $fileName);
        $cleanFileName = preg_replace('/\s+/', ' ', $cleanFileName);
        $cleanFileName = trim($cleanFileName);

        if (empty($cleanFileName)) {
            throw new Exception("File name contains invalid characters or is empty after sanitization.");
        }

        $datePart = date('Y-m-d', strtotime($issuanceDate));
        $newFileNameOnly = "{$cleanFileName} - {$datePart}.{$oldExt}";

        // Prevent filename collision
        $originalName = $newFileNameOnly;
        $counter = 1;
        while (file_exists($uploadDir . $newFileNameOnly) && $newFileNameOnly !== $oldFileNameOnly) {
            $newFileNameOnly = pathinfo($originalName, PATHINFO_FILENAME) . " ({$counter}).{$oldExt}";
            $counter++;
        }

        $newPath = $uploadDir . $newFileNameOnly;

        // Rename old file if filename changed
        if ($oldFileNameOnly !== $newFileNameOnly && file_exists($oldPath)) {
            rename($oldPath, $newPath);
        }

        // Store relative path with folder including 'uploads/' and year
        $newFileName = 'uploads/' . $typeFolder . '/' . $year . '/' . $newFileNameOnly;

        $stmt = $conn->prepare("UPDATE rood_orders SET file_name = ?, issuance_date = ?, upload_file = ?, updated_by = ? WHERE id = ?");
        $stmt->execute([$fileName, $issuanceDate, $newFileName, $updatedBy, $id]);
    }

    $response['success'] = true;
    $response['message'] = 'Record updated successfully.';
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);