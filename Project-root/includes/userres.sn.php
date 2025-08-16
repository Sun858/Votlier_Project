<?php

//This is the file for the functions on showing results for the User when a vote is tallied.

// Get all elections with tally data
function getElectionsWithTally($conn) {
    $elections = [];
    $sql = "SELECT e.poll_id, e.election_name 
            FROM election e 
            JOIN tally t ON e.poll_id = t.poll_id
            GROUP BY e.poll_id, e.election_name
            ORDER BY e.start_datetime DESC";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $elections[] = $row;
        }
    }
    return $elections;
}

// Get per-candidate results for a poll
function getUserElectionResults($conn, $pollId) {
    $results = [];
    $sql = "SELECT 
                c.candidate_name, 
                c.party, 
                t.total_votes, 
                t.r1_votes, 
                t.r2_votes, 
                t.r3_votes, 
                t.r4_votes, 
                t.r5_votes 
            FROM tally t
            JOIN candidates c ON t.candidate_id = c.candidate_id
            WHERE t.poll_id = ?
            ORDER BY t.r1_votes DESC, t.total_votes DESC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $pollId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
        }
        $stmt->close();
    }
    return $results;
}

//Calculate the top N candidates by weighted points. Rank 1 is obv the highest
function calculateTopCandidates($results, $limit = 3) {
    // Define points per rank: Rank 1 = 5 points, Rank 2 = 4, ..., Rank 5 = 1
    $weights = [ 'r1_votes' => 5, 'r2_votes' => 4, 'r3_votes' => 3, 'r4_votes' => 2, 'r5_votes' => 1 ];
    foreach ($results as &$row) {
        $points =
            intval($row['r1_votes']) * $weights['r1_votes'] +
            intval($row['r2_votes']) * $weights['r2_votes'] +
            intval($row['r3_votes']) * $weights['r3_votes'] +
            intval($row['r4_votes']) * $weights['r4_votes'] +
            intval($row['r5_votes']) * $weights['r5_votes'];
        $row['points'] = $points;
    }
    unset($row);
    // Sort by points descending, then candidate_name as tie-breaker
    usort($results, function($a, $b) {
        if ($b['points'] === $a['points']) {
            return strcmp($a['candidate_name'], $b['candidate_name']);
        }
        return $b['points'] - $a['points'];
    });
    return array_slice($results, 0, $limit);
}
?>