<?php
require_once __DIR__ . '/../auth_guard.php';
rood_require_admin();
rood_enforce_rate_limit('write');
validateCsrfOrAbort();

header('Content-Type: application/json');

try {
    // Check if the ID is provided
    if (!isset($_POST['id']) || empty($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        exit;
    }

    $id = intval($_POST['id']);
    $deletedBy = (int) $_SESSION['id'];

    // Confirm the record exists and isn't already deleted.
    $stmt = $conn->prepare("SELECT id FROM rood_orders WHERE id = :id AND deleted_at IS NULL");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'No record found with that ID']);
        exit;
    }

    // Soft-delete: mark the record as removed but keep the row and file on
    // disk for a retention window. This is deliberate — official issuances
    // must be recoverable from an accidental or malicious delete rather than
    // being unlink()'d immediately. Physical purge is a separate scheduled job.
    $deleteStmt = $conn->prepare("UPDATE rood_orders SET deleted_at = NOW(), deleted_by = :deleted_by WHERE id = :id AND deleted_at IS NULL");
    $deleteStmt->bindParam(':deleted_by', $deletedBy, PDO::PARAM_INT);
    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
    $deleteStmt->execute();

    if ($deleteStmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete record']);
    }

} catch (PDOException $e) {
    error_log('ROOD Delete Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'A database error occurred. Please try again.']);
} catch (Exception $e) {
    error_log('ROOD Delete Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>