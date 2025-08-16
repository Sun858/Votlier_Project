<?php
session_start();
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/profiles.sn.php';
require_once '../includes/election_stats.php';

checkSessionTimeout();
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/stafflogin.php");
    exit();
}
$adminId = (int)$_SESSION["admin_id"];
$admin = getProfile($conn, $adminId, 'admin');
if (!$admin) die("Admin not found");
$lastLogin = getLastAdminLogin($conn);

$elections = [];
$est = $conn->prepare("SELECT poll_id, election_name, start_datetime, end_datetime FROM election ORDER BY start_datetime ASC");
if ($est) {
    $est->execute();
    $eRes = $est->get_result();
    while ($erow = $eRes->fetch_assoc()) {
        $elections[] = $erow;
    }
}

// Handle AJAX POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'update_admin_profile':
            case 'update_profile': // allow both actions for robustness
                $dob = trim($_POST['dob'] ?? '');
                if ($dob === '') $dob = null;
                updateProfile(
                    $conn,
                    $adminId,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['email']),
                    $dob,
                    'admin'
                );
                echo json_encode(['success' => true]);
                exit;
                break;

            case 'change_admin_password':
            case 'change_password': // allow both for robustness
                $current = (string)($_POST['currentPassword'] ?? '');
                $new     = (string)($_POST['newPassword'] ?? '');
                $verify  = (string)($_POST['verifyPassword'] ?? '');

                if ($current === '' || $new === '' || $verify === '') {
                    echo json_encode(['success' => false, 'message' => 'All password fields are required.']);
                    exit;
                }
                if ($new !== $verify) {
                    echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
                    exit;
                }
                if (strlen($new) < 8) {
                    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
                    exit;
                }

                $q = $conn->prepare("SELECT hash_password, salt, iterations FROM administration WHERE admin_id = ? LIMIT 1");
                $q->bind_param("i", $adminId);
                $q->execute();
                $res = $q->get_result();
                if ($res->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'Admin not found.']);
                    exit;
                }
                $row = $res->fetch_assoc();

                $calc = hash_pbkdf2("sha256", $current, $row['salt'], (int)$row['iterations'], 32, true);
                if (!hash_equals($row['hash_password'], $calc)) {
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                    exit;
                }

                $newSalt = random_bytes(16);
                $newIter = 100000;
                $newHash = hash_pbkdf2("sha256", $new, $newSalt, $newIter, 32, true);

                $u = $conn->prepare("UPDATE administration SET hash_password = ?, salt = ?, iterations = ? WHERE admin_id = ?");
                $u->bind_param("ssii", $newHash, $newSalt, $newIter, $adminId);
                if (!$u->execute()) {
                    echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
                    exit;
                }

                // Destroy session + cookies
                $_SESSION = [];
                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie(session_name(), '', [
                            'expires'  => time() - 42000,
                            'path'     => $params['path'],
                            'domain'   => $params['domain'],
                            'secure'   => $params['secure'],
                            'httponly' => $params['httponly'],
                            'samesite' => 'Lax',
                        ]);
                    } else {
                        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
                    }
                }
                session_destroy();

                echo json_encode([
                    'success'  => true,
                    'redirect' => '../pages/stafflogin.php?reason=pwchange'
                ]);
                exit;
                break;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>