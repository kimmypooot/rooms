<?php
// CLI-only maintenance job: permanently removes rood_orders rows (and their
// PDFs) that were soft-deleted more than RETENTION_DAYS ago. Soft-delete
// (backend/delete/rood_delete_reference.php) only sets deleted_at — this
// script is what actually reclaims the disk space and DB row, on a delay,
// so an accidental or malicious delete has a recovery window.
//
// Not web-reachable by design (php_sapi_name() guard below). Wire into cron:
//   php /path/to/imis/rooms/backend/maintenance/rood_purge_deleted.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../../../includes/connect.php';

const RETENTION_DAYS = 30;

$stmt = $conn->prepare(
    "SELECT id, upload_file FROM rood_orders
     WHERE deleted_at IS NOT NULL AND deleted_at < DATE_SUB(NOW(), INTERVAL :days DAY)"
);
$stmt->bindValue(':days', RETENTION_DAYS, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo "No records past the " . RETENTION_DAYS . "-day retention window.\n";
    exit(0);
}

$deleteStmt = $conn->prepare("DELETE FROM rood_orders WHERE id = :id");
$purged = 0;

foreach ($rows as $row) {
    $filePath = __DIR__ . '/../../' . $row['upload_file'];
    if (!empty($row['upload_file']) && is_file($filePath)) {
        unlink($filePath);
    }

    $deleteStmt->bindValue(':id', $row['id'], PDO::PARAM_INT);
    $deleteStmt->execute();
    $purged++;
}

echo "Purged {$purged} record(s) soft-deleted more than " . RETENTION_DAYS . " day(s) ago.\n";
