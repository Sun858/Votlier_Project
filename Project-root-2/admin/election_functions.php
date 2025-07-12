<?php
// election_function.php

function createElection($conn, $poll_id, $election_type, $election_name, $start_datetime, $end_datetime) {
    $stmt = $conn->prepare("INSERT INTO election (poll_id, election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssss", $poll_id, $election_type, $election_name, $start_datetime, $end_datetime);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
}

function updateElection($conn, $poll_id, $election_type, $election_name, $start_datetime, $end_datetime) {
    $stmt = $conn->prepare("UPDATE election SET election_type = ?, election_name = ?, start_datetime = ?, end_datetime = ? WHERE poll_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssss", $election_type, $election_name, $start_datetime, $end_datetime, $poll_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
}

function createCandidate($conn, $poll_id, $candidate_id, $candidate_name, $party, $symbol, $candidate_image) {
    $stmt = $conn->prepare("INSERT INTO candidates (poll_id, candidate_id, candidate_name, party, candidate_symbol, candidate_image) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("ssssss", $poll_id, $candidate_id, $candidate_name, $party, $symbol, $candidate_image);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
}

function updateCandidate($conn, $candidate_id, $candidate_name, $party, $symbol, $candidate_image) {
    $stmt = $conn->prepare("UPDATE candidates SET candidate_name = ?, party = ?, candidate_symbol = ?, candidate_image = ? WHERE candidate_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("sssss", $candidate_name, $party, $symbol, $candidate_image, $candidate_id);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
}

?>
