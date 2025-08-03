<?php
// Tallies votes for a given poll and updates the tally table for each candidate
function tallyVotes($conn, $pollId, $adminId) {
    logAdminAction($conn, $adminId, 'Tally Votes', "Tallying votes for poll ID: $pollId");

    $candidateSql = "SELECT candidate_id FROM candidates WHERE poll_id = ?";
    $stmt = $conn->prepare($candidateSql);
    if (!$stmt) {
        error_log("Failed to prepare candidate SQL: " . $conn->error);
        return;
    }
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidateIds = [];

    while ($row = $result->fetch_assoc()) {
        $candidateIds[] = $row['candidate_id'];
    }
    $stmt->close();

    // Count votes per preference rank and update/inject into tally table
    foreach ($candidateIds as $candidateId) {
        $voteSql = "
            SELECT
                COUNT(*) AS total_votes,
                SUM(CASE WHEN preference_rank = 1 THEN 1 ELSE 0 END) AS r1_votes,
                SUM(CASE WHEN preference_rank = 2 THEN 1 ELSE 0 END) AS r2_votes,
                SUM(CASE WHEN preference_rank = 3 THEN 1 ELSE 0 END) AS r3_votes,
                SUM(CASE WHEN preference_rank = 4 THEN 1 ELSE 0 END) AS r4_votes,
                SUM(CASE WHEN preference_rank = 5 THEN 1 ELSE 0 END) AS r5_votes
            FROM ballot
            WHERE poll_id = ? AND candidate_id = ?
        ";
        $stmt = $conn->prepare($voteSql);
        if (!$stmt) {
            error_log("Failed to prepare vote SQL for tally: " . $conn->error);
            continue;
        }
        $stmt->bind_param("ii", $pollId, $candidateId);
        $stmt->execute();
        // Bind results for all 5 preference ranks
        $stmt->bind_result($total, $r1, $r2, $r3, $r4, $r5);
        $stmt->fetch();
        $stmt->close();

        $upsert = "
            INSERT INTO tally (poll_id, candidate_id, total_votes, r1_votes, r2_votes, r3_votes, r4_votes, r5_votes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_votes = VALUES(total_votes),
                r1_votes = VALUES(r1_votes),
                r2_votes = VALUES(r2_votes),
                r3_votes = VALUES(r3_votes),
                r4_votes = VALUES(r4_votes),
                r5_votes = VALUES(r5_votes),
                updatetime = CURRENT_TIMESTAMP
        ";
        $stmt = $conn->prepare($upsert);
        if (!$stmt) {
            error_log("Failed to prepare upsert SQL for tally: " . $conn->error);
            continue;
        }
        // Bind parameters for all 5 preference ranks
        $stmt->bind_param("iiiiiiii", $pollId, $candidateId, $total, $r1, $r2, $r3, $r4, $r5);
        $stmt->execute();
        $stmt->close();
    }
}

// Retrieves the election results (votes per candidate) for a given poll
function getElectionResults($conn, $pollId, $adminId) {
    logAdminAction($conn, $adminId, 'View Results', "Viewing results for poll ID: $pollId");

    $results = [];
    $sql = "
        SELECT
            c.candidate_id,
            c.candidate_name,
            t.total_votes,
            t.r1_votes,
            t.r2_votes,
            t.r3_votes,
            t.r4_votes, 
            t.r5_votes, 
            c.party
        FROM tally t
        JOIN candidates c ON t.candidate_id = c.candidate_id
        WHERE t.poll_id = ?
        ORDER BY t.r1_votes DESC, t.total_votes DESC
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Failed to prepare getElectionResults SQL: " . $conn->error);
        return [];
    }
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $results[] = $row;
    }
    $stmt->close();
    return $results;
}

// Loads all data needed for admin_result.php (elections list, selected poll ID, results, message)
function loadAdminResultPageState($conn) {
    $state = [
        'pollId' => $_SESSION['selected_poll_id'] ?? null,
        'results' => $_SESSION['view_results'] ?? [],
        'tallyMsg' => $_SESSION['tally_success'] ?? null,
        'elections' => []
    ];

    $query = "SELECT poll_id, election_name FROM election ORDER BY start_datetime DESC";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $state['elections'][] = $row;
        }
    } else {
        error_log("Failed to fetch elections: " . $conn->error);
    }

    unset($_SESSION['view_results'], $_SESSION['selected_poll_id'], $_SESSION['tally_success']);

    return $state;
}
?>
