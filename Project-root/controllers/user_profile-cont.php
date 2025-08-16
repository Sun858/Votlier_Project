<?php
session_start();
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/profiles.sn.php';
require_once '../includes/election_stats.php';

checkSessionTimeout();
if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}
$userId = (int)$_SESSION["user_id"];
$user = getProfile($conn, $userId);
if (!$user) die("User not found");
$user['elections'] = getUserElectionsOverview($conn, $userId);
$lastLogin = getLastUserLogin($conn);

// Handle AJAX POST actions
// Handle AJAX POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'update_profile':
                $dob = trim($_POST['date_of_birth']);
                if ($dob === '') $dob = null;
                updateProfile(
                    $conn,
                    $userId,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['email']),
                    $dob,      // <-- Now uses null if blank
                    'user'
                );
                echo json_encode(['success' => true]);
                exit;
                break;

            case 'change_password':
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

                $q = $conn->prepare("SELECT hash_password, salt, iterations FROM users WHERE user_id = ? LIMIT 1");
                $q->bind_param("i", $userId);
                $q->execute();
                $res = $q->get_result();
                if ($res->num_rows === 0) {
                    echo json_encode(['success' => false, 'message' => 'User not found.']);
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

                $u = $conn->prepare("UPDATE users SET hash_password = ?, salt = ?, iterations = ? WHERE user_id = ?");
                $u->bind_param("ssii", $newHash, $newSalt, $newIter, $userId);
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
                    'redirect' => '../controllers/Logout.php?reason=pwchange'
                ]);
                exit;
                break;

            case 'update_address':
                $address = trim((string)($_POST['address'] ?? ''));

                if (empty($address)) {
                    throw new RuntimeException('Address cannot be empty.');
                }

                $upd = $conn->prepare("UPDATE users SET address = ? WHERE user_id = ?");
                $upd->bind_param("si", $address, $userId);
                
                if (!$upd->execute()) {
                    throw new RuntimeException('Database update failed.');
                }

                echo json_encode([
                    'success' => true,
                    'address' => $address,
                    'message' => 'Address updated successfully.'
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