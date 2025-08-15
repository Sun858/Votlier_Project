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

// Connect to the Docker MySQL service
$conn = new mysqli("db", "admin", "adminpassword", "voting_system");
if ($conn->connect_error) {
    $_SESSION['message'] = "Database connection failed.";
    header("Location: dashboard.php");
    exit();
}

// Step 1: Delete all tally entries for candidates in this election
$tallyDelete = $conn->prepare("
    DELETE t
    FROM tally t
    INNER JOIN candidates c ON t.candidate_id = c.candidate_id
    WHERE c.poll_id = ?
");
$tallyDelete->bind_param("i", $election_id);
if (!$tallyDelete->execute()) {
    $_SESSION['message'] = "Failed to delete related vote tallies.";
    $tallyDelete->close();
    $conn->close();
    header("Location: dashboard.php");
    exit();
}
$tallyDelete->close();

// Step 2: Delete candidates
$deleteCandidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
$deleteCandidates->bind_param("i", $election_id);
if (!$deleteCandidates->execute()) {
    $_SESSION['message'] = "Failed to delete candidates.";
    $deleteCandidates->close();
    $conn->close();
    header("Location: dashboard.php");
    exit();
}
$deleteCandidates->close();

// Step 3: Delete the election itself
$deleteElection = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
$deleteElection->bind_param("i", $election_id);
if ($deleteElection->execute()) {
    $_SESSION['message'] = "Election and all related data deleted successfully.";
} else {
    $_SESSION['message'] = "Failed to delete election.";
}
$deleteElection->close();
$conn->close();

header("Location: dashboard.php");
exit();
?>
