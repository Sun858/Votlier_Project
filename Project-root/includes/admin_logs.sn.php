<?php
// Fetches admin audit logs with pagination, optionally filtered by event_type or admin_id.
// Now fetches encrypted names & IV for later decryption
// Added a limit and offset for pagination.
function getAdminAuditLogs($conn, $filterEventType = '', $filterAdminId = '', $limit = null, $offset = 0) {
    $sql = "SELECT l.log_id, l.admin_id, a.first_name, a.last_name, a.iv as admin_iv, l.event_type, l.details, l.event_time, l.ip_address
            FROM admin_audit_logs l
            JOIN administration a ON l.admin_id = a.admin_id
            WHERE 1";
    $params = [];
    $types = '';

    if ($filterEventType) {
        $sql .= " AND l.event_type = ?";
        $params[] = $filterEventType;
        $types .= 's';
    }
    if ($filterAdminId) {
        $sql .= " AND l.admin_id = ?";
        $params[] = $filterAdminId;
        $types .= 'i';
    }
    $sql .= " ORDER BY l.event_time DESC";

    // Add LIMIT and OFFSET for pagination
    if ($limit !== null) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
    }

    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $logs = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $logs;
}

// Counts total admin audit logs, optionally filtered.
// Added for pagination in compare to previously saved code.
function getTotalAdminAuditLogsCount($conn, $filterEventType = '', $filterAdminId = '') {
    $sql = "SELECT COUNT(*) FROM admin_audit_logs l WHERE 1";
    $params = [];
    $types = '';

    if ($filterEventType) {
        $sql .= " AND l.event_type = ?";
        $params[] = $filterEventType;
        $types .= 's';
    }
    if ($filterAdminId) {
        $sql .= " AND l.admin_id = ?";
        $params[] = $filterAdminId;
        $types .= 'i';
    }

    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    $stmt->close();
    return $count;
}

// Get all unique event types for filter dropdown.
function getAuditLogEventTypes($conn) {
    $result = $conn->query("SELECT DISTINCT event_type FROM admin_audit_logs ORDER BY event_type ASC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get all admins for filter dropdown (returns encrypted names + IV)
function getAuditLogAdmins($conn) {
    $result = $conn->query("SELECT admin_id, first_name, last_name, iv FROM administration ORDER BY first_name, last_name");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Decrypt admin name with your project keys and cipher
function decryptAdminNameFromRow($row) {
    $cipher = "aes-256-cbc";
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $first = openssl_decrypt($row['first_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $row['iv']);
    $last = openssl_decrypt($row['last_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $row['iv']);
    return trim($first . ' ' . $last);
}

// For logs, where iv is admin_iv
function decryptAdminNameForLog($first, $last, $iv) {
    $cipher = "aes-256-cbc";
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $first = openssl_decrypt($first, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $last = openssl_decrypt($last, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    return trim($first . ' ' . $last);
}
?>