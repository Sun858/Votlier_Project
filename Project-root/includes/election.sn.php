<?php
// includes/election.sn.php

// Fetches all elections
function getAllElections($conn) {
    $sql = "SELECT * FROM election";
    return $conn->query($sql);
}

// Fetches a single election by ID
function getElectionById($conn, $pollId) {
    $stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return null;
    }
    $stmt->bind_param("i", $pollId); // Changed to 'i' for integer poll_id
    $stmt->execute();
    $result = $stmt->get_result();
    $electionData = $result->fetch_assoc();
    $stmt->close();
    return $electionData;
}

// Fetches candidates for a given election poll ID
function getCandidatesByPoll($conn, $pollId) {
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ? ORDER BY candidate_id ASC"); // Order for consistency
    if ($stmt === false) {
        error_log("Prepare failed: " . $conn->error);
        return []; // Return empty array on error
    }
    $stmt->bind_param("i", $pollId); // Changed to 'i' for integer poll_id
    $stmt->execute();
    $result = $stmt->get_result();
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
    return $candidates;
}

// Creates or Updates an Election and its Candidates (Centralized Logic)
function createOrUpdateElection($conn, $electionData, $candidatesData = []) {
    $conn->begin_transaction(); // Start transaction for atomicity

    try {
        if (!empty($electionData['poll_id'])) {
            // Update existing election
            $stmt = $conn->prepare("UPDATE election SET election_type=?, election_name=?, start_datetime=?, end_datetime=? WHERE poll_id=?");
            if ($stmt === false) throw new Exception("Prepare UPDATE election failed: " . $conn->error);
            $stmt->bind_param("ssssi", $electionData['election_type'], $electionData['election_name'], $electionData['start_datetime'], $electionData['end_datetime'], $electionData['poll_id']);
            $stmt->execute();
            $stmt->close();
            $pollId = $electionData['poll_id'];

            // For updates, delete existing candidates and re-insert to simplify logic.
            // This is safer than trying to track individual adds/edits/deletes via JavaScript.
            deleteCandidatesForPoll($conn, $pollId);

        } else {
            // Insert new election
            $stmt = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
            if ($stmt === false) throw new Exception("Prepare INSERT election failed: " . $conn->error);
            $stmt->bind_param("ssss", $electionData['election_type'], $electionData['election_name'], $electionData['start_datetime'], $electionData['end_datetime']);
            $stmt->execute();
            $stmt->close();
            $pollId = $conn->insert_id; // Get the ID of the newly inserted election
        }

        // Save candidates if provided
        if (!empty($candidatesData) && $pollId) {
            foreach ($candidatesData as $candidate) {
                // Ensure required fields exist
                if (empty($candidate['candidate_name'])) {
                    error_log("Skipping candidate due to missing name.");
                    continue; // Skip this candidate if name is empty
                }

                // Insert new candidate (as previous ones were deleted for update)
                // Removed candidate_symbol
                $stmt = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, admin_id) VALUES (?, ?, ?, ?)");
                if ($stmt === false) throw new Exception("Prepare INSERT candidate failed: " . $conn->error);
                $stmt->bind_param("isss", // 'i' for poll_id, 's' for strings, 'i' for admin_id
                    $pollId,
                    $candidate['candidate_name'],
                    $candidate['party'] ?? null,
                    $_SESSION['admin_id'] // Use the admin_id from the session
                );
                $stmt->execute();
                $stmt->close();
            }
        }

        $conn->commit(); // Commit transaction if all successful
        return true;

    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        error_log("Election save failed: " . $e->getMessage());
        return false;
    }
}

// Deletes an election and its associated candidates
function deleteElection($conn, $pollId) {
    $conn->begin_transaction(); // Start transaction

    try {
        // Delete candidates first (to satisfy foreign key constraints if they exist)
        $stmt = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
        if ($stmt === false) throw new Exception("Prepare DELETE candidates failed: " . $conn->error);
        $stmt->bind_param("i", $pollId); // Changed to 'i' for integer poll_id
        $stmt->execute();
        $stmt->close();

        // Then delete the election
        $stmt = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
        if ($stmt === false) throw new Exception("Prepare DELETE election failed: " . $conn->error);
        $stmt->bind_param("i", $pollId); // Changed to 'i' for integer poll_id
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
    $stmt->bind_param("i", $pollId); // Changed to 'i' for integer poll_id
    $stmt->execute();
    $stmt->close();
    return true;
}
?>