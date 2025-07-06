<?php
// includes/save_election.php

session_start();
require_once '../DatabaseConnection/config.php';
require_once 'election.sn.php';
require_once 'security.sn.php'; // Include security for robustness, though checkSessionTimeout isn't called here.

checkSessionTimeout(); // Re-add this if you want timeout on form submissions.

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $electionData = [
        // poll_id will be empty string for new elections, which createOrUpdateElection handles
        'poll_id' => $_POST['poll_id'] ?? '',
        'election_type' => $_POST['election_type'] ?? '',
        'election_name' => $_POST['election_name'] ?? '',
        'start_datetime' => $_POST['start_datetime'] ?? '',
        'end_datetime' => $_POST['end_datetime'] ?? ''
    ];

    // Collect candidate data if submitted
    $candidatesData = [];
    if (isset($_POST['candidates']) && is_array($_POST['candidates'])) {
        foreach ($_POST['candidates'] as $candidate) {
            // Basic validation to ensure at least a name exists
            if (!empty($candidate['candidate_name'])) {
                $candidatesData[] = [
                    'candidate_name' => $candidate['candidate_name'],
                    'party' => $candidate['party'] ?? '',
                    // Removed party_symbol as per schema update request
                ];
            }
        }
    }

    if (createOrUpdateElection($conn, $electionData, $candidatesData)) {
        $_SESSION['message'] = $electionData['poll_id'] ? "Election updated successfully." : "Election created successfully.";
    } else {
        $_SESSION['message'] = "Failed to save election. Please check logs for details.";
    }

    header("Location: ../pages/admin_election.php");
    exit();
} else {
    // If not a POST request, redirect back
    header("Location: ../pages/admin_election.php");
    exit();
}
?>