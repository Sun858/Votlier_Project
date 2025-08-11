<?php
// This file contains functions to retrieve election statistics from the database
// This function count Total Elections
function getTotalElections($conn)
{
    $sql = "SELECT COUNT(*) as total FROM election";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

// This function count Total Voters
function getTotalVoters($conn)
{
    $sql = "SELECT COUNT(*) as total FROM users";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

//This function count Total Candidates
function getTotalCandidates($conn)
{
    $sql = "SELECT COUNT(*) as total FROM candidates";
    $result = $conn->query($sql);
    return ($result && $row = $result->fetch_assoc()) ? $row['total'] : 0;
}

/**
 * This function returns an associative array with the total count of elections, 
 * active elections, upcoming elections and completed elections.
 */
function getElectionStatusCounts($conn)
{
    $counts = [
        'total' => 0,
        'active' => 0,
        'upcoming' => 0,
        'completed' => 0
    ];

    // Get total elections
    $counts['total'] = getTotalElections($conn);

    // Get active elections
    $currentDate = date('Y-m-d H:i:s');
    $sql = "SELECT COUNT(*) as active FROM election 
            WHERE start_datetime <= ? AND (end_datetime IS NULL OR end_datetime >= ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $currentDate, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['active'] = $row['active'];
    }

    // Get upcoming elections
    $sql = "SELECT COUNT(*) as upcoming FROM election WHERE start_datetime > ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['upcoming'] = $row['upcoming'];
    }

    // Get completed elections
    $sql = "SELECT COUNT(*) as completed FROM election WHERE end_datetime < ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $counts['completed'] = $row['completed'];
    }

    return $counts;
}
// This function retrieves the latest election activities for the admin dashboard
function getElectionActivities($conn, $limit = 5)
{
    $sql = "SELECT event_type, details, event_time FROM admin_audit_logs WHERE event_type LIKE '%Election%' ORDER BY event_time DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    $stmt->close();
    return $activities;
}
?>