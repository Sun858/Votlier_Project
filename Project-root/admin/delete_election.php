<?php
session_start();

// Only allow admin users
if (!isset($_SESSION["admin_id"])) {
    die("Access denied.");
}

// Validate election ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['message'] = "Invalid election ID.";
    header("Location: dashboard.php");
    exit();
}

$election_id = $_GET['id'];

// Connect to the database
$conn = new mysqli("localhost", "root", "", "voting_system");
if ($conn->connect_error) {
    $_SESSION['message'] = "Database connection failed.";
    header("Location: dashboard.php");
    exit();
}

// Delete candidates first
$stmt = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
$stmt->bind_param("i", $election_id);
if (!$stmt->execute()) {
    $_SESSION['message'] = "Failed to delete candidates.";
    $stmt->close();
    $conn->close();
    header("Location: dashboard.php");
    exit();
}
$stmt->close();

// Delete election
$stmt = $conn->prepare("DELETE FROM elections WHERE poll_id = ?");
$stmt->bind_param("i", $election_id);
if ($stmt->execute()) {
    $_SESSION['message'] = "Election and related candidates deleted successfully.";
} else {
    $_SESSION['message'] = "Failed to delete election.";
}
$stmt->close();
$conn->close();

header("Location: dashboard.php");
exit();
?>
