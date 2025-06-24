<?php
//SESSION TIMEOUT CHECK - Checks if the user was timed out already, before allowing them to log back in again
function checkSessionTimeout($redirectTo = '../pages/login.php') {
    if (session_status() === PHP_SESSION_NONE) session_start();

    $timeoutDuration = 900; // This is 15 minutes
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeoutDuration) {
        session_unset();
        session_destroy();
        header("Location: {$redirectTo}?error=sessiontimeout");
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

//RATE LIMITING - This will limit the amount of attempts a ip takes when logging in.
function recordLoginAttemptDB($conn, $ip, $resource) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, resource, attempt_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $ip, $resource);
    $stmt->execute();
    $stmt->close();
}
// Same function from above but for the signup.
function recordSignupAttemptDB($conn, $ip, $resource) {
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, resource, attempt_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("ss", $ip, $resource);
    $stmt->execute();
    $stmt->close();
}

function isRateLimitedDB($conn, $ip, $resource, $maxAttempts, $intervalSeconds) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND resource = ? AND attempt_time > (NOW() - INTERVAL ? SECOND)");
    $stmt->bind_param("ssi", $ip, $resource, $intervalSeconds);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    return $count >= $maxAttempts;
}

//ADMIN AUDIT LOGGING - You would put a line of code like 'logAdminAction($conn, $_SESSION['admin_id'], 'typetheaction', 'then the reason' );
function logAdminAction($conn, $adminId, $eventType, $details = "") {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO admin_audit_logs (admin_id, event_type, details, event_time, ip_address) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->bind_param("isss", $adminId, $eventType, $details, $ip);
    $stmt->execute();
    $stmt->close();
}

?>
