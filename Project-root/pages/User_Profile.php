<?php
session_start();

define('ROOT_DIR', dirname(__DIR__));

// Load .env so functions.sn.php can read keys via getenv()
(function () {
    $env = ROOT_DIR . '/.env';
    if (is_file($env) && is_readable($env)) {
        $pairs = parse_ini_file($env, false, INI_SCANNER_RAW) ?: [];
        foreach ($pairs as $k => $v) {
            if (is_string($v) && strlen($v) >= 2) {
                $q = $v[0];
                $r = substr($v, -1);
                if (($q === '"' && $r === '"') || ($q === "'" && $r === "'")) $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
})();

require_once ROOT_DIR . '/includes/security.sn.php';
checkSessionTimeout();
require_once ROOT_DIR . '/DatabaseConnection/config.php';
require_once '../includes/election_stats.php';

$lastLogin = getLastUserLogin($conn);

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// DB connect
require_once ROOT_DIR . '/DatabaseConnection/config.php';

// Fallback connect if config.php didn't instantiate $conn
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($host, $username, $password, $database)) {
        $conn = @mysqli_connect($host, $username, $password, $database);
        if (!$conn) {
            die("Failed to connect to Database: " . mysqli_connect_error());
        }
    } else {
        die("Database configuration not available.");
    }
}
mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Australia/Melbourne');

// Crypto/bootstrap used by your existing code
require_once ROOT_DIR . '/includes/functions.sn.php';

/* ---------- Helpers ---------- */
function tableExists(mysqli $conn, string $name): bool
{
    $q = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $q->bind_param("s", $name);
    $q->execute();
    $q->store_result();
    $ok = $q->num_rows > 0;
    $q->free_result();
    return $ok;
}

function dec_cbc(?string $ciphertext, string $iv): string
{
    if ($ciphertext === null || $ciphertext === '') return '';
    if (!defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) return '';
    $key = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return ($plain === false) ? '' : $plain;
}

function enc_cbc(string $plaintext, string $iv): string
{
    $key = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $ct  = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($ct === false) throw new RuntimeException('Encryption failed');
    return $ct;
}

/* ---------- AJAX Handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_SESSION['user_id'])) {
            throw new RuntimeException('Session expired. Please log in again.');
        }

        $userId = (int)$_SESSION['user_id'];
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'update_profile':
                $rs = $conn->prepare("SELECT iv FROM users WHERE user_id = ? LIMIT 1");
                $rs->bind_param("i", $userId);
                $rs->execute();
                $ivRes = $rs->get_result();
                if ($ivRes->num_rows === 0) {
                    throw new RuntimeException('User not found');
                }
                $iv = ($ivRes->fetch_assoc())['iv'];

                $first_name = trim((string)($_POST['first_name'] ?? ''));
                $last_name  = trim((string)($_POST['last_name'] ?? ''));
                $email      = trim((string)($_POST['email'] ?? ''));
                $dob        = trim((string)($_POST['date_of_birth'] ?? ''));

                if ($first_name === '' || $last_name === '' || $email === '') {
                    throw new RuntimeException('First name, Last name and Email are required.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Invalid email address.');
                }
                if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
                    throw new RuntimeException('Invalid date format for Date of Birth (YYYY-MM-DD).');
                }

                $encFirst = enc_cbc($first_name, $iv);
                $encLast  = enc_cbc($last_name, $iv);
                $encEmail = enc_cbc($email, $iv);

                $emailBlindIndex = hash_hmac('sha256', $email, TRUE_BLIND_INDEX_SECRET_KEY, true);

                $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, email_blind_index = ?, date_of_birth = ? WHERE user_id = ?");
                $upd->bind_param("sssssi", $encFirst, $encLast, $encEmail, $emailBlindIndex, $dob, $userId);
                if (!$upd->execute()) {
                    throw new RuntimeException('Database update failed.');
                }

                echo json_encode([
                    'success'     => true,
                    'first_name'  => $first_name,
                    'last_name'   => $last_name,
                    'email'       => $email,
                    'dob'         => $dob,
                    'message'     => 'Profile updated successfully.'
                ]);
                exit;

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

            case 'change_password':
                $current = (string)($_POST['currentPassword'] ?? '');
                $new     = (string)($_POST['newPassword'] ?? '');
                $verify  = (string)($_POST['verifyPassword'] ?? '');

                if ($current === '' || $new === '' || $verify === '') {
                    throw new RuntimeException('All password fields are required.');
                }
                if ($new !== $verify) {
                    throw new RuntimeException('New passwords do not match.');
                }
                if (strlen($new) < 8) {
                    throw new RuntimeException('Password must be at least 8 characters.');
                }

                $q = $conn->prepare("SELECT hash_password, salt, iterations FROM users WHERE user_id = ? LIMIT 1");
                $q->bind_param("i", $userId);
                $q->execute();
                $res = $q->get_result();
                if ($res->num_rows === 0) {
                    throw new RuntimeException('User not found.');
                }
                $row = $res->fetch_assoc();

                $calc = hash_pbkdf2("sha256", $current, $row['salt'], (int)$row['iterations'], 32, true);
                if (!hash_equals($row['hash_password'], $calc)) {
                    throw new RuntimeException('Current password is incorrect.');
                }

                $newSalt = random_bytes(16);
                $newIter = 100000;
                $newHash = hash_pbkdf2("sha256", $new, $newSalt, $newIter, 32, true);

                $u = $conn->prepare("UPDATE users SET hash_password = ?, salt = ?, iterations = ? WHERE user_id = ?");
                $u->bind_param("ssii", $newHash, $newSalt, $newIter, $userId);
                if (!$u->execute()) {
                    throw new RuntimeException('Failed to update password.');
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

            default:
                throw new RuntimeException('Invalid action');
        }
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

/* ---------- Build $user for the view ---------- */
$userId = (int)$_SESSION["user_id"];
$user = [
    'first_name' => '',
    'middle_name' => '',
    'last_name' => '',
    'email' => '',
    'voter_id' => $userId,
    'dob' => '',
    'address' => '',
    'elections' => []
];

$userQuery = "SELECT user_id, first_name, middle_name, last_name, email, iv, date_created, date_of_birth, address 
              FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die("User not found");
}
$row = $result->fetch_assoc();

$iv = $row['iv'] ?? '';
$user['first_name']  = dec_cbc($row['first_name'] ?? null, $iv);
$user['middle_name'] = dec_cbc($row['middle_name'] ?? null, $iv);
$user['last_name']   = dec_cbc($row['last_name'] ?? null, $iv);
$user['email']       = dec_cbc($row['email'] ?? null, $iv);
$user['dob']         = $row['date_of_birth'] ?? '';
$user['address']     = $row['address'] ?? '';

/* ---------- Elections ---------- */
$hasElection = tableExists($conn, 'election');
$hasBallot   = tableExists($conn, 'ballot'); // Fix: ballot is the real vote table

if ($hasElection) {
    $now = new DateTime();
    if ($hasBallot) {
        $sql = "
          SELECT e.poll_id, e.election_name, e.start_datetime, e.end_datetime,
                 EXISTS(SELECT 1 FROM ballot b WHERE b.user_id = ? AND b.poll_id = e.poll_id) AS has_voted
          FROM election e
          WHERE (e.start_datetime IS NOT NULL OR e.end_datetime IS NOT NULL)
          ORDER BY e.start_datetime ASC
        ";
        $est = $conn->prepare($sql);
        $est->bind_param("i", $userId);
    } else {
        $sql = "
          SELECT e.poll_id, e.election_name, e.start_datetime, e.end_datetime,
                 0 AS has_voted
          FROM election e
          WHERE (e.start_datetime IS NOT NULL OR e.end_datetime IS NOT NULL)
          ORDER BY e.start_datetime ASC
        ";
        $est = $conn->prepare($sql);
    }
    $est->execute();
    $res = $est->get_result();

    while ($e = $res->fetch_assoc()) {
        $start = $e['start_datetime'] ? new DateTime($e['start_datetime']) : null;
        $end   = $e['end_datetime']   ? new DateTime($e['end_datetime'])   : null;

        if ($start && $now < $start) {
            $status = 'Starts in ' . $now->diff($start)->format('%a days %h hours');
        } elseif ($end && $now > $end) {
            $status = 'Election Expired';
        } elseif ($start && $end && $now >= $start && $now <= $end) {
            $status = 'Ends in ' . $now->diff($end)->format('%a days %h hours');
        } else {
            $status = 'Schedule TBA';
        }

        $user['elections'][] = [
            'name'       => $e['election_name'],
            'enrolled'   => true,
            'voted'      => (bool)$e['has_voted'],
            'status'     => $status,
            'start_time' => $e['start_datetime'],
            'end_time'   => $e['end_datetime']
        ];
    }
}

/* ---------- Avatar URL ---------- */
$avatarFs = ROOT_DIR . '/Assets/img/avatar.jpg';
$avatarUrl = '../Assets/img/avatar.jpg';
if (is_file($avatarFs)) {
    $mtime = @filemtime($avatarFs) ?: time();
    $avatarUrl .= '?v=' . $mtime;
} else {
    $avatarUrl = 'https://www.svgrepo.com/show/510930/user-circle.svg';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Votify - User Profile</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <link rel="stylesheet" href="../Assets/css/User_Profile.css" />
    <link rel="preload" as="image" href="../Assets/img/avatar.jpg" />
</head>

<body>
    <button class="mobile-menu-toggle" aria-label="Toggle menu">
        <ion-icon name="menu-outline"></ion-icon>
    </button>

    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="User_Home.php"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
                <li><a href="User_Profile.php" class="active"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
                <li><a href="User_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
                <li><a href="User_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
                <li><a href="User_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../controllers/Logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar" role="img" aria-label="User profile picture"
                    style="background-image:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>');"></div>
                <div class="profile-header-content">
                    <h1 class="profile-name" id="profile-name">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </h1>
                    <div class="last-login">Last login: <?= htmlspecialchars($lastLogin) ?></div>
                    <button class="change-password-btn" onclick="showPasswordModal()">Change Password</button>
                </div>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="person-outline"></ion-icon>
                    <span>Personal Information</span>
                    <button class="edit-btn" id="edit-personal-info" title="Edit personal info">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>
                </div>
                <table class="personal-info-table">
                    <tr>
                        <td class="info-label">Voter ID</td>
                        <td id="voter-id-value"><?= htmlspecialchars($user['voter_id'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">First Name</td>
                        <td id="first-name-value"><?= htmlspecialchars($user['first_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Last Name</td>
                        <td id="last-name-value"><?= htmlspecialchars($user['last_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Date of Birth</td>
                        <td id="dob-value"><?= htmlspecialchars($user['dob'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Email</td>
                        <td id="email-value"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    </tr>
                </table>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="document-text-outline"></ion-icon>
                    <span>Election Overview</span>
                </div>
                <?php if (empty($user['elections'])): ?>
                    <p>There are no current elections available.</p>
                <?php else: ?>
                    <table class="elections-table">
                        <thead>
                            <tr>
                                <th>Election</th>
                                <th>Enrolment Status</th>
                                <th>Vote Status</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user['elections'] as $election): ?>
                                <tr>
                                    <td><?= htmlspecialchars($election['name']) ?></td>
                                    <td><span class="status-badge enrolled">Enrolled</span></td>
                                    <td>
                                        <span class="status-badge <?= $election['voted'] ? 'voted' : 'not-voted' ?>">
                                            <?= $election['voted'] ? 'Voted' : 'Not Voted' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($election['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="home-outline"></ion-icon>
                    <span>Addresses</span>
                </div>
                <div class="address-section">
                    <div class="address-line">
                        <ion-icon name="location-outline"></ion-icon>
                        <span>Residential: <?= htmlspecialchars($user['address'] ?? '') ?></span>
                    </div>
                    <br />
                    <button class="manage-address-btn" id="manage-address-btn">Manage Addresses</button>
                </div>
            </div>

            <div class="action-section">
                <a href="../pages/FAQs.php" class="action-link" id="help-info-link" target="_blank">
                    <ion-icon name="help-circle-outline"></ion-icon>
                    Help &amp; Info
                </a>
            </div>

            <div class="action-section">
                <a href="../pages/contact.html" class="action-link" id="provide-feedback" target="_blank">
                    <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
                    Provide Feedback
                </a>
            </div>
        </div>
    </main>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Change Password</h3>
            </div>
            <form id="passwordForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="change_password" />
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <input type="password" id="currentPassword" name="currentPassword" required autocomplete="current-password" inputmode="text">
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="newPassword" required autocomplete="new-password" inputmode="text">
                </div>
                <div class="form-group">
                    <label for="verifyPassword">Verify New Password</label>
                    <input type="password" id="verifyPassword" name="verifyPassword" required autocomplete="new-password" inputmode="text">
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="apply-password">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Personal Info Modal -->
    <div id="personalInfoModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Edit Personal Information</h3>
            </div>
            <form id="personalInfoForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_profile" />
                <div class="form-group">
                    <label for="modal-voter-id">Voter ID</label>
                    <input type="text" id="modal-voter-id" value="<?= htmlspecialchars($user['voter_id'] ?? '') ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="modal-first-name">First Name</label>
                    <input type="text" id="modal-first-name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-last-name">Last Name</label>
                    <input type="text" id="modal-last-name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-dob">D.O.B</label>
                    <input type="date" id="modal-dob" name="date_of_birth" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-email">Email</label>
                    <input type="email" id="modal-email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="save-personal-info">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Address Modal -->
    <div id="addressModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Manage Addresses</h3>
            </div>
            <form id="addressForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_address" />
                <div class="form-group">
                    <label for="modal-address">Residential Address</label>
                    <textarea id="modal-address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="save-address">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal helpers
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'block';
                el.setAttribute('aria-hidden', 'false');
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            }
        }

        // Inline confirm
        function inlineConfirm(modalId, message = 'Apply changes?') {
            return new Promise(resolve => {
                const modal = document.getElementById(modalId);
                const content = modal?.querySelector('.modal-content');
                if (!content) {
                    resolve(true);
                    return;
                }

                const existing = content.querySelector('.inline-confirm');
                if (existing) existing.remove();
                content.style.position = 'relative';

                const overlay = document.createElement('div');
                overlay.className = 'inline-confirm';
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');

                const box = document.createElement('div');
                box.className = 'inline-confirm-box';

                const icon = document.createElement('ion-icon');
                icon.setAttribute('name', 'alert-circle-outline');
                icon.className = 'inline-confirm-icon';

                const text = document.createElement('div');
                text.className = 'inline-confirm-text';
                text.textContent = message;

                const btns = document.createElement('div');
                btns.className = 'inline-confirm-actions';

                const noBtn = document.createElement('button');
                noBtn.type = 'button';
                noBtn.className = 'cancel-btn';
                noBtn.textContent = 'Discard';
                const yesBtn = document.createElement('button');
                yesBtn.type = 'button';
                yesBtn.className = 'green-btn';
                yesBtn.textContent = 'Apply';

                btns.append(noBtn, yesBtn);
                box.append(icon, text, btns);
                overlay.append(box);
                content.append(overlay);

                const cleanup = (v) => {
                    overlay.remove();
                    resolve(v);
                };
                noBtn.addEventListener('click', () => cleanup(false), {
                    once: true
                });
                yesBtn.addEventListener('click', () => cleanup(true), {
                    once: true
                });
            });
        }

        function showPasswordModal() {
            const f = document.getElementById('passwordForm');
            if (f) {
                f.reset();
                ['currentPassword', 'newPassword', 'verifyPassword'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = '';
                        el.setAttribute('autocomplete', id === 'currentPassword' ? 'current-password' : 'new-password');
                    }
                });
            }
            openModal('passwordModal');
        }

        // Improved AJAX handler
        async function handleAjaxRequest(formElement, successCallback) {
            try {
                const formData = new FormData(formElement);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const text = await response.text();
                let data = null;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response');
                }

                if (!response.ok) {
                    throw new Error(data?.message || 'Request failed');
                }

                if (!data || typeof data.success === 'undefined') {
                    throw new Error('Invalid response format');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Operation failed');
                }

                if (successCallback) {
                    successCallback(data);
                }

                return data;
            } catch (error) {
                console.error('AJAX error:', error);
                alert('Error: ' + (error.message || 'Operation failed'));
                throw error;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Mobile menu toggle
            document.querySelector('.mobile-menu-toggle')?.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.toggle('active');
            });

            // Modal open buttons
            document.getElementById('edit-personal-info')?.addEventListener('click', () => {
                openModal('personalInfoModal');
            });

            document.getElementById('manage-address-btn')?.addEventListener('click', () => {
                openModal('addressModal');
            });

            // Cancel buttons
            document.querySelectorAll('.cancel-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        if (modal.id === 'passwordModal') {
                            document.getElementById('passwordForm')?.reset();
                        }
                        closeModal(modal.id);
                    }
                });
            });

            // Close when clicking outside modal
            window.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target.id);
                }
            });

            // Password form submission
            const pwForm = document.getElementById('passwordForm');
            pwForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('passwordModal', 'Apply password change?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });

            // Personal info form submission
            const piForm = document.getElementById('personalInfoForm');
            piForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('personalInfoModal', 'Apply profile changes?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        document.getElementById('profile-name').textContent = `${data.first_name} ${data.last_name}`;
                        document.getElementById('first-name-value').textContent = data.first_name;
                        document.getElementById('last-name-value').textContent = data.last_name;
                        document.getElementById('email-value').textContent = data.email;
                        document.getElementById('dob-value').textContent = data.dob;
                        closeModal('personalInfoModal');
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });

            // Address form submission
            const addrForm = document.getElementById('addressForm');
            addrForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('addressModal', 'Apply address changes?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        const firstLine = document.querySelector('.address-line span');
                        if (firstLine) firstLine.textContent = `Residential: ${data.address}`;
                        closeModal('addressModal');
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });
        });
    </script>
</body>

</html>