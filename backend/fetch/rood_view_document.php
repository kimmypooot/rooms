<?php
// Streams a document's PDF with a clean URL and filename, instead of the
// browser navigating straight to the raw uploads/<category>/<year>/<file>.pdf
// path (which exposed the internal folder structure in the address bar/tab
// title and offered an ugly filename on "Save As").
require '../auth_guard.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid document ID.');
}

$stmt = $conn->prepare("SELECT file_name, upload_file FROM rood_orders WHERE id = ? AND deleted_at IS NULL");
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row || empty($row['upload_file'])) {
    http_response_code(404);
    exit('Document not found.');
}

// upload_file is stored relative to rooms/ (e.g. "uploads/Regional Office
// Orders/2024/Foo.pdf"). Resolve it and verify — defense in depth against a
// tampered/unexpected DB value — that it actually resolves inside rooms/uploads/.
$roomsDir     = dirname(__DIR__, 2);
$uploadsRoot  = realpath($roomsDir . '/uploads');
$resolvedPath = realpath($roomsDir . '/' . ltrim($row['upload_file'], '/'));

if ($uploadsRoot === false || $resolvedPath === false || strpos($resolvedPath, $uploadsRoot . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Document not found.');
}

// Build a clean, filesystem-safe filename from the free-text document name
// (same whitelist used on upload) so "Save As" doesn't dump the raw stored
// filename (which embeds the issuance date suffix, collision counters, etc).
$cleanName = preg_replace('/[^a-zA-Z0-9_\-\s.]/', '', $row['file_name']);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
if ($cleanName === '') {
    $cleanName = 'document';
}
$cleanName .= '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $cleanName . '"');
header('Content-Length: ' . filesize($resolvedPath));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($resolvedPath);
exit;
