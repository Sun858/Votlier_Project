<?php
session_start();
define('ROOT_DIR', dirname(__DIR__));

/* Load .env (no changes to your .env contents) */
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
require_once ROOT_DIR . '/DatabaseConnection/config.php';
require_once ROOT_DIR . '/includes/crypto_helpers.php';

checkSessionTimeout();
if (!isset($_SESSION["user_id"])) { header("location: ../pages/login.php"); exit(); }

// Fallback connect if config.php didn't create $conn
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

// Helper: table existence
function tableExists(mysqli $conn, string $name): bool {
    $q = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $q->bind_param("s", $name);
    $q->execute();
    $q->store_result();
    $ok = $q->num_rows > 0;
    $q->free_result();
    return $ok;
}

$userId = (int)$_SESSION["user_id"];

/* User record */
$userQuery = "SELECT user_id, first_name, middle_name, last_name, email, date_created FROM users WHERE user_id = ?";
$stmt = $conn->prepare($userQuery);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) { die("User not found"); }
$row = $result->fetch_assoc();

/* Decrypt */
$user = [
    'first_name'     => decryptField($row['first_name']) ?? '',
    'middle_name'    => decryptField($row['middle_name'] ?? null) ?? '',
    'last_name'      => decryptField($row['last_name']) ?? '',
    'email'          => decryptField($row['email']) ?? '',
    'voter_id'       => $row['user_id'],
    'dob'            => 'March 15, 1980',
    'address'        => '70/104 Ballarat Rd, Footscray VIC 3011',
    'postal_address' => '',
    'elections'      => []
];

/* Elections */
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
        $st = $conn->prepare($sql);
        $st->bind_param("i", $userId);
    } else {
        $sql = "
          SELECT e.poll_id, e.election_name, e.start_datetime, e.end_datetime,
                 0 AS has_voted
          FROM election e
          WHERE (e.start_datetime IS NOT NULL OR e.end_datetime IS NOT NULL)
          ORDER BY e.start_datetime ASC
        ";
        $st = $conn->prepare($sql);
    }

    $st->execute();
    $rs = $st->get_result();

    while ($e = $rs->fetch_assoc()) {
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

/* Render */
include 'User_Profile.php';
