<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../DatabaseConnection/config.php';     // DB connection
require 'security.sn.php';                      // Security functions
require 'functions.sn.php';                     // Encryption keys, shared funcs
require 'vote.sn.php';                          // Voting functions (directly connected to this controller file)

session_start();
checkSessionTimeout();

// Get user's IP for audit and rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$resource = 'user_vote'; //This is what the 'resource' field will hold in the DB. May have to look into renaming the table lmao.

// Only logged-in users can vote
if (!isset($_SESSION['user_id'])) {
    header("location: ../pages/login.php?error=notloggedin");
    exit();
}

// Rate limit voting attempts (first option is the amount of attempts)
if (isRateLimitedDB($conn, $ip, $resource, 2, 3600)) {
    header("location: ../pages/user_election.php?error=ratelimited");
    exit();
}
recordVotingAttemptDB($conn, $ip, $resource);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $pollId = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : null;

    // Basic input validation
    if (!$pollId) {
        header("location: ../pages/user_election.php?error=missingpoll");
        exit();
    }

    // Collect votes: expecting candidate_id_1, candidate_id_2, candidate_id_3, etc.
    $votes = [];
    $maxPrefs = 3; // Set to 3 for now (can be changed later for admin config)
    for ($i = 1; $i <= $maxPrefs; $i++) {
        $cid = $_POST["candidate_id_$i"] ?? null;
        if ($cid) {
            $votes[] = [
                'candidate_id' => intval($cid),
                'preference_rank' => $i
            ];
        }
    }

    // If no votes submitted, error
    if (empty($votes)) {
        header("location: ../pages/user_election.php?poll_id=$pollId&error=novotesubmitted");
        exit();
    }

    // Call the secure model function
    $result = submitUserVote($conn, $userId, $pollId, $votes);

    if (isset($result['error'])) {
        // Redirect with error code in URL (for view display)
        header("location: ../pages/user_election.php?poll_id=$pollId&error=" . urlencode($result['error']));
        exit();
    } else {
        // Success redirect
        header("location: ../pages/user_election.php?poll_id=$pollId&success=vote");
        exit();
    }
} else {
    header("location: ../pages/user_election.php?error=invalidmethod");
    exit();
}