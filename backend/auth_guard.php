<?php
// Shared auth gate for all rood_orders backend endpoints (fetch/insert/update/delete).
// Loads the platform session gate (blocks unauthenticated requests with a 401 JSON
// response for AJAX callers) and exposes an admin check against the same
// system_access.rooms column the front-end pages already use to show/hide controls.
require_once __DIR__ . '/../../includes/bootstrap.php';

function rood_is_admin(): bool
{
    global $conn;

    if (empty($_SESSION['id'])) {
        return false;
    }

    $userId = (int) $_SESSION['id'];
    $stmt = $conn->prepare("SELECT rooms FROM system_access WHERE id = :id LIMIT 1");
    $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row && strtolower((string) $row['rooms']) === 'admin';
}

// Call at the top of any endpoint that creates/modifies/deletes a record.
function rood_require_admin(): void
{
    if (!rood_is_admin()) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
        exit;
    }
}

/**
 * Lightweight session-based rate limit for write endpoints (insert/update/
 * delete), following this codebase's existing convention of session-backed
 * counters rather than a new DB table (see public_html/CLAUDE.md's api/
 * endpoints: "session-based counters (per IP hash)").
 *
 * Deliberately separate from ImisSecurityManager::checkRateLimit(), which is
 * tuned for login brute-force (5 attempts / 15 min) — far too strict for a
 * legitimate admin doing a batch upload session. This uses a generous
 * threshold aimed at scripted abuse, not normal editing pace.
 *
 * Call at the top of any endpoint that creates/modifies/deletes a record,
 * after rood_require_admin().
 */
function rood_enforce_rate_limit(string $bucket, int $maxAttempts = 30, int $windowSeconds = 300): void
{
    $key = '_rood_rl_' . $bucket;
    $now = time();

    $timestamps = $_SESSION[$key] ?? [];
    $timestamps = array_values(array_filter($timestamps, static fn($t) => $t > $now - $windowSeconds));

    if (count($timestamps) >= $maxAttempts) {
        if (!headers_sent()) {
            http_response_code(429);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Too many requests. Please wait a few minutes and try again.',
        ]);
        exit;
    }

    $timestamps[] = $now;
    $_SESSION[$key] = $timestamps;
}

/**
 * Verifies an uploaded file is actually a PDF by content, not just by its
 * client-supplied extension/name. Extension checks alone accept a renamed
 * non-PDF (e.g. malicious.html renamed to malicious.pdf); this inspects the
 * real file signature via fileinfo.
 */
function rood_is_valid_pdf(string $tmpPath): bool
{
    if (!is_file($tmpPath)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        // fileinfo unavailable — fail closed rather than silently skipping
        // the check (extension validation still applies as the first line
        // of defense at the call site).
        return false;
    }

    $mime = finfo_file($finfo, $tmpPath);
    finfo_close($finfo);

    return $mime === 'application/pdf';
}
