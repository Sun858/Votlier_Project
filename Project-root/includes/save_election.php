<?php
// includes/save_election.php

session_start();
require_once '../DatabaseConnection/config.php';
require_once 'election.sn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'poll_id' => $_POST['poll_id'] ?? '',
        'election_type' => $_POST['election_type'] ?? '',
        'election_name' => $_POST['election_name'] ?? '',
        'start_datetime' => $_POST['start_datetime'] ?? '',
        'end_datetime' => $_POST['end_datetime'] ?? ''
    ];

    if (createOrUpdateElection($conn, $data)) {
        $_SESSION['message'] = $data['poll_id'] ? "Election updated successfully." : "Election created successfully.";
    } else {
        $_SESSION['message'] = "Failed to save election.";
    }

    header("Location: ../pages/admin_election.php");
    exit();
} else {
    header("Location: ../pages/admin_election.php");
    exit();
}