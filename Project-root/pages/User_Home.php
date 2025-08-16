<?php
session_start();
require_once '../includes/security.sn.php';
checkSessionTimeout();

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}

define('ROOT_DIR', dirname(__DIR__));

/* Load .env so decrypt keys work */
(function () {
    $env = ROOT_DIR . '/.env';
    if (is_file($env) && is_readable($env)) {
        $pairs = parse_ini_file($env, false, INI_SCANNER_RAW) ?: [];
        foreach ($pairs as $k => $v) {
            if (is_string($v) && strlen($v) >= 2) {
                $q = $v[0]; $r = substr($v, -1);
                if (($q === '"' && $r === '"') || ($q === "'" && $r === "'")) $v = substr($v, 1, -1);
            }
            $_ENV[$k] = $v; putenv("$k=$v");
        }
    }
})();

require_once ROOT_DIR . '/DatabaseConnection/config.php';
require_once ROOT_DIR . '/includes/functions.sn.php';
require_once ROOT_DIR . '/includes/election_stats.php'; // getLastUserLogin()

/* Ensure we have $conn */
if (!isset($conn) || !($conn instanceof mysqli)) {
    if (isset($host, $username, $password, $database)) {
        $conn = @mysqli_connect($host, $username, $password, $database);
        if (!$conn) die("Failed to connect to Database: " . mysqli_connect_error());
    } else die("Database configuration not available.");
}
mysqli_set_charset($conn, 'utf8mb4');
date_default_timezone_set('Australia/Melbourne');

/* Helpers */
if (!function_exists('dec_cbc')) { // avoid redeclare if functions.sn.php already defines it
    function dec_cbc(?string $ct, string $iv): string {
        if ($ct === null || $ct === '' || !defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) return '';
        $p = openssl_decrypt($ct, 'aes-256-cbc', TRUE_MASTER_EMAIL_ENCRYPTION_KEY, OPENSSL_RAW_DATA, $iv);
        return $p === false ? '' : $p;
    }
}
function tableExists(mysqli $conn, string $name): bool {
    $q = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $q->bind_param("s", $name);
    $q->execute(); $q->store_result();
    $ok = $q->num_rows > 0;
    $q->free_result();
    return $ok;
}
function columnsOf(mysqli $conn, string $table): array {
    $cols = [];
    $q = $conn->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $q->bind_param("s", $table);
    if ($q->execute()) {
        $res = $q->get_result();
        while ($r = $res->fetch_assoc()) $cols[strtolower($r['COLUMN_NAME'])] = true;
    }
    return $cols;
}
function days_left_only(?string $end): ?int {
    if (!$end) return null;
    $now = new DateTime(); $endDt = new DateTime($end);
    if ($endDt <= $now) return 0;
    return (int)$now->diff($endDt)->days; // whole days
}
function dtfmt(?string $dt): string {
    if (!$dt) return '';
    try { return (new DateTime($dt))->format('M j, Y'); } catch (Throwable $e) { return $dt; }
}

/* Exact vote check against `ballot` first, fallback to `user_votes` */
function userHasVotedExact(mysqli $conn, int $userId, int $pollId): bool {
    if (tableExists($conn, 'ballot')) {
        $st = $conn->prepare("SELECT 1 FROM ballot WHERE user_id = ? AND poll_id = ? LIMIT 1");
        $st->bind_param("ii", $userId, $pollId);
        $st->execute(); $st->store_result();
        $has = $st->num_rows > 0; $st->free_result();
        return $has;
    }
    if (tableExists($conn, 'user_votes')) {
        $st = $conn->prepare("SELECT 1 FROM user_votes WHERE user_id = ? AND poll_id = ? LIMIT 1");
        $st->bind_param("ii", $userId, $pollId);
        $st->execute(); $st->store_result();
        $has = $st->num_rows > 0; $st->free_result();
        return $has;
    }
    return false;
}

/* User basics */
$userId = (int)$_SESSION["user_id"];
$lastLogin = getLastUserLogin($conn);

$first = $last = $iv = '';
$q = $conn->prepare("SELECT first_name, last_name, iv FROM users WHERE user_id = ? LIMIT 1");
$q->bind_param("i", $userId); $q->execute(); $res = $q->get_result();
if ($row = $res->fetch_assoc()) {
    $iv = $row['iv'] ?? '';
    $first = dec_cbc($row['first_name'] ?? null, $iv);
    $last  = dec_cbc($row['last_name']  ?? null, $iv);
}
$displayName = trim($first !== '' ? $first : 'User');

/* --- Elections (dynamic + real-time vote status) --- */
$elections = [];
$hasElection  = tableExists($conn, 'election');

$eCols = $hasElection ? columnsOf($conn, 'election') : [];
$colHas = fn($n) => isset($eCols[strtolower($n)]);

if ($hasElection) {
    // Base election fields
    $sel = [
        "e.poll_id",
        "e.election_name",
        "e.start_datetime",
        "e.end_datetime"
    ];
    $sel[] = $colHas('election_type') ? "e.election_type" : "NULL AS election_type";
    if ($colHas('is_paused'))      $sel[] = "e.is_paused AS paused_flag";
    elseif ($colHas('paused'))     $sel[] = "e.paused AS paused_flag";
    elseif ($colHas('status'))     $sel[] = "(CASE WHEN LOWER(e.status)='paused' THEN 1 ELSE 0 END) AS paused_flag";
    else                           $sel[] = "0 AS paused_flag";

    $sql = "SELECT " . implode(',', $sel) . "
            FROM election e
            WHERE (e.start_datetime IS NOT NULL OR e.end_datetime IS NOT NULL)
            ORDER BY e.start_datetime ASC";

    $st = $conn->prepare($sql);
    $st->execute(); $rs = $st->get_result();

    $now = new DateTime();
    while ($e = $rs->fetch_assoc()) {
        $start = $e['start_datetime'] ? new DateTime($e['start_datetime']) : null;
        $end   = $e['end_datetime']   ? new DateTime($e['end_datetime'])   : null;

        $status = 'Schedule TBA'; $state = 'unknown';
        if ($start && $now < $start) { $status = 'Starts in ' . $now->diff($start)->format('%a days %h hours'); $state='starts_soon'; }
        elseif ($end && $now > $end) { $status = 'Election Ended'; $state='ended'; }
        elseif ($start && $end && $now >= $start && $now <= $end) { $status = 'Ends in ' . $now->diff($end)->format('%a days %h hours'); $state='ongoing'; }

        $pollId = (int)$e['poll_id'];
        $voted  = userHasVotedExact($conn, $userId, $pollId); // LIVE from `ballot`

        $elections[] = [
            'poll_id'    => $pollId,
            'name'       => $e['election_name'],
            'type'       => $e['election_type'] ?? null,
            'paused'     => (int)($e['paused_flag'] ?? 0) === 1,
            'voted'      => $voted,
            'status'     => $status,
            'state'      => $state,
            'start_time' => $e['start_datetime'],
            'end_time'   => $e['end_datetime']
        ];
    }
}

/* ===== Notifications (session-backed) =====
   Keep only:
   - 🚨 start notice when ongoing (unvoted)
   - daily countdown (days only) while ongoing (unvoted)
   - ended notice when an election disappears or ends
*/
$_SESSION['notif_unread']        = $_SESSION['notif_unread']        ?? [];
$_SESSION['notif_read']          = $_SESSION['notif_read']          ?? [];
$_SESSION['elections_snapshot']  = $_SESSION['elections_snapshot']  ?? []; // prev state

function notif_add_session(string $idKey, string $title, string $htmlMsg, bool $unread = true) {
    if (!isset($_SESSION['notif_unread'][$idKey]) && !isset($_SESSION['notif_read'][$idKey])) {
        $arr = ['id'=>$idKey, 'title'=>$title, 'message'=>$htmlMsg, 'is_read'=>$unread?0:1, 'created'=>date('Y-m-d H:i:s')];
        if ($unread) $_SESSION['notif_unread'][$idKey] = $arr; else $_SESSION['notif_read'][$idKey] = $arr;
    }
}
function notif_fetch_session(): array {
    $unread = array_values($_SESSION['notif_unread']);
    $read   = array_values($_SESSION['notif_read']);
    usort($unread, fn($a,$b)=>strcmp($b['created'],$a['created']));
    usort($read,   fn($a,$b)=>strcmp($b['created'],$a['created']));
    return [$unread,$read];
}
function notif_mark_session(array $ids, bool $toRead) {
    foreach ($ids as $id) {
        if ($toRead) {
            if (isset($_SESSION['notif_unread'][$id])) {
                $_SESSION['notif_unread'][$id]['is_read']=1;
                $_SESSION['notif_read'][$id] = $_SESSION['notif_unread'][$id];
                unset($_SESSION['notif_unread'][$id]);
            }
        } else {
            if (isset($_SESSION['notif_read'][$id])) {
                $_SESSION['notif_read'][$id]['is_read']=0;
                $_SESSION['notif_unread'][$id] = $_SESSION['notif_read'][$id];
                unset($_SESSION['notif_read'][$id]);
            }
        }
    }
}

/* ---- Seed "start" & "daily countdown" notices ---- */
$now = new DateTime();
foreach ($elections as $e) {
    if ($e['state'] === 'ongoing' && !$e['voted']) {
        $startKey = "start_{$e['poll_id']}";
        notif_add_session($startKey, 'Election started', "🚨 <strong>{$e['name']}</strong> is now open.");

        $days = days_left_only($e['end_time']);
        if ($days !== null) {
            $days = max(0, (int)$days);
            $remKey = "rem_{$e['poll_id']}_d{$days}";
            $msg = "Hey {$displayName}, just a reminder you have <strong>{$days} days</strong> to vote in <strong>{$e['name']}</strong>.";
            notif_add_session($remKey, 'Voting reminder', $msg);
        }
    }
}

/* ---- Ended notice: detect elections that vanished since last view ---- */
$prev = $_SESSION['elections_snapshot']; // previous snapshot
$curr = []; // build new snapshot
foreach ($elections as $e) {
    $curr[$e['poll_id']] = [
        'name'  => (string)$e['name'],
        'state' => (string)$e['state']
    ];
}
// disappeared elections
foreach ($prev as $pid => $snap) {
    if (!isset($curr[$pid])) {
        $nk = "end_{$pid}";
        $ename = $snap['name'] ?? "Election #{$pid}";
        notif_add_session($nk, 'Election ended', "<strong>{$ename}</strong> has ended.");
    }
}
// state became ended
foreach ($curr as $pid => $snap) {
    if (($prev[$pid]['state'] ?? '') !== 'ended' && $snap['state'] === 'ended') {
        $nk = "ended_{$pid}";
        $ename = $snap['name'] ?? "Election #{$pid}";
        notif_add_session($nk, 'Election ended', "<strong>{$ename}</strong> has ended.");
    }
}
// Save snapshot for next diff
$_SESSION['elections_snapshot'] = $curr;

/* ---- AJAX for the modal ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notif_action'])) {
    header('Content-Type: application/json');
    $act = $_POST['notif_action'];
    if ($act === 'fetch') {
        [$u,$r] = notif_fetch_session();
        echo json_encode(['success'=>true,'unread'=>$u,'read'=>$r]); exit;
    }
    if ($act === 'mark_read') {
        $ids = array_filter((array)($_POST['ids'] ?? []), fn($x)=>is_string($x) && $x!=='');
        notif_mark_session($ids, true);
        [$u,$r] = notif_fetch_session();
        echo json_encode(['success'=>true,'unread'=>$u,'read'=>$r]); exit;
    }
    echo json_encode(['success'=>false,'message'=>'bad action']); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Votify - Home</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/User_Home.css">
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-top-bar"><h3>Votify</h3></div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="User_Home.php" class="active"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
                <li><a href="User_Profile.php"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
                <li><a href="User_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
                <li><a href="User_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
                <li><a href="User_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <!-- Fixed logout path to controllers (matches other pages) -->
            <a href="../controllers/Logout.php" class="footer-link signout-link">
                <span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
                <span class="text">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <!-- welcome -->
        <section class="welcome-header card">
            <div class="title-row">
                <ion-icon class="title-icon" name="person-circle-outline" aria-hidden="true"></ion-icon>
                <h1>Welcome back, <span class="name-accent"><?= htmlspecialchars($displayName) ?></span></h1>
            </div>
            <div class="subtext">Last login: <?= htmlspecialchars($lastLogin) ?></div>
        </section>

        <!-- elections -->
        <section class="card">
            <div class="title-row">
                <!-- ballot-like paper icon -->
                <ion-icon class="title-icon" name="document-text-outline" aria-hidden="true"></ion-icon>
                <h2 class="card-title">Elections Overview</h2>
            </div>

            <?php if (empty($elections)): ?>
                <p class="muted">There are no current elections available.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table elections-table">
                        <thead>
                            <tr>
                                <th>Election</th>
                                <th>Enrolment status</th>
                                <th>Vote status</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($elections as $e): ?>
                            <tr>
                                <td><?= htmlspecialchars($e['name']) ?></td>
                                <td><span class="badge badge-enrolled">Enrolled</span></td>
                                <td>
                                    <span class="badge <?= $e['voted'] ? 'badge-voted' : 'badge-notvoted' ?>">
                                        <?= $e['voted'] ? 'Voted' : 'Not voted' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($e['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- notifications + help -->
        <section class="grid-2">
            <div class="card">
                <div class="title-row">
                    <ion-icon class="title-icon" name="information-circle-outline" aria-hidden="true"></ion-icon>
                    <h2 class="card-title">Notifications</h2>
                </div>

                <?php [$unread,$read] = notif_fetch_session(); $preview = array_slice(array_merge($unread,$read), 0, 3); ?>
                <?php if (empty($preview)): ?>
                    <p class="muted">No new notifications.</p>
                <?php else: ?>
                    <ul class="notif-list">
                        <?php foreach ($preview as $n): ?>
                            <li><span class="notif-text"><?= $n['message'] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="notif-actions">
                    <button id="viewAllBtn" class="btn view-all">View All</button>
                </div>
            </div>

            <div class="card">
                <div class="title-row">
                    <ion-icon class="title-icon" name="hand-left-outline" aria-hidden="true"></ion-icon>
                    <h2 class="card-title">Help &amp; Support</h2>
                </div>
                <!-- stacked buttons -->
                <div class="help-stack">
                    <a class="help-card" href="../pages/contact.html">
                        <span>Help</span>
                        <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
                    </a>
                    <a class="help-card" href="../pages/FAQs.php">
                        <span>FAQs</span>
                        <ion-icon name="arrow-forward-outline" aria-hidden="true"></ion-icon>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- notifications modal -->
    <div id="notifModal" class="modal" aria-hidden="true">
        <div class="modal-content large fancy">
            <div class="modal-header">
                <h3 class="modal-title">All Notifications</h3>
            </div>
            <div class="modal-body">
                <div class="tabs">
                    <button class="tab-btn active" data-tab="unread">Unread</button>
                    <button class="tab-btn" data-tab="read">Read</button>
                </div>
                <div class="tab-panels">
                    <div id="tab-unread" class="tab-panel active">
                        <ul id="list-unread" class="modal-list"></ul>
                    </div>
                    <div id="tab-read" class="tab-panel">
                        <ul id="list-read" class="modal-list"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-actions center">
                <button id="notifBack" class="btn btn-light">Back</button>
                <button id="markRead" class="btn btn-primary">Mark as Read</button>
            </div>
        </div>
    </div>

<script>
(() => {
    const $ = s => document.querySelector(s);
    const $$ = s => Array.from(document.querySelectorAll(s));

    function openModal(id){ const el=document.getElementById(id); if(!el) return; el.style.display='block'; el.setAttribute('aria-hidden','false'); }
    function closeModal(id){ const el=document.getElementById(id); if(!el) return; el.style.display='none'; el.setAttribute('aria-hidden','true'); }

    function activateTab(name){
        $$('.tab-btn').forEach(b=>b.classList.toggle('active', b.dataset.tab===name));
        $$('.tab-panel').forEach(p=>p.classList.toggle('active', p.id==='tab-'+name));
    }
    document.addEventListener('click', e=>{
        if (e.target.classList.contains('tab-btn')) activateTab(e.target.dataset.tab);
    });

    async function fetchNotifs(){
        const fd=new FormData(); fd.append('notif_action','fetch');
        const r=await fetch(location.href,{method:'POST',body:fd});
        const j=await r.json();
        if(!j.success) throw new Error('load fail');

        const render = (arr, ulId) => {
            const ul = document.getElementById(ulId);
            ul.innerHTML='';
            if(!arr.length){
                const li=document.createElement('li');
                li.className='empty';
                li.textContent='No notifications';
                ul.appendChild(li);
                return;
            }
            arr.forEach(n=>{
                const li=document.createElement('li');
                li.innerHTML = `
                    <label class="chkrow">
                        <input type="checkbox" value="${n.id}">
                        <div class="msg">
                            <div class="title">${n.title || ''}</div>
                            <div class="text">${n.message}</div>
                            <div class="meta">${n.created || ''}</div>
                        </div>
                    </label>`;
                ul.appendChild(li);
            });
        };

        render(j.unread, 'list-unread');
        render(j.read,   'list-read');
    }

    $('#viewAllBtn')?.addEventListener('click', async () => {
        await fetchNotifs();
        openModal('notifModal');
    });

    $('#notifBack')?.addEventListener('click', () => closeModal('notifModal'));

    async function mark(action, fromListSel){
        const ids=$$(fromListSel + ' input[type="checkbox"]:checked').map(i=>i.value);
        if(!ids.length) return;
        const fd=new FormData(); fd.append('notif_action',action);
        ids.forEach(id=>fd.append('ids[]', id));
        const r=await fetch(location.href,{method:'POST',body:fd});
        const j=await r.json();
        if(j.success){ await fetchNotifs(); } else alert('Update failed');
    }

    $('#markRead')?.addEventListener('click', ()=> mark('mark_read', '#list-unread'));

    window.addEventListener('click', e=>{ if(e.target.classList.contains('modal')) closeModal(e.target.id); });
})();
</script>
</body>
</html>
