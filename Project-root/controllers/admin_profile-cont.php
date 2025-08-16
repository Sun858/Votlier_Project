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
$admin = getProfile($conn, $adminId);
if (!$admin) die("Admin not found");
$lastLogin = getLastAdminLogin($conn);

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