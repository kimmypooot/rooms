<?php
// rood_insert_reference.php
require '../auth_guard.php';
rood_require_admin();
rood_enforce_rate_limit('write');
validateCsrfOrAbort();

// Initialize response
$response = ['success' => false, 'message' => ''];

try {
    // Check PHP upload limits and provide helpful error message
    $phpMaxUpload = ini_get('upload_max_filesize');
    $phpMaxPost = ini_get('post_max_size');
    
    // Detailed validation with specific error messages
    $errors = [];
    
    if (empty($_POST['referenceType'])) {
        $errors[] = "Reference Type is required";
    }
    
    if (empty($_POST['fileName'])) {
        $errors[] = "File Name is required";
    }
    
    if (empty($_POST['issuanceDate'])) {
        $errors[] = "Issuance Date is required";
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['uploadFile']) || $_FILES['uploadFile']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = "Upload File is required";
    } elseif ($_FILES['uploadFile']['error'] !== UPLOAD_ERR_OK) {
        // Handle specific upload errors
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => "File exceeds server upload limit ({$phpMaxUpload}). Please upload a smaller file.",
            UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Server error: Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by server extension'
        ];
        $errors[] = $uploadErrors[$_FILES['uploadFile']['error']] ?? 'Unknown upload error';
    }
    
    // If there are validation errors, throw exception with all errors
    if (!empty($errors)) {
        throw new Exception(implode("\n", $errors));
    }

    // Get and sanitize inputs
    $referenceType = (int) $_POST['referenceType'];
    $fileName = trim($_POST['fileName']);
    $issuanceDate = $_POST['issuanceDate'];
    $file = $_FILES['uploadFile'];

    // Validate reference type
    if ($referenceType < 1 || $referenceType > 7) {
        throw new Exception("Invalid reference type selected.");
    }

    // File type validation — extension first (cheap, clear error message),
    // then the actual file content, since a renamed non-PDF would otherwise
    // pass the extension check.
    $allowedExtensions = ['pdf'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        throw new Exception("Only PDF files are allowed.");
    }
    if (!rood_is_valid_pdf($file['tmp_name'])) {
        throw new Exception("The uploaded file is not a valid PDF.");
    }

    // Get actual uploaded file size and convert PHP ini values to bytes for comparison
    $actualFileSize = $file['size'];
    $maxUploadBytes = convertToBytes($phpMaxUpload);
    $maxPostBytes = convertToBytes($phpMaxPost);
    $scriptMaxSize = 10 * 1024 * 1024; // 10MB
    
    // Use the smallest limit
    $effectiveLimit = min($maxUploadBytes, $maxPostBytes, $scriptMaxSize);
    
    if ($actualFileSize > $effectiveLimit) {
        $effectiveLimitMB = round($effectiveLimit / (1024 * 1024), 2);
        $actualSizeMB = round($actualFileSize / (1024 * 1024), 2);
        throw new Exception("File size ({$actualSizeMB}MB) exceeds the maximum allowed size ({$effectiveLimitMB}MB).");
    }

    // Validate date format
    $dateObj = DateTime::createFromFormat('Y-m-d', $issuanceDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $issuanceDate) {
        throw new Exception("Invalid date format. Please use YYYY-MM-DD.");
    }

    // Extract year from issuance date
    $year = date('Y', strtotime($issuanceDate));

    // Folder mapping based on reference type
    $referenceFolders = [
        1 => "Competency-Based Recruitment and Promotion Issuances",
        2 => "Policies on Alternative Work Arrangements (AWA)",
        3 => "Policies on Attendance and Leave Administration",
        4 => "Regional Office Memorandum",
        5 => "Regional Office Orders",
        6 => "Training Related Issuances",
        7 => "Welfare Fund and Employees' Benefits Related Issuances"
    ];

    $typeFolder = $referenceFolders[$referenceType];
    $baseUploadDir = '../../uploads/';
    
    // Include year in the directory structure
    $uploadDir = $baseUploadDir . $typeFolder . '/' . $year . '/';

    // Create directory if not existing
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory. Please check permissions.");
        }
    }

    // Sanitize and format file name
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

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception("Failed to move uploaded file. Please check server permissions.");
    }

    // Include year in the relative path
    $relativePath = 'uploads/' . $typeFolder . '/' . $year . '/' . $safeName;

    // Insert record into database
    $stmt = $conn->prepare("INSERT INTO rood_orders (reference_type, file_name, issuance_date, upload_file, created_by) VALUES (?, ?, ?, ?, ?)");

    if (!$stmt->execute([$referenceType, $fileName, $issuanceDate, $relativePath, (int) $_SESSION['id']])) {
        // If file was uploaded but DB insert fails, remove the file
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
        throw new Exception("Database error: Failed to insert record.");
    }

    $response['success'] = true;
    $response['message'] = "Reference added successfully!";
    $response['filePath'] = $relativePath;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("ROOD Insert Error: " . $e->getMessage());
}

// Helper function to convert PHP ini size values to bytes
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int) $value;
    
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    
    return $value;
}

header('Content-Type: application/json');
echo json_encode($response);
exit;