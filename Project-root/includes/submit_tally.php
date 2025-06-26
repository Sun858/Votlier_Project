<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../DatabaseConnection/config.php'; // This is the database connection including the authentication details
require 'security.sn.php'; // Security based functions, includes rate limiting.


$pollId = $_POST['poll_id'] ?? 0;
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tally_votes'])) {
        tallyVotes($conn, $pollId, $_SESSION['admin_id']);
    }
    if (isset($_POST['view_results'])) {
        $results = getElectionResults($conn, $pollId, $_SESSION['admin_id']);
    }
}

// Get elections from the DB and into an array
$elections = [];
$res = $conn->query("SELECT poll_id, election_name FROM election ORDER BY start_datetime DESC");
while ($row = $res->fetch_assoc()) {
    $elections[] = $row;
}

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['selected_poll_id'] = $pollId;

    if (isset($_POST['tally_votes'])) {
        tallyVotes($conn, $pollId, $_SESSION['admin_id']);
        $_SESSION['tally_success'] = "Tally completed for poll ID $pollId.";
    }

    if (isset($_POST['view_results'])) {
        $_SESSION['view_results'] = getElectionResults($conn, $pollId, $_SESSION['admin_id']);
    }

    // Always redirect back to results page after logic
    header("Location: ../pages/Admin_Result.php");
    exit();
}
?>