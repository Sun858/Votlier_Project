<?php
// includes/election.sn.php

function getAllElections($conn) {
    $sql = "SELECT * FROM election";
    return $conn->query($sql);
}

function getElectionById($conn, $pollId) {
    $stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
    $stmt->bind_param("s", $pollId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function createOrUpdateElection($conn, $data) {
    if (!empty($data['poll_id'])) {
        $stmt = $conn->prepare("UPDATE election SET election_type=?, election_name=?, start_datetime=?, end_datetime=? WHERE poll_id=?");
        $stmt->bind_param("sssss", $data['election_type'], $data['election_name'], $data['start_datetime'], $data['end_datetime'], $data['poll_id']);
    } else {
        $stmt = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $data['election_type'], $data['election_name'], $data['start_datetime'], $data['end_datetime']);
    }

    return $stmt->execute();
}

function deleteElection($conn, $pollId) {
    $stmt = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
    $stmt->bind_param("s", $pollId);
    return $stmt->execute();
}

function getCandidatesByPoll($conn, $pollId) {
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ?");
    $stmt->bind_param("s", $pollId);
    $stmt->execute();
    return $stmt->get_result();
}