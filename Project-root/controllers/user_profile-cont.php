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
$user = getUserProfile($conn, $userId);
if (!$user) die("User not found");
$user['elections'] = getUserElectionsOverview($conn, $userId);
$lastLogin = getLastUserLogin($conn);

// Handle AJAX POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    header('Content-Type: application/json');
    try {
        switch ($_POST['action']) {
            case 'update_profile':
                updateUserProfile(
                    $conn,
                    $userId,
                    trim($_POST['first_name']),
                    trim($_POST['last_name']),
                    trim($_POST['email']),
                    trim($_POST['date_of_birth'])
                );
                echo json_encode(['success' => true]);
                exit;
            case 'update_address':
                updateUserAddress($conn, $userId, trim($_POST['address']));
                echo json_encode(['success' => true]);
                exit;
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>