<?php
session_start();
require_once __DIR__ . '/../includes/security.sn.php';
require_once __DIR__ . '/../DataBaseConnection/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify admin session
    if (!isset($_SESSION['admin_id'])) {
        $_SESSION['password_error'] = "Session expired. Please login again.";
        header("Location: ../pages/Admin_Profile.php");
        exit();
    }

    $adminId = $_SESSION['admin_id'];
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $verifyPassword = $_POST['verifyPassword'] ?? '';

    // Validate inputs
    if (empty($currentPassword) || empty($newPassword) || empty($verifyPassword)) {
        $_SESSION['password_error'] = "All password fields are required";
        header("Location: ../pages/Admin_Profile.php");
        exit();
    }

    if ($newPassword !== $verifyPassword) {
        $_SESSION['password_error'] = "New passwords do not match";
        header("Location: ../pages/Admin_Profile.php");
        exit();
    }

    try {
        // 1. Get current password data
        $stmt = $conn->prepare("SELECT hash_password FROM administration WHERE admin_id = ?");
        if (!$stmt) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $stmt->bind_param("i", $adminId);
        if (!$stmt->execute()) {
            throw new Exception("Query failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $_SESSION['password_error'] = "Administrator account not found";
            header("Location: ../pages/Admin_Profile.php");
            exit();
        }

        $admin = $result->fetch_assoc();
        
        // 2. Verify current password using password_verify()
        if (!password_verify($currentPassword, $admin['hash_password'])) {
            $_SESSION['password_error'] = "Current password is incorrect";
            header("Location: ../pages/Admin_Profile.php");
            exit();
        }

        // 3. Hash new password
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // 4. Update password in database
        $updateStmt = $conn->prepare("UPDATE administration SET hash_password = ? WHERE admin_id = ?");
        
        if (!$updateStmt) {
            throw new Exception("Update error: " . $conn->error);
        }
        
        $updateStmt->bind_param("si", $newHash, $adminId);
        
        if ($updateStmt->execute()) {
            $_SESSION['password_success'] = "Password updated successfully!";
        } else {
            throw new Exception("Update failed: " . $updateStmt->error);
        }

    } catch (Exception $e) {
        $_SESSION['password_error'] = "System error occurred. Please try again.";
        error_log("Password Change Error: " . $e->getMessage());
    }

    header("Location: ../pages/Admin_Profile.php");
    exit();
}

// Redirect if accessed directly
header("Location: ../pages/Admin_Profile.php");
exit();
?>