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
if (!$est) {
    // Handle error if prepare fails, e.g., log it or set an empty array.
    // For now, we'll continue with an empty array if statement preparation fails.
} else {
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
                updateAdminProfile(
                    $conn,
                    $adminId,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['email']),
                    trim($_POST['dob'])
                );
                echo json_encode(['success' => true]);
                exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>