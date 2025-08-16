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
                break; // <-- Optional, since you exit, but keeps it tidy
            // (other cases here)
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}
?>