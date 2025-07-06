<?php

// Fetches all elections
function getAllElections($conn) {
    $sql = "SELECT poll_id, election_name, election_type, start_datetime, end_datetime FROM election ORDER BY election_name ASC";
    return $conn->query($sql);
}

// Fetches a single election by ID
function getElectionById($conn, $pollId) {
    $stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $result = $stmt->get_result();
    $electionData = $result->fetch_assoc();
    $stmt->close();
    return $electionData;
}

// Fetches candidates for a given election poll ID
function getCandidatesByPoll($conn, $pollId) {
    $stmt = $conn->prepare("SELECT candidate_id, candidate_name, party FROM candidates WHERE poll_id = ? ORDER BY candidate_id ASC");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
    return $candidates;
}

// This one allows you tyo be lazy if you dont want to type new candidates.
function getElectionCandidatesForImport($conn, $sourcePollId) {
    $stmt = $conn->prepare("SELECT candidate_name, party FROM candidates WHERE poll_id = ? ORDER BY candidate_name ASC");
    if ($stmt === false) {
        error_log("Prepare getElectionCandidatesForImport failed: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $sourcePollId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
    return $candidates;
}


// Creates or Updates an Election and its Candidates
function createOrUpdateElection($conn, $electionData, $candidatesData = [], $adminId) {
    $conn->begin_transaction();

    try {
        $pollId = $electionData['poll_id'] ?? null;
        $endDateTime = (!empty($electionData['end_datetime'])) ? $electionData['end_datetime'] : NULL;

        if (!empty($pollId)) {
            $stmt = $conn->prepare("UPDATE election SET election_type=?, election_name=?, start_datetime=?, end_datetime=? WHERE poll_id=?");
            if ($stmt === false) throw new Exception("Prepare UPDATE election failed: " . $conn->error);

            $stmt->bind_param("ssssi", $electionData['election_type'], $electionData['election_name'], $electionData['start_datetime'], $endDateTime, $pollId);
            $stmt->execute();
            $stmt->close();

            deleteCandidatesForPoll($conn, $pollId);

        } else {
            $stmt = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
            if ($stmt === false) throw new Exception("Prepare INSERT election failed: " . $conn->error);
            $stmt->bind_param("ssss", $electionData['election_type'], $electionData['election_name'], $electionData['start_datetime'], $endDateTime);
            $stmt->execute();
            $stmt->close();
            $pollId = $conn->insert_id;
        }

        if ($pollId && !empty($candidatesData)) {
            foreach ($candidatesData as $candidate) {
                if (!isset($candidate['candidate_name']) || empty($candidate['candidate_name'])) {
                    error_log("Skipping candidate due to missing name.");
                    continue;
                }

                $stmt = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, admin_id) VALUES (?, ?, ?, ?)");
                if ($stmt === false) throw new Exception("Prepare INSERT candidate failed: " . $conn->error);

                $candidateParty = $candidate['party'] ?? null;

                $stmt->bind_param("isss",
                    $pollId,
                    $candidate['candidate_name'],
                    $candidateParty,
                    $adminId
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit();
        return $pollId;

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Election save failed: " . $e->getMessage());
        return false;
    }
}

// Deletes an election and its associated candidates
function deleteElection($conn, $pollId) {
    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
        if ($stmt === false) throw new Exception("Prepare DELETE candidates failed: " . $conn->error);
        $stmt->bind_param("i", $pollId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
        if ($stmt === false) throw new Exception("Prepare DELETE election failed: " . $conn->error);
        $stmt->bind_param("i", $pollId);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Election deletion failed: " . $e->getMessage());
        return false;
    }
}

// Helper to delete all candidates for a specific poll
function deleteCandidatesForPoll($conn, $pollId) {
    $stmt = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
    if ($stmt === false) throw new Exception("Prepare DELETE candidates for poll failed: " . $conn->error);
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $stmt->close();
    return true;
}