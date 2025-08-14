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
                $q = $v[0]; $r = substr($v, -1);
                if (($q === '"' && $r === '"') || ($q === "'" && $r === "'")) $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v;
            putenv("$k=$v");
        }
    }
})();

require_once ROOT_DIR . '/includes/security.sn.php';
checkSessionTimeout();

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
        if (!$conn) { die("Failed to connect to Database: " . mysqli_connect_error()); }
    } else {
        die("Database configuration not available.");
    }
}
mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Australia/Melbourne');

// Crypto/bootstrap used by your existing code
require_once ROOT_DIR . '/includes/functions.sn.php';

/* ---------- Helpers ---------- */
function tableExists(mysqli $conn, string $name): bool {
    $q = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $q->bind_param("s", $name);
    $q->execute();
    $q->store_result();
    $ok = $q->num_rows > 0;
    $q->free_result();
    return $ok;
}
function dec_cbc(?string $ciphertext, string $iv): string {
    if ($ciphertext === null || $ciphertext === '') return '';
    if (!defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) return '';
    $key = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return ($plain === false) ? '' : $plain;
}
function enc_cbc(string $plaintext, string $iv): string {
    $key = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $ct  = openssl_encrypt($plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($ct === false) throw new RuntimeException('Encryption failed');
    return $ct;
}

/* ---------- AJAX: Update personal info (DOB = UI-only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    header('Content-Type: application/json');
    try {
        $userId = (int)$_SESSION['user_id'];

        $rs = $conn->prepare("SELECT iv FROM users WHERE user_id = ? LIMIT 1");
        $rs->bind_param("i", $userId);
        $rs->execute();
        $ivRes = $rs->get_result();
        if ($ivRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'User not found']); exit;
        }
        $iv = ($ivRes->fetch_assoc())['iv'];

        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name  = trim((string)($_POST['last_name'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $dob        = trim((string)($_POST['dob'] ?? ''));

        if ($first_name === '' || $last_name === '' || $email === '') {
            echo json_encode(['success' => false, 'message' => 'First name, Last name and Email are required.']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']); exit;
        }

        $encFirst = enc_cbc($first_name, $iv);
        $encLast  = enc_cbc($last_name,  $iv);
        $encEmail = enc_cbc($email,      $iv);

        $emailBlindIndex = hash_hmac('sha256', $email, TRUE_BLIND_INDEX_SECRET_KEY, true);

        $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, email_blind_index = ? WHERE user_id = ?");
        $upd->bind_param("ssssi", $encFirst, $encLast, $encEmail, $emailBlindIndex, $userId);
        if (!$upd->execute()) {
            echo json_encode(['success' => false, 'message' => 'Database update failed.']); exit;
        }

        echo json_encode([
            'success'     => true,
            'first_name'  => $first_name,
            'last_name'   => $last_name,
            'email'       => $email,
            'dob'         => $dob,
            'message'     => 'Profile updated.'
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Update error: ' . $e->getMessage()]); exit;
    }
}

/* ---------- AJAX: Change password (server logs out + client hard-redirects to Logout.php) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    header('Content-Type: application/json');
    try {
        $userId = (int)$_SESSION['user_id'];
        $current = (string)($_POST['currentPassword'] ?? '');
        $new     = (string)($_POST['newPassword'] ?? '');
        $verify  = (string)($_POST['verifyPassword'] ?? '');

        if ($current === '' || $new === '' || $verify === '') {
            echo json_encode(['success' => false, 'message' => 'All password fields are required.']); exit;
        }
        if ($new !== $verify) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']); exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); exit;
        }

        $q = $conn->prepare("SELECT hash_password, salt, iterations FROM users WHERE user_id = ? LIMIT 1");
        $q->bind_param("i", $userId);
        $q->execute();
        $res = $q->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success' => false, 'message' => 'User not found.']); exit; }
        $row = $res->fetch_assoc();

        $calc = hash_pbkdf2("sha256", $current, $row['salt'], (int)$row['iterations'], 32, true);
        if (!hash_equals($row['hash_password'], $calc)) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']); exit;
        }

        $newSalt = random_bytes(16);
        $newIter = 100000;
        $newHash = hash_pbkdf2("sha256", $new, $newSalt, $newIter, 32, true);

        $u = $conn->prepare("UPDATE users SET hash_password = ?, salt = ?, iterations = ? WHERE user_id = ?");
        $u->bind_param("ssii", $newHash, $newSalt, $newIter, $userId);
        if (!$u->execute()) { echo json_encode(['success' => false, 'message' => 'Failed to update password.']); exit; }

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
            'redirect' => '../includes/Logout.php?reason=pwchange'
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Password update error: ' . $e->getMessage()]); exit;
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
    'dob' => 'March 15, 1980',
    'address' => '70/104 Ballarat Rd, Footscray VIC 3011',
    'postal_address' => '',
    'elections' => []
];

$userQuery = "SELECT user_id, first_name, middle_name, last_name, email, iv, date_created 
              FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { die("User not found"); }
$row = $result->fetch_assoc();

$iv = $row['iv'] ?? '';
$user['first_name']  = dec_cbc($row['first_name'] ?? null, $iv);
$user['middle_name'] = dec_cbc($row['middle_name'] ?? null, $iv);
$user['last_name']   = dec_cbc($row['last_name'] ?? null, $iv);
$user['email']       = dec_cbc($row['email'] ?? null, $iv);

/* ---------- Elections ---------- */
$hasElection = tableExists($conn, 'election');
$hasVotes    = tableExists($conn, 'user_votes');

if ($hasElection) {
    $now = new DateTime();
    if ($hasVotes) {
        $sql = "
          SELECT e.poll_id, e.election_name, e.start_datetime, e.end_datetime,
                 EXISTS(SELECT 1 FROM user_votes uv WHERE uv.user_id = ? AND uv.poll_id = e.poll_id) AS has_voted
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

$lastLogin = date(
    "F j, Y, g:i a",
    strtotime($_SESSION['last_login'] ?? ($row['date_created'] ?? date('Y-m-d H:i:s')))
);

/* ---------- Avatar URL (robust) ---------- */
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
            <ion-icon class="voter-icon" name="person-circle-outline"></ion-icon>
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
            <a href="../includes/Logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
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
                    <?php if (!empty($user['postal_address'])): ?>
                    <div class="address-line">
                        <ion-icon name="mail-outline"></ion-icon>
                        <span>Postal: <?= htmlspecialchars($user['postal_address']) ?></span>
                    </div>
                    <?php endif; ?>
                    <br />
                    <button class="manage-address-btn" id="manage-address-btn">Manage Addresses</button>
                </div>
            </div>

            <div class="action-section">
                <a href="../pages/faq.php" class="action-link" id="help-info-link">
                    <ion-icon name="help-circle-outline"></ion-icon>
                    Help &amp; Info
                </a>
            </div>

            <div class="action-section">
                <a href="#" class="action-link" id="provide-feedback">
                    <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
                    Provide Feedback
                </a>
            </div>
        </div>
    </main>

    <!-- Password Modal (styled like Admin, no X) -->
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

    <!-- Edit Personal Info Modal (styled like Admin, no X) -->
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
                    <input type="text" id="modal-dob" name="dob" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
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

    <!-- Manage Address Modal (styled like Admin, no X) -->
    <div id="addressModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Manage Addresses</h3>
            </div>
            <form id="addressForm" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="modal-address">Residential Address</label>
                    <textarea id="modal-address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                    <label for="modal-postal-address">Postal Address (if different)</label>
                    <textarea id="modal-postal-address" rows="3"><?= htmlspecialchars($user['postal_address'] ?? '') ?></textarea>
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="button" class="btn btn-confirm green-btn" id="save-address">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Feedback Modal (unchanged) -->
    <div id="feedbackModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Provide Feedback</h3>
            </div>
            <form id="feedbackForm" action="../includes/submit_feedback.php" method="POST" autocomplete="off">
                <div class="form-group">
                    <label for="feedback-text">Your Feedback</label>
                    <textarea id="feedback-text" name="feedback" rows="5" placeholder="Please share your feedback..."></textarea>
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn">Submit Feedback</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal helpers
        function openModal(id){ const el=document.getElementById(id); if(el){ el.style.display='block'; el.setAttribute('aria-hidden','false'); } }
        function closeModal(id){ const el=document.getElementById(id); if(el){ el.style.display='none'; el.setAttribute('aria-hidden','true'); } }

        // Inline confirm (class-based; same as Admin)
        function inlineConfirm(modalId, message='Apply changes?'){
            return new Promise(resolve=>{
                const modal = document.getElementById(modalId);
                const content = modal?.querySelector('.modal-content');
                if (!content) { resolve(true); return; }

                const existing = content.querySelector('.inline-confirm');
                if (existing) existing.remove();
                content.style.position = 'relative';

                const overlay = document.createElement('div');
                overlay.className = 'inline-confirm';
                overlay.setAttribute('role','dialog');
                overlay.setAttribute('aria-modal','true');

                const box=document.createElement('div');
                box.className='inline-confirm-box';

                const icon=document.createElement('ion-icon');
                icon.setAttribute('name','alert-circle-outline');
                icon.className='inline-confirm-icon';

                const text=document.createElement('div');
                text.className='inline-confirm-text';
                text.textContent=message;

                const btns=document.createElement('div');
                btns.className='inline-confirm-actions';

                const noBtn=document.createElement('button'); noBtn.type='button'; noBtn.className='cancel-btn'; noBtn.textContent='Discard';
                const yesBtn=document.createElement('button'); yesBtn.type='button'; yesBtn.className='green-btn'; yesBtn.textContent='Apply';

                btns.append(noBtn, yesBtn); box.append(icon, text, btns); overlay.append(box); content.append(overlay);

                const cleanup=(v)=>{ overlay.remove(); resolve(v); };
                noBtn.addEventListener('click', ()=>cleanup(false), {once:true});
                yesBtn.addEventListener('click', ()=>cleanup(true), {once:true});
            });
        }

        function showPasswordModal(){
            const f=document.getElementById('passwordForm');
            if (f){
                f.reset();
                ['currentPassword','newPassword','verifyPassword'].forEach(id=>{
                    const el=document.getElementById(id);
                    if(el){ el.value=''; el.setAttribute('autocomplete', id==='currentPassword' ? 'current-password' : 'new-password'); }
                });
            }
            openModal('passwordModal');
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelector('.mobile-menu-toggle')?.addEventListener('click', ()=>document.querySelector('.sidebar').classList.toggle('active'));
            document.getElementById('edit-personal-info')?.addEventListener('click', ()=>openModal('personalInfoModal'));
            document.getElementById('manage-address-btn')?.addEventListener('click', ()=>openModal('addressModal'));
            document.getElementById('provide-feedback')?.addEventListener('click', (e)=>{ e.preventDefault(); openModal('feedbackModal'); });

            // Cancel buttons close the host modal
            document.querySelectorAll('.cancel-btn').forEach(btn=>{
                btn.addEventListener('click', function(){
                    const modal=this.closest('.modal');
                    if (modal) {
                        if (modal.id==='passwordModal') document.getElementById('passwordForm')?.reset();
                        closeModal(modal.id);
                    }
                });
            });

            // Close when clicking outside modal
            window.addEventListener('click', (e)=>{ if (e.target.classList.contains('modal')) closeModal(e.target.id); });

            // Password form -> confirm -> POST -> redirect
            const pwForm=document.getElementById('passwordForm');
            pwForm?.addEventListener('submit', async function(e){
                e.preventDefault();
                const ok=await inlineConfirm('passwordModal','Apply password change?');
                if(!ok) return;

                const fd=new FormData(this);
                fd.set('action','change_password');

                fetch(window.location.href, {
                    method:'POST',
                    body:fd,
                    headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
                })
                .then(async r=>{ const t=await r.text(); let d=null; try{ d=JSON.parse(t);}catch(_){} if(!r.ok) throw new Error(d?.message||t||'Request failed'); return d; })
                .then(d=>{
                    if(d.success){
                        window.location.replace(d.redirect || '../includes/Logout.php?reason=pwchange');
                    }else{
                        alert('Error: ' + (d.message || 'Update failed'));
                    }
                })
                .catch(err=>alert('An error occurred while updating your password. ' + (err?.message||'')));
            });

            // Personal info -> confirm -> POST -> update UI
            const piForm=document.getElementById('personalInfoForm');
            piForm?.addEventListener('submit', async function(e){
                e.preventDefault();
                const ok=await inlineConfirm('personalInfoModal','Apply profile changes?');
                if(!ok) return;

                const fd=new FormData(this);
                fd.set('action','update_profile');

                fetch(window.location.href, {
                    method:'POST',
                    body:fd,
                    headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
                })
                .then(async r=>{ const t=await r.text(); let d=null; try{ d=JSON.parse(t);}catch(_){} if(!r.ok) throw new Error(d?.message||t||'Request failed'); return d; })
                .then(d=>{
                    if(d.success){
                        document.getElementById('profile-name').textContent=`${d.first_name} ${d.last_name}`;
                        document.getElementById('first-name-value').textContent=d.first_name;
                        document.getElementById('last-name-value').textContent=d.last_name;
                        document.getElementById('email-value').textContent=d.email;
                        document.getElementById('dob-value').textContent=d.dob;
                        closeModal('personalInfoModal');
                    }else{
                        alert('Error: ' + (d.message || 'Update failed'));
                    }
                })
                .catch(err=>alert('An error occurred while updating your information. ' + (err?.message||'')));
            });

            // Addresses (UI only)
            document.getElementById('save-address')?.addEventListener('click', async ()=>{
                const ok=await inlineConfirm('addressModal','Apply address changes?');
                if(!ok) return;

                const address=document.getElementById('modal-address').value;
                const postal=document.getElementById('modal-postal-address').value;

                const firstLine=document.querySelector('.address-line span');
                if(firstLine) firstLine.textContent=`Residential: ${address}`;

                const section=document.querySelector('.address-section');
                let postalLine=section.querySelectorAll('.address-line')[1];
                if (postal.trim()!==''){
                    if(!postalLine){
                        const div=document.createElement('div');
                        div.className='address-line';
                        div.innerHTML=`<ion-icon name="mail-outline"></ion-icon><span>Postal: ${postal}</span>`;
                        section.insertBefore(div, document.getElementById('manage-address-btn'));
                    }else{
                        postalLine.querySelector('span').textContent=`Postal: ${postal}`;
                    }
                }else if (postalLine){ postalLine.remove(); }

                closeModal('addressModal');
            });
        });
    </script>
</body>
</html>
