<?php
function tallyVotes($conn, $pollId, $adminId) {
    // Log the tally action
    logAdminAction($conn, $adminId, 'Tally Votes', "Tallying votes for poll ID: $pollId");

    // Get all candidates in the selected election
    $candidateSql = "SELECT candidate_id FROM candidates WHERE poll_id = ?";
    $stmt = $conn->prepare($candidateSql);
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidateIds = [];

    while ($row = $result->fetch_assoc()) {
        $candidateIds[] = $row['candidate_id'];
    }
    $stmt->close();

    // Loop over each candidate and count votes
    foreach ($candidateIds as $candidateId) {
        $voteSql = "
            SELECT 
                COUNT(*) AS total_votes,
                SUM(CASE WHEN preference_rank = 1 THEN 1 ELSE 0 END) AS r1_votes,
                SUM(CASE WHEN preference_rank = 2 THEN 1 ELSE 0 END) AS r2_votes,
                SUM(CASE WHEN preference_rank = 3 THEN 1 ELSE 0 END) AS r3_votes
            FROM ballot
            WHERE poll_id = ? AND candidate_id = ?
        ";
        $stmt = $conn->prepare($voteSql);
        $stmt->bind_param("ii", $pollId, $candidateId);
        $stmt->execute();
        $stmt->bind_result($total, $r1, $r2, $r3);
        $stmt->fetch();
        $stmt->close();

        // Insert or update tally table
        $upsert = "
            INSERT INTO tally (poll_id, candidate_id, total_votes, r1_votes, r2_votes, r3_votes)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                total_votes = VALUES(total_votes),
                r1_votes = VALUES(r1_votes),
                r2_votes = VALUES(r2_votes),
                r3_votes = VALUES(r3_votes),
                updatetime = CURRENT_TIMESTAMP
        ";
        $stmt = $conn->prepare($upsert);
        $stmt->bind_param("iiiiii", $pollId, $candidateId, $total, $r1, $r2, $r3);
        $stmt->execute();
        $stmt->close();
    }
}

function loadAdminResultPageState($conn) {
    session_start();

    $state = [
        'pollId' => $_SESSION['selected_poll_id'] ?? null,
        'results' => $_SESSION['view_results'] ?? [],
        'tallyMsg' => $_SESSION['tally_success'] ?? null,
        'elections' => []
    ];

    // Fetch all elections for the dropdown
    $query = "SELECT poll_id, election_name FROM election ORDER BY start_datetime DESC";
    $res = $conn->query($query);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $state['elections'][] = $row;
        }
    }

    // Clear session variables after using them
    unset($_SESSION['view_results'], $_SESSION['selected_poll_id'], $_SESSION['tally_success']);

    return $state;
}

?>