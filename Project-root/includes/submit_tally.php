<?php
// enable ertor reporting again
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start(); // Start the session. This doesnt create a new session just resumes.

require '../DatabaseConnection/config.php'; // Database connection
require 'security.sn.php'; // Security functions
require_once '../includes/result_functions.php'; // Include result functions

// Check if admin is logged in
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}


// This prepares all the data we are to request
$pollId = $_POST['poll_id'] ?? null;
$adminId = $_SESSION['admin_id'];



// This is the handler for the form in admin_result.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pollId !== null) {
    $_SESSION['selected_poll_id'] = $pollId; 
    // Store selected poll_id for re-display

    if (isset($_POST['tally_votes'])) {
        tallyVotes($conn, $pollId, $adminId);
        // Above triggers the tallyVotes function in result_functions.php
        $_SESSION['tally_success'] = "Tally completed for poll ID $pollId.";
    } elseif (isset($_POST['view_results'])) {
        $_SESSION['view_results'] = getElectionResults($conn, $pollId, $adminId);
        // Stores all the results to display on the front end page.
    }
}

// Always redirect back to results page after logic. Apparently at least.
header("Location: ../pages/Admin_Result.php");
exit();
?>