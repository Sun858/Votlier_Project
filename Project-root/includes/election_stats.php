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

/* Retrieves the most recent admin login time from the database. */

function getLastAdminLogin($conn) {
    $sql = "SELECT attempt_time FROM login_attempts WHERE resource = 'admin_login' ORDER BY attempt_time DESC LIMIT 1";

    // Prepare and execute the statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if a result was found
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastLoginTimestamp = $row['attempt_time'];

            // Convert to Australia/Melbourne timezone
            $date = new DateTime($lastLoginTimestamp, new DateTimeZone('UTC'));
            $date->setTimezone(new DateTimeZone('Australia/Melbourne'));
            return $date->format('F j, Y, g:i a');
        }

        // Close the statement
        $stmt->close();
    }

    // Return a default message if no login attempts are found or on error
    return 'No recent login recorded';
}

// This function retrieves the last user login time from the database
function getLastUserLogin($conn) {
    $sql = "SELECT attempt_time FROM login_attempts WHERE resource = 'user_signup' ORDER BY attempt_time DESC LIMIT 1";

    // Prepare and execute the statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        // Check if a result was found
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $lastLoginTimestamp = $row['attempt_time'];

            // Format the time into a more readable string
            return date('F j, Y, g:i a', strtotime($lastLoginTimestamp));
        }

        // Close the statement
        $stmt->close();
    }

    // Return a default message if no login attempts are found or on error
    return 'No recent login recorded';
}

//   This function executes a MySQL query to select elections that start within the next 7 days

function getUpcomingElectionsCount($conn) {
    $sql = "SELECT COUNT(*) AS total_upcoming 
            FROM election 
            WHERE start_datetime >= NOW() AND start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return (int)$row['total_upcoming'];
        }
    }
    return 0; // Return 0 on failure or no elections found
}

// This function counts the number of ongoing elections
function getOngoingElectionsCount($conn) {
    $sql = "SELECT COUNT(*) AS total_ongoing 
            FROM election 
            WHERE start_datetime <= NOW() AND (end_datetime >= NOW() OR end_datetime IS NULL)";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return (int)$row['total_ongoing'];
        }
    }
    return 0; // Return 0 on failure or no ongoing elections
}

// --- FUNCTION FOR PAGINATION ---
// This function counts the total number of FAQs in the database.
function getTotalFAQsCount($conn) {
    $sql = "SELECT COUNT(*) FROM faqs";
    $result = $conn->query($sql);
    if ($result) {
        return $result->fetch_row()[0]; // We use fetch_row()[0] to get the single number from the count query
    }
    return 0;                           // Return 0 on error or no FAQs found
}

// This function retrieves a specific number of FAQs for a pagination.
function getAllFAQs($conn, $limit, $offset) {
    $faqs = [];
    // The LIMIT clause with a prepared statement protects against SQL injection
    $sql = "SELECT faq_id, question, answer, date_created FROM faqs ORDER BY date_created DESC LIMIT ?, ?";
    $stmt = $conn->prepare($sql);
    // 'ii' means we are binding two integer variables
    $stmt->bind_param("ii", $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $faqs[] = $row;
        }
    }
    
    return $faqs;
}


?>





