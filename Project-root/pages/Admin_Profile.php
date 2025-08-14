<?php
session_start();

define('ROOT_DIR', dirname(__DIR__));

// Load .env
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

// Require admin session
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/stafflogin.php");
    exit();
}

// DB connect
require_once ROOT_DIR . '/DatabaseConnection/config.php';

// Fallback if needed
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

// Crypto
require_once ROOT_DIR . '/includes/functions.sn.php';

/* ---------- Helpers ---------- */
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

/* ---------- AJAX: Update admin profile (DOB = UI-only) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_admin_profile') {
    header('Content-Type: application/json');
    try {
        $adminId = (int)$_SESSION['admin_id'];

        $rs = $conn->prepare("SELECT iv FROM administration WHERE admin_id = ? LIMIT 1");
        $rs->bind_param("i", $adminId);
        $rs->execute();
        $ivRes = $rs->get_result();
        if ($ivRes->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Admin not found']); exit; }
        $iv = ($ivRes->fetch_assoc())['iv'];

        $first_name = trim((string)($_POST['first_name'] ?? ''));
        $last_name  = trim((string)($_POST['last_name'] ?? ''));
        $email      = trim((string)($_POST['email'] ?? ''));
        $dob        = trim((string)($_POST['dob'] ?? ''));

        if ($first_name === '' || $last_name === '' || $email === '') {
            echo json_encode(['success'=>false,'message'=>'First name, Last name and Email are required.']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success'=>false,'message'=>'Invalid email address.']); exit;
        }

        $encFirst = enc_cbc($first_name, $iv);
        $encLast  = enc_cbc($last_name,  $iv);
        $encEmail = enc_cbc($email,      $iv);
        $emailBlindIndex = hash_hmac('sha256', $email, TRUE_BLIND_INDEX_SECRET_KEY, true);

        $upd = $conn->prepare("UPDATE administration SET first_name = ?, last_name = ?, email = ?, email_blind_index = ? WHERE admin_id = ?");
        $upd->bind_param("ssssi", $encFirst, $encLast, $encEmail, $emailBlindIndex, $adminId);
        if (!$upd->execute()) { echo json_encode(['success'=>false,'message'=>'Database update failed.']); exit; }

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
        echo json_encode(['success'=>false,'message'=>'Update error: '.$e->getMessage()]); exit;
    }
}

/* ---------- AJAX: Change admin password (logout -> stafflogin.php) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_admin_password') {
    header('Content-Type: application/json');
    try {
        $adminId = (int)$_SESSION['admin_id'];
        $current = (string)($_POST['currentPassword'] ?? '');
        $new     = (string)($_POST['newPassword'] ?? '');
        $verify  = (string)($_POST['verifyPassword'] ?? '');

        if ($current === '' || $new === '' || $verify === '') {
            echo json_encode(['success'=>false,'message'=>'All password fields are required.']); exit;
        }
        if ($new !== $verify) {
            echo json_encode(['success'=>false,'message'=>'New passwords do not match.']); exit;
        }
        if (strlen($new) < 8) {
            echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters.']); exit;
        }

        $q = $conn->prepare("SELECT hash_password, salt, iterations FROM administration WHERE admin_id = ? LIMIT 1");
        $q->bind_param("i", $adminId);
        $q->execute();
        $res = $q->get_result();
        if ($res->num_rows === 0) { echo json_encode(['success'=>false,'message'=>'Admin not found.']); exit; }
        $row = $res->fetch_assoc();

        $calc = hash_pbkdf2("sha256", $current, $row['salt'], (int)$row['iterations'], 32, true);
        if (!hash_equals($row['hash_password'], $calc)) {
            echo json_encode(['success'=>false,'message'=>'Current password is incorrect.']); exit;
        }

        $newSalt = random_bytes(16);
        $newIter = 100000;
        $newHash = hash_pbkdf2("sha256", $new, $newSalt, $newIter, 32, true);

        $u = $conn->prepare("UPDATE administration SET hash_password = ?, salt = ?, iterations = ? WHERE admin_id = ?");
        $u->bind_param("ssii", $newHash, $newSalt, $newIter, $adminId);
        if (!$u->execute()) { echo json_encode(['success'=>false,'message'=>'Failed to update password.']); exit; }

        // Robust logout
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
            'redirect' => '../pages/stafflogin.php'
        ]);
        exit;

    } catch (Throwable $e) {
        echo json_encode(['success'=>false,'message'=>'Password update error: '.$e->getMessage()]); exit;
    }
}

/* ---------- Build $admin for the view ---------- */
$adminId = (int)$_SESSION["admin_id"];
$admin = [
    'first_name' => '',
    'last_name'  => '',
    'email'      => '',
    'admin_id'   => $adminId,
    'dob'        => '15 March 1990'
];

$q = $conn->prepare("SELECT admin_id, first_name, last_name, email, iv, date_created FROM administration WHERE admin_id = ? LIMIT 1");
$q->bind_param("i", $adminId);
$q->execute();
$r = $q->get_result();
if ($r->num_rows === 0) { die("Admin not found"); }
$row = $r->fetch_assoc();

$iv = $row['iv'] ?? '';
$admin['first_name'] = dec_cbc($row['first_name'] ?? null, $iv);
$admin['last_name']  = dec_cbc($row['last_name']  ?? null, $iv);
$admin['email']      = dec_cbc($row['email']      ?? null, $iv);

$displayName = trim($admin['first_name'].' '.$admin['last_name']);
$displayId   = str_pad((string)$adminId, 6, '0', STR_PAD_LEFT);

// Elections list
$elections = [];
$est = $conn->prepare("SELECT poll_id, election_name, start_datetime, end_datetime FROM election ORDER BY start_datetime ASC");
$est->execute();
$eRes = $est->get_result();
while ($erow = $eRes->fetch_assoc()) { $elections[] = $erow; }

$lastLogin = date("F j, Y, g:i a", strtotime($_SESSION['last_login'] ?? ($row['date_created'] ?? date('Y-m-d H:i:s'))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Votify - Admin Profile</title>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<link rel="stylesheet" href="../Assets/css/Admin_Profile.css">
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
            <li><a href="Admin_Home.php"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
            <li><a href="Admin_Profile.php" class="active"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
            <li><a href="Admin_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
            <li><a href="Admin_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
            <li><a href="Admin_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <a href="../includes/Logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
    </div>
</aside>

<main class="main-content">
    <section class="profile-card" aria-label="Admin Profile Information">
        <div class="profile-avatar" role="img" aria-label="Profile Avatar"></div>
        <div class="profile-info">
            <h1 id="admin-display-name"><?= htmlspecialchars($displayName) ?></h1>
            <div class="role">Election Coordinator</div>
            <div class="admin-id">Admin ID: <span id="admin-display-id"><?= htmlspecialchars($displayId) ?></span></div>
            <div class="admin-email" id="admin-display-email"><?= htmlspecialchars($admin['email']) ?></div>
            <div class="last-login">Last login: <?= htmlspecialchars($lastLogin) ?></div>
            <div class="action-buttons">
                <button class="red-btn" id="open-password-modal">Change Password</button>
            </div>
        </div>
    </section>

    <section class="employee-info" aria-label="Employee Information">
        <div class="section-title">
            <ion-icon name="person-outline"></ion-icon>
            <span>Employee Information</span>
            <button class="edit-btn employee-edit" id="edit-employee-info" title="Edit employee info">
                <ion-icon name="create-outline"></ion-icon>
            </button>
        </div>

        <table class="info-table">
            <tr>
                <td class="info-label">Admin ID</td>
                <td id="emp-admin-id"><?= htmlspecialchars($displayId) ?></td>
            </tr>
            <tr>
                <td class="info-label">First Name</td>
                <td id="emp-first-name"><?= htmlspecialchars($admin['first_name']) ?></td>
            </tr>
            <tr>
                <td class="info-label">Last Name</td>
                <td id="emp-last-name"><?= htmlspecialchars($admin['last_name']) ?></td>
            </tr>
            <tr>
                <td class="info-label">D.O.B</td>
                <td id="emp-dob"><?= htmlspecialchars($admin['dob']) ?></td>
            </tr>
            <tr>
                <td class="info-label">Work Email</td>
                <td id="emp-email"><?= htmlspecialchars($admin['email']) ?></td>
            </tr>
            <tr>
                <td class="info-label">Team</td>
                <td>Administration - Election Coordinator</td>
            </tr>
            <tr>
                <td class="info-label">Supervisor</td>
                <td>Jane Citizen</td>
            </tr>
            <tr>
                <td class="info-label">Supervisor Contact</td>
                <td>Jane.Citizen@live.com.au</td>
            </tr>
        </table>
    </section>

    <section class="election-overview" aria-label="Election Overview">
        <div class="section-title">
            <ion-icon name="document-text-outline"></ion-icon>
            <span>Election Overview</span>
        </div>

        <?php if (count($elections) > 0): ?>
            <ul class="election-list">
                <li><strong>Total Elections:</strong> <?= count($elections) ?></li>
                <?php
                $now = new DateTime();
                foreach ($elections as $election):
                    $start = $election['start_datetime'] ? new DateTime($election['start_datetime']) : null;
                    $timeUntilStart = 'Schedule TBA';
                    if ($start) {
                        $interval = $now->diff($start);
                        $timeUntilStart = ($start > $now) ? $interval->format('%a days, %h hours') : 'Already started';
                    }
                ?>
                    <li>
                        <strong><?= htmlspecialchars($election['election_name']) ?></strong>
                        <span class="start-info">Starts in: <?= htmlspecialchars($timeUntilStart) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="election-actions">
                <div class="action-buttons">
                    <button class="green-btn" onclick="window.location.href='../admin/admin_create_election.php'">Create New Election</button>
                    <button class="green-btn" onclick="window.location.href='../admin/dashboard.php'">Current Elections</button>
                </div>
            </div>
        <?php else: ?>
            <p>No elections found.</p>
            <div class="action-buttons">
                <button class="green-btn" onclick="window.location.href='../admin/admin_create_election.php'">Create New Election</button>
                <button class="green-btn" onclick="window.location.href='../admin/dashboard.php'">Current Elections</button>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Change Password Modal -->
<div id="passwordModal" class="modal" aria-hidden="true">
  <div class="modal-content fancy">
    <div class="modal-header">
        <h3 class="modal-title">Change Password</h3>
        <!-- no X button -->
    </div>
    <form id="passwordForm" method="POST" autocomplete="off">
      <input type="hidden" name="action" value="change_admin_password" />
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
      <div class="modal-buttons">
        <button type="button" class="cancel-btn btn-cancel">Cancel</button>
        <button type="submit" class="green-btn btn-confirm">Update Password</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Employee Info Modal -->
<div id="employeeModal" class="modal" aria-hidden="true">
  <div class="modal-content fancy">
    <div class="modal-header">
        <h3 class="modal-title">Edit Employee Information</h3>
        <!-- no X button -->
    </div>
    <form id="employeeForm" method="POST" autocomplete="off">
      <input type="hidden" name="action" value="update_admin_profile" />

      <!-- NEW: read-only Admin ID (greyed out by CSS) -->
      <div class="form-group">
        <label for="modal-admin-id">Admin ID</label>
        <input type="text" id="modal-admin-id" value="<?= htmlspecialchars($displayId) ?>" readonly>
      </div>

      <div class="form-group">
        <label for="modal-first-name">First Name</label>
        <input type="text" id="modal-first-name" name="first_name" value="<?= htmlspecialchars($admin['first_name']) ?>">
      </div>
      <div class="form-group">
        <label for="modal-last-name">Last Name</label>
        <input type="text" id="modal-last-name" name="last_name" value="<?= htmlspecialchars($admin['last_name']) ?>">
      </div>
      <div class="form-group">
        <label for="modal-dob">D.O.B</label>
        <input type="text" id="modal-dob" name="dob" value="<?= htmlspecialchars($admin['dob']) ?>">
      </div>
      <div class="form-group">
        <label for="modal-email">Work Email</label>
        <input type="email" id="modal-email" name="email" value="<?= htmlspecialchars($admin['email']) ?>">
      </div>
      <div class="modal-buttons">
        <button type="button" class="cancel-btn btn-cancel">Cancel</button>
        <button type="submit" class="green-btn btn-confirm" id="save-employee">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
    // Mobile menu
    document.querySelector('.mobile-menu-toggle')?.addEventListener('click', ()=>document.querySelector('.sidebar').classList.toggle('active'));

    // Modal helpers
    function openModal(id){ const el=document.getElementById(id); if(el){ el.style.display='block'; el.setAttribute('aria-hidden','false'); } }
    function closeModal(id){ const el=document.getElementById(id); if(el){ el.style.display='none'; el.setAttribute('aria-hidden','true'); } }

    // Inline confirm (same visual as user profile)
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

    // Open modals
    document.getElementById('open-password-modal')?.addEventListener('click', ()=>{
        const f=document.getElementById('passwordForm'); if (f) f.reset();
        openModal('passwordModal');
    });
    document.getElementById('edit-employee-info')?.addEventListener('click', ()=>{
        // refresh fields from table
        document.getElementById('modal-first-name').value = document.getElementById('emp-first-name').textContent.trim();
        document.getElementById('modal-last-name').value  = document.getElementById('emp-last-name').textContent.trim();
        document.getElementById('modal-email').value      = document.getElementById('emp-email').textContent.trim();
        document.getElementById('modal-dob').value        = document.getElementById('emp-dob').textContent.trim();
        openModal('employeeModal');
    });

    // Cancel buttons close modal
    document.querySelectorAll('.btn-cancel').forEach(btn=>{
        btn.addEventListener('click', function(){
            const modal=this.closest('.modal'); if (modal) closeModal(modal.id);
        });
    });
    // Click outside closes modal
    window.addEventListener('click', (e)=>{ if (e.target.classList.contains('modal')) closeModal(e.target.id); });

    // Password form ajax + confirm + redirect
    document.getElementById('passwordForm')?.addEventListener('submit', async function(e){
        e.preventDefault();
        const ok = await inlineConfirm('passwordModal','Apply password change?');
        if(!ok) return;

        const fd=new FormData(this);
        fd.set('action','change_admin_password');

        fetch(window.location.href, {
            method:'POST',
            body:fd,
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        })
        .then(async r=>{ const t=await r.text(); let d=null; try{ d=JSON.parse(t);}catch(_){} if(!r.ok) throw new Error(d?.message||t||'Request failed'); return d; })
        .then(d=>{
            if(d.success){
                window.location.replace(d.redirect || '../pages/stafflogin.php');
            }else{
                alert('Error: ' + (d.message || 'Update failed'));
            }
        })
        .catch(err=>alert('An error occurred while updating password. ' + (err?.message||'')));
    });

    // Employee form ajax + confirm
    document.getElementById('employeeForm')?.addEventListener('submit', async function(e){
        e.preventDefault();
        const ok = await inlineConfirm('employeeModal','Apply profile changes?');
        if(!ok) return;

        const fd=new FormData(this);
        fd.set('action','update_admin_profile');

        fetch(window.location.href, {
            method:'POST',
            body:fd,
            headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}
        })
        .then(async r=>{ const t=await r.text(); let d=null; try{ d=JSON.parse(t);}catch(_){} if(!r.ok) throw new Error(d?.message||t||'Request failed'); return d; })
        .then(d=>{
            if(d.success){
                // Update top card + table
                document.getElementById('admin-display-name').textContent = `${d.first_name} ${d.last_name}`;
                document.getElementById('admin-display-email').textContent = d.email;

                document.getElementById('emp-first-name').textContent = d.first_name;
                document.getElementById('emp-last-name').textContent  = d.last_name;
                document.getElementById('emp-email').textContent      = d.email;
                document.getElementById('emp-dob').textContent        = d.dob;

                closeModal('employeeModal');
            }else{
                alert('Error: ' + (d.message || 'Update failed'));
            }
        })
        .catch(err=>alert('An error occurred while updating profile. ' + (err?.message||'')));
    });
</script>

</body>
</html>
