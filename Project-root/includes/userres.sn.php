<?php
/* This is all for view of the results on the user page
*/
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
?>