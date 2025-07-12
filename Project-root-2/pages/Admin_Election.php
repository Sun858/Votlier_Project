<?php
// Start output buffering immediately to catch any stray output,
// especially important for a file that serves both HTML and JSON.
ob_start();
session_start();
header('Content-Type: text/html; charset=utf-8'); // Default to HTML for page load

// Enable error reporting for debugging (should be turned off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to send JSON response and exit
// This function will be used only when handling AJAX requests
function sendJsonResponse($success, $message, $data = []) {
    // Clear any buffered output accumulated before this point
    ob_clean();
    header('Content-Type: application/json'); // Set header for JSON response
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit(); // Terminate script execution after sending JSON
}

// Check if user is logged in and has admin role
if (!isset($_SESSION["admin_id"])) {
    // If it's an AJAX request, send JSON unauthorized error
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, 'Unauthorized access. Please log in.');
    } else {
        // Otherwise, redirect for a full page load
        header("Location: ../pages/login.php");
        exit();
    }
}

$admin_id = $_SESSION["admin_id"];

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, 'Database connection failed: ' . $conn->connect_error);
    } else {
        die("DB Error: Check 1) Docker containers 2) .env credentials: " . $conn->connect_error);
    }
}

// --- Handle AJAX GET request for fetching election details for editing or viewing candidates ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (isset($_GET['fetch_poll_id']) || isset($_GET['view_candidates_poll_id']))) {
    $poll_id = $_GET['fetch_poll_id'] ?? $_GET['view_candidates_poll_id']; // Use whichever parameter is present

    // Fetch election details (always useful for context)
    $stmt_election = $conn->prepare("SELECT poll_id, election_name, election_type, start_datetime, end_datetime FROM election WHERE poll_id = ?");
    $stmt_election->bind_param("i", $poll_id);
    $stmt_election->execute();
    $election_result = $stmt_election->get_result();
    $election_data = $election_result->fetch_assoc();
    $stmt_election->close();

    if (!$election_data) {
        sendJsonResponse(false, 'Election not found.');
    }

    // Fetch candidates for this election
    $stmt_candidates = $conn->prepare("SELECT candidate_name, party, candidate_symbol FROM candidates WHERE poll_id = ?");
    $stmt_candidates->bind_param("i", $poll_id);
    $stmt_candidates->execute();
    $candidates_result = $stmt_candidates->get_result();
    $candidates_data = [];
    while ($row = $candidates_result->fetch_assoc()) {
        $candidates_data[] = $row;
    }
    $stmt_candidates->close();

    sendJsonResponse(true, 'Election data fetched successfully.', [
        'election' => $election_data,
        'candidates' => $candidates_data
    ]);
}


// --- Handle AJAX POST request for saving/updating election ---
// This block will execute ONLY if it's an AJAX POST request sending JSON data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['election']) || !isset($data['candidates'])) {
        sendJsonResponse(false, 'Invalid data provided. Missing election or candidates data.');
    }

    $election = $data['election'];
    $candidates = $data['candidates'];
    $poll_id_to_update = filter_var($election['poll_id'] ?? null, FILTER_VALIDATE_INT); // Check for poll_id for updates

    // Validate election data
    $election_name = trim($election['election_name'] ?? '');
    $election_type = trim($election['election_type'] ?? '');
    $start_datetime_str = trim($election['start_datetime'] ?? '');
    $end_datetime_str = trim($election['end_datetime'] ?? '');

    if (empty($election_name) || empty($election_type) || empty($start_datetime_str)) {
        sendJsonResponse(false, 'Election name, type, and start date are required.');
    }

    // Basic date validation
    $start_datetime = new DateTime($start_datetime_str);
    $end_datetime = null;
    if (!empty($end_datetime_str)) {
        $end_datetime = new DateTime($end_datetime_str);
        if ($end_datetime < $start_datetime) {
            sendJsonResponse(false, 'End Date & Time cannot be before Start Date & Time.');
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        if ($poll_id_to_update) {
            // This is an UPDATE operation
            $stmt_election = $conn->prepare("UPDATE election SET election_type = ?, election_name = ?, start_datetime = ?, end_datetime = ? WHERE poll_id = ?");
            if (!$stmt_election) {
                throw new Exception('Prepare statement failed for election update: ' . $conn->error);
            }
            $stmt_election->bind_param("ssssi", $election_type, $election_name, $start_datetime_str, $end_datetime_str, $poll_id_to_update);
            if (!$stmt_election->execute()) {
                throw new Exception('Execute statement failed for election update: ' . $stmt_election->error);
            }
            $stmt_election->close();

            // Delete all existing candidates for this poll_id before re-inserting
            $stmt_delete_candidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
            if (!$stmt_delete_candidates) {
                throw new Exception('Prepare statement failed for deleting old candidates: ' . $conn->error);
            }
            $stmt_delete_candidates->bind_param("i", $poll_id_to_update);
            if (!$stmt_delete_candidates->execute()) {
                throw new Exception('Execute statement failed for deleting old candidates: ' . $stmt_delete_candidates->error);
            }
            $stmt_delete_candidates->close();

            $actual_poll_id_for_candidates = $poll_id_to_update; // Use the existing poll_id
            $message_suffix = 'updated';

        } else {
            // This is a NEW election creation
            $stmt_election = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
            if (!$stmt_election) {
                throw new Exception('Prepare statement failed for new election: ' . $conn->error);
            }
            $stmt_election->bind_param("ssss", $election_type, $election_name, $start_datetime_str, $end_datetime_str);
            if (!$stmt_election->execute()) {
                throw new Exception('Execute statement failed for new election: ' . $stmt_election->error);
            }
            $actual_poll_id_for_candidates = $conn->insert_id; // Get the newly inserted poll_id
            $stmt_election->close();
            $message_suffix = 'added';
        }

        // Insert/Re-insert candidates into 'candidates' table
        if (!empty($candidates)) {
            $stmt_candidates = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, candidate_symbol, admin_id) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt_candidates) {
                throw new Exception('Prepare statement failed for candidates: ' . $conn->error);
            }

            foreach ($candidates as $candidate) {
                $candidate_name = trim($candidate['name'] ?? '');
                $party = trim($candidate['party'] ?? '');
                $symbol = trim($candidate['symbol'] ?? '');

                if (empty($candidate_name)) {
                    throw new Exception('Candidate name cannot be empty for an election.');
                }

                $stmt_candidates->bind_param("isssi", $actual_poll_id_for_candidates, $candidate_name, $party, $symbol, $admin_id);
                if (!$stmt_candidates->execute()) {
                    throw new Exception('Execute statement failed for candidate ' . htmlspecialchars($candidate_name) . ': ' . $stmt_candidates->error);
                }
            }
            $stmt_candidates->close();
        }

        // If everything is successful, commit the transaction
        $conn->commit();
        sendJsonResponse(true, 'Election and candidates ' . $message_suffix . ' successfully!', ['poll_id' => $actual_poll_id_for_candidates]);

    } catch (Exception $e) {
        // Something went wrong, rollback the transaction
        $conn->rollback();
        sendJsonResponse(false, 'Failed to ' . $message_suffix . ' election: ' . $e->getMessage());
    } finally {
        // Ensure connection is closed.
        if ($conn) {
            $conn->close();
        }
    }
}

// --- Dynamic Table Data Fetching for AJAX (Search, Filter, Sort, Pagination) ---
if (isset($_GET['fetch_table_data']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $search_query = $_GET['search_query'] ?? '';
    $election_type_filter = $_GET['election_type_filter'] ?? '';
    $election_status_filter = $_GET['election_status_filter'] ?? '';
    $sort_column = $_GET['sort_column'] ?? 'poll_id'; // Default sort
    $sort_direction = $_GET['sort_direction'] ?? 'asc'; // Default direction

    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit_per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10; // Default items per page
    $offset = ($page - 1) * $limit_per_page;

    // Build WHERE clause for filters and search
    $where_clauses = [];
    $bind_types = '';
    $bind_params = [];

    // Search query
    if (!empty($search_query)) {
        // Search across election name, type, and candidate names
        $search_term = '%' . $search_query . '%';
        $where_clauses[] = "(e.election_name LIKE ? OR e.election_type LIKE ? OR c.candidate_name LIKE ?)";
        $bind_types .= 'sss';
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
    }

    // Type filter
    if (!empty($election_type_filter)) {
        $where_clauses[] = "e.election_type = ?";
        $bind_types .= 's';
        $bind_params[] = $election_type_filter;
    }

    // Base query components
    $select_cols = "e.poll_id, e.election_name, e.election_type, e.start_datetime, e.end_datetime, (SELECT COUNT(c2.candidate_id) FROM candidates c2 WHERE c2.poll_id = e.poll_id) AS candidate_count";
    $from_tables = "election e";
    $join_clause = ""; // Initially no join unless searching candidates

    // If searching candidates, we need a LEFT JOIN to candidates table
    if (!empty($search_query)) {
        $join_clause = "LEFT JOIN candidates c ON e.poll_id = c.poll_id";
    }

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $group_by_sql = !empty($join_clause) ? "GROUP BY e.poll_id" : ""; // Group if joined for candidates

    // Total count query for pagination
    $count_query = "SELECT COUNT(DISTINCT e.poll_id) AS total_records FROM election e " . $join_clause . " " . $where_sql;
    $stmt_count = $conn->prepare($count_query);

    if ($stmt_count && !empty($bind_params)) {
        // Use a temporary array for bind_param arguments for call_user_func_array
        $tmp_bind_params = [];
        $tmp_bind_params[] = $bind_types;
        foreach ($bind_params as $param) {
            $tmp_bind_params[] = &$param; // Pass by reference
        }
        call_user_func_array([$stmt_count, 'bind_param'], $tmp_bind_params);
    }

    $stmt_count->execute();
    $total_records = $stmt_count->get_result()->fetch_assoc()['total_records'];
    $stmt_count->close();

    $total_pages = ceil($total_records / $limit_per_page);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages; // Adjust page if it's out of bounds after filtering
        $offset = ($page - 1) * $limit_per_page;
    } elseif ($total_pages == 0) {
        $page = 1; // If no records, set page to 1
        $offset = 0;
    }


    // Validate sort column to prevent SQL injection
    $allowed_sort_columns = ['election_name', 'election_type', 'start_datetime', 'end_datetime', 'candidate_count', 'poll_id']; // Added poll_id as it's default
    if (!in_array($sort_column, $allowed_sort_columns)) {
        $sort_column = 'poll_id'; // Default to a safe column
    }
    $sort_direction = (strtolower($sort_direction) === 'desc') ? 'DESC' : 'ASC';


    // Main data query
    $query_sql = "SELECT {$select_cols} FROM {$from_tables} {$join_clause} {$where_sql} {$group_by_sql} ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
    $stmt_data = $conn->prepare($query_sql);

    if (!$stmt_data) {
        sendJsonResponse(false, 'Prepare statement failed for data fetch: ' . $conn->error);
    }

    // Prepare parameters for the main data query
    $data_bind_types = $bind_types . 'ii'; // Add types for LIMIT and OFFSET
    $data_bind_params = array_merge($bind_params, [&$limit_per_page, &$offset]); // Merge all params

    // Bind parameters for the main data query
    if (!empty($data_bind_params)) {
        $tmp_bind_params = [];
        $tmp_bind_params[] = $data_bind_types;
        foreach ($data_bind_params as $key => $val) {
            // Only add by reference if it's not the first element (which is the types string)
            if ($key === 0) {
                $tmp_bind_params[] = $val;
            } else {
                $tmp_bind_params[] = &$data_bind_params[$key];
            }
        }
        call_user_func_array([$stmt_data, 'bind_param'], $tmp_bind_params);
    }

    $stmt_data->execute();
    $elections_data = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_data->close();

    // Calculate status for each row for frontend display
    $timezone = new DateTimeZone('Australia/Melbourne');
    $now = new DateTime('now', $timezone);
    $current_time = $now->getTimestamp();

    foreach ($elections_data as &$row) {
        $start_timestamp = strtotime($row['start_datetime'] ?? '');
        $end_timestamp = strtotime($row['end_datetime'] ?? '');

        $display_status = 'Unknown';
        if ($end_timestamp && $end_timestamp < $current_time) {
            $display_status = 'Completed';
        } elseif ($start_timestamp && $start_timestamp > $current_time) {
            $display_status = 'Upcoming';
        } elseif ($start_timestamp && $end_timestamp && $start_timestamp <= $current_time && $end_timestamp >= $current_time) {
            $display_status = 'Ongoing';
        }
        $row['status'] = $display_status;
    }
    unset($row); // Unset the reference

    sendJsonResponse(true, 'Elections data fetched successfully.', [
        'elections' => $elections_data,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'limit_per_page' => $limit_per_page
    ]);
}

// --- End of AJAX table data handling ---


// If the script reaches here, it means it's a regular page load (GET request or non-JSON POST
// without 'fetch_poll_id' or 'fetch_table_data' parameter) and we should proceed with rendering the HTML content.

// Initial data fetch for the page load (not via AJAX)
// This initial query should not have LIMIT/OFFSET as the JS will immediately replace it via AJAX.
// But we need election types and possible statuses to populate filters.

// Fetch distinct election types for the dropdown filter
$election_types_result = $conn->query("SELECT DISTINCT election_type FROM election ORDER BY election_type ASC");
$election_types = [];
if ($election_types_result) {
    while ($row = $election_types_result->fetch_assoc()) {
        $election_types[] = htmlspecialchars($row['election_type']);
    }
}

// For statuses, we don't query the DB. We know the possible values by using the start_datetime and end_datetime columns.
$possible_statuses = ['Ongoing', 'Upcoming', 'Completed']; // Manually define
sort($possible_statuses); // Sort them alphabetically for consistent display

// End output buffering for the main HTML content, sending it to the browser.
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Election.css">
    <link rel="stylesheet" href="../Assets/css/Admin_Election_View_Candidates_Modal.css">
    <style>
        /* Existing CSS for table, dialog, notification (from previous versions - keeping them) */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-family: 'Inter', sans-serif;
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: 600;
            cursor: pointer; /* Indicate sortable columns */
            position: relative; /* For sort arrows */
        }
        th.sortable:hover {
            background-color: #e6e6e6; /* Slightly darker on hover */
        }
        th .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 5px; /* Space between text and arrow/dropdown */
        }
        .sort-arrow {
            font-size: 0.8em;
            margin-left: 5px;
            visibility: hidden; /* Hidden by default */
        }
        th.asc .sort-arrow.up,
        th.desc .sort-arrow.down {
            visibility: visible;
        }

        /* Styles for filter dropdown within th */
        th select {
            padding: 4px 8px; /* Smaller padding for in-header dropdown */
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-size: 0.9em; /* Smaller font size */
            background-color: #fff;
            margin-top: 5px; /* Space from header text */
            width: 100%; /* Take full width of header */
            box-sizing: border-box; /* Include padding and border in width */
        }
        th select:focus {
            outline: none;
            border-color: rgb(101, 76, 175);
            box-shadow: 0 0 0 1px rgba(101, 76, 175, 0.2);
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #f1f1f1;
        }
        .action-buttons a {
            margin-right: 10px;
            color: #007BFF;
            text-decoration: none;
        }
        .action-buttons a:hover {
            text-decoration: underline;
        }
        /* Style for the new "View Candidates" button */
        .view-candidates-btn {
            background: none; /* Make it look like a link */
            border: none;
            padding: 0;
            font: inherit;
            cursor: pointer;
            color: #007BFF; /* Link color */
            text-decoration: underline; /* Underline like a link */
            display: inline; /* To behave like a link */
            margin-left: 10px; /* Space it out from other action buttons */
        }
        .view-candidates-btn:hover {
            color: #0056b3; /* Darker blue on hover */
            text-decoration: none; /* No underline on hover */
        }


        .delete-button {
            color: red;
            font-weight: bold;
        }
        .tooltip {
            position: relative;
            cursor: pointer;
        }
        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            background-color: #333;
            color: #fff;
            padding: 6px 8px;
            border-radius: 4px;
            top: -35px;
            left: 0;
            white-space: nowrap;
            font-size: 12px;
            z-index: 1000;
        }
        td[colspan="7"] {
            text-align: center;
            color: #555;
            padding: 20px;
        }
        .candidate-count {
            font-weight: 600;
            color: #555;
            margin-left: 5px;
        }
        .delete-button.loading {
            cursor: wait;
            opacity: 0.7;
        }

        /* Custom Dialog Styles */
        #custom-confirm-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }

        #custom-confirm-dialog {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border: 1px solid #ccc;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            border-radius: 8px;
            z-index: 9999;
            min-width: 280px;
            max-width: 400px;
            text-align: center;
            font-family: 'Inter', sans-serif;
        }

        #confirm-message {
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #333;
        }

        #custom-confirm-dialog button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        #confirm-yes {
            background-color: #dc3545;
            color: white;
        }

        #confirm-yes:hover {
            background-color: #c82333;
        }

        #confirm-no {
            background-color: #6c757d;
            color: white;
            margin-left: 10px; /* Added spacing */
        }

        #confirm-no:hover {
            background-color: #5a6268;
        }

        /* Notification Styles */
        #notification {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            padding: 15px 25px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            display: none;
            animation: fadeInOut 3s ease-in-out;
            font-weight: 500;
            text-align: center;
        }

        #notification.error {
            background-color: #f44336;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* NEW WIZARD STYLES */
        .new-wizard-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            font-family: 'Inter', sans-serif;
        }

        .new-wizard-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            width: 90%;
            max-width: 800px;
            box-sizing: border-box;
            position: relative;
            animation: fadeInScale 0.3s ease-out;
            max-height: 90vh; /* Limit height to prevent overflow */
            overflow-y: auto; /* Enable scrolling for long content */
        }

        .new-wizard-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .new-wizard-header h2 {
            margin: 0;
            color: #333;
            font-size: 1.8em;
            font-weight: 600;
        }

        .new-wizard-step {
            display: none; /* Steps are hidden by default */
        }

        .new-wizard-step.active {
            display: block; /* Active step is shown */
        }

        .new-wizard-form-group {
            margin-bottom: 20px;
        }

        .new-wizard-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 0.95em;
        }

        .new-wizard-form-group input[type="text"],
        .new-wizard-form-group input[type="datetime-local"],
        .new-wizard-form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            box-sizing: border-box;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .new-wizard-form-group input[type="text"]:focus,
        .new-wizard-form-group input[type="datetime-local"]:focus,
        .new-wizard-form-group select:focus {
            outline: none;
            border-color: rgb(101, 76, 175);
            box-shadow: 0 0 0 3px rgba(101, 76, 175, 0.2);
        }

        .new-wizard-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .new-wizard-navigation button {
            padding: 12px 25px;
            border: none;
            border-radius: 75px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.1s ease;
        }

        .new-wizard-navigation button.prev-btn {
            background-color: #6c757d;
            color: white;
        }

        .new-wizard-navigation button.prev-btn:hover {
            background-color: #5a6268;
            transform: translateY(-2px);
        }

        .new-wizard-navigation button.next-btn,
        .new-wizard-navigation button.submit-btn {
            background-color: rgb(101, 76, 175);
            color: white;
        }

        .new-wizard-navigation button.next-btn:hover,
        .new-wizard-navigation button.submit-btn:hover {
            background-color: rgb(80, 60, 140);
            transform: translateY(-2px);
        }

        .new-wizard-navigation button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Candidate Details Section for New Wizard */
        #new-candidates-list {
            margin-top: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            min-height: 100px;
            background-color: #f9f9f9;
        }

        .new-candidate-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .new-candidate-info {
            flex-grow: 1;
            font-size: 0.95em;
            color: #444;
        }

        .new-candidate-info strong {
            color: #222;
        }

        .new-candidate-actions button {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2em;
            transition: color 0.2s ease;
        }
        .new-candidate-actions button.edit-candidate-btn {
            color: #007BFF; /* A distinct color for edit */
            margin-right: 5px; /* Spacing between edit and delete */
        }
        .new-candidate-actions button.edit-candidate-btn:hover {
            color: #0056b3;
        }
        .new-candidate-actions button.remove-candidate-btn:hover {
            color: #c82333;
        }

        #new-add-candidate-btn {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 75px;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.2s ease;
            margin-top: 15px;
        }

        #new-add-candidate-btn:hover {
            background-color: #218838;
        }

        /* Review Section for New Wizard */
        #new-review-details p {
            margin-bottom: 10px;
            font-size: 1.05em;
            color: #333;
        }

        #new-review-candidates-list {
            list-style: none;
            padding: 0;
            margin-top: 15px;
        }

        #new-review-candidates-list li {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 8px;
            font-size: 0.95em;
            color: #495057;
        }

        /* Styles for search and pagination */
        .search-container {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .search-container input {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            flex-grow: 1;
            max-width: 400px; /* Limit search bar width */
        }
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding-bottom: 20px;
        }
        .pagination-button {
            background-color: rgb(101, 76, 175);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .pagination-button:hover:not(:disabled) {
            background-color: rgb(80, 60, 140);
        }
        .pagination-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .page-info {
            font-weight: 500;
            color: #555;
        }

        /* Modal specific styles for View Candidates */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1001; /* Sit on top of everything */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 10px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            position: relative;
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        #candidatesContent h3 {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        #candidatesContent ul {
            list-style: none;
            padding: 0;
        }

        #candidatesContent ul li {
            background-color: #e9ecef;
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 8px;
            font-size: 0.95em;
            color: #495057;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <ion-icon class="voter-icon" name="person-circle-outline"></ion-icon>
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="Admin_Home.php">
                    <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                    <span class="text">Home</span>
                </a></li>
                <li><a href="Admin_Profile.php">
                    <span class="icon"><ion-icon name="people-outline"></ion-icon></span>
                    <span class="text">Profile</span>
                </a></li>
                <li><a href="Admin_Election.php">
                    <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                    <span class="text">Election</span>
                </a></li>
                <li><a href="Admin_Result.php">
                    <span class="icon"><ion-icon name="eye-outline"></ion-icon></span>
                    <span class="text">Result</span>
                </a></li>
                <li><a href="Admin_Settings.php">
                    <span class="icon"><ion-icon name="settings-outline"></ion-icon></span>
                    <span class="text">Settings</span>
                </a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../includes/logout.php" class="footer-link signout-link">
                <span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
                <span class="text">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Welcome to Voter Dashboard</h1>
            <p>Explore your data and manage your business efficiently</p>
        </header>

        <div style="margin: 25px 0; display: flex; gap: 15px;">
            <!-- Original button/link for creating election (preserving its behavior) -->
            <a href="/admin/admin_create_election.php"
                style="
                    display: inline-flex;
                    align-items: center;
                    padding: 12px 20px;
                    background-color: #007BFF; /* A different color to distinguish */
                    color: white;
                    text-decoration: none;
                    border-radius: 75px;
                    font-weight: 500;
                    gap: 8px;">
                <ion-icon name="create-outline"></ion-icon>
                Create Election (Old)
            </a>

            <!-- NEW button for the wizard -->
            <button id="triggerNewElectionWizardBtn"
                style="
                    display: inline-flex;
                    align-items: center;
                    padding: 12px 20px;
                    background-color:rgb(101, 76, 175); /* Your desired color for the new wizard button */
                    color: white;
                    text-decoration: none;
                    border-radius: 75px;
                    font-weight: 500;
                    gap: 8px;
                    border: none;
                    cursor: pointer;
                    ">
                <ion-icon name="add-circle-outline"></ion-icon>
                Add New Election (Wizard)
            </button>
        </div>

        <div class="search-container">
            <input type="text" id="electionSearchInput" placeholder="Search elections by name, type, or candidate...">
            <button id="clearSearchBtn" class="pagination-button" style="background-color: #6c757d;">Clear Search</button>
        </div>


        <table>
            <caption style="
                caption-side: top;
                font-size: 1.25rem;
                font-weight: 600;
                padding: 12px;
                color: #333;
                background-color: #f0f0f0;
                border-radius: 8px 8px 0 0;
                text-align: center;
                letter-spacing: 0.5px;">
                Election Management Table</caption>

            <thead>
                <tr>
                    <th class="sortable" data-column="election_name">
                        <div class="header-content">
                            Election Name
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th data-column="election_type">
                        <div class="header-content">
                            Type
                            <select id="electionTypeFilter" onclick="event.stopPropagation()">
                                <option value="">All Types</option>
                                <?php foreach ($election_types as $type): ?>
                                    <option value="<?= $type ?>"><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th data-column="status">
                        <div class="header-content">
                            Status
                            <select id="electionStatusFilter" onclick="event.stopPropagation()">
                                <option value="">All Statuses</option>
                                <?php foreach ($possible_statuses as $status_option): ?>
                                    <option value="<?= $status_option ?>"><?= $status_option ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th class="sortable" data-column="start_datetime">
                        <div class="header-content">
                            Start Date
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th class="sortable" data-column="end_datetime">
                        <div class="header-content">
                            End Date
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th class="sortable" data-column="candidate_count">
                        <div class="header-content">
                            Candidates
                            <span class="sort-arrow up">&#9650;</span>
                            <span class="sort-arrow down">&#9660;</span>
                        </div>
                    </th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="election-table-body">
                <!-- Data will be loaded via JavaScript AJAX -->
                <tr><td colspan="7" style="text-align:center; padding: 20px;">Loading elections...</td></tr>
            </tbody>
        </table>

        <div id="no-elections-message-container" style="display: none;">
            <p id="no-elections-message" style="text-align: center; color: #555; padding: 20px;">There are no current existing elections.</p>
        </div>

        <div id="note-section" class="delete-section" style="margin-top: 20px; display: none;">
            <p><strong>Note:</strong> You can EDIT or DELETE an election using the buttons in the "Action" column.</p>
        </div>

        <div class="pagination-container">
            <button id="prevPageBtn" class="pagination-button">Previous</button>
            <span class="page-info">Page <span id="currentPage">1</span> of <span id="totalPages">1</span></span>
            <button id="nextPageBtn" class="pagination-button">Next</button>
        </div>
    </main>

    <div id="custom-confirm-overlay"></div>
    <div id="custom-confirm-dialog">
        <p id="confirm-message"></p>
        <div style="display: flex; justify-content: center; gap: 10px;">
            <button id="confirm-yes">Yes, Delete</button>
            <button id="confirm-no">Cancel</button>
        </div>
    </div>

    <div id="notification"></div>

    <!-- Modal structure for viewing candidates -->
    <div id="candidatesModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="candidatesContent">
                <!-- Candidates will be loaded here -->
            </div>
        </div>
    </div>

    <!-- NEW ELECTION WIZARD OVERLAY -->
    <div id="newElectionWizardOverlay" class="new-wizard-overlay">
        <div class="new-wizard-container">
            <div class="new-wizard-header">
                <h2>Create New Election</h2>
            </div>

            <!-- Hidden input to store poll_id when editing -->
            <input type="hidden" id="editPollId">

            <!-- Step 1: Election Details -->
            <div id="newElectionDetailsStep" class="new-wizard-step active">
                <h3>Step 1: Election Details</h3>
                <div class="new-wizard-form-group">
                    <label for="newElectionName">Election Name:</label>
                    <input type="text" id="newElectionName" required>
                </div>
                <div class="new-wizard-form-group">
                    <label for="newElectionType">Election Type:</label>
                    <select id="newElectionType" required>
                        <option value="">Select Type</option>
                        <option value="Federal">Federal</option>
                        <option value="State">State</option>
                        <option value="Local">Local</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="new-wizard-form-group">
                    <label for="newStartDatetime">Start Date & Time:</label>
                    <input type="datetime-local" id="newStartDatetime" required>
                </div>
                <div class="new-wizard-form-group">
                    <label for="newEndDatetime">End Date & Time:</label>
                    <input type="datetime-local" id="newEndDatetime">
                </div>
            </div>

            <!-- Step 2: Candidate Details -->
            <div id="newCandidateDetailsStep" class="new-wizard-step">
                <h3>Step 2: Candidate Details</h3>
                <div class="new-wizard-form-group">
                    <label for="newCandidateName">Candidate Name:</label>
                    <input type="text" id="newCandidateName">
                </div>
                <div class="new-wizard-form-group">
                    <label for="newCandidateParty">Party (Optional):</label>
                    <input type="text" id="newCandidateParty">
                </div>
                <div class="new-wizard-form-group">
                    <label for="newCandidateSymbol">Symbol (Optional):</label>
                    <input type="text" id="newCandidateSymbol">
                </div>
                <button type="button" id="new-add-candidate-btn">Add Candidate</button>
                <div id="candidateAddConfirmation" style="color: green; margin-top: 10px; font-weight: bold; display: none;">Candidate Added!</div>


                <h4>Current Candidates:</h4>
                <div id="new-candidates-list">
                    <!-- Candidates will be dynamically added here -->
                    <p id="new-no-candidates-message" style="text-align: center; color: #777;">No candidates added yet.</p>
                </div>
            </div>

            <!-- Step 3: Review -->
            <div id="newReviewStep" class="new-wizard-step">
                <h3>Step 3: Review Election</h3>
                <div id="new-review-details">
                    <p><strong>Election Name:</strong> <span id="newReviewElectionName"></span></p>
                    <p><strong>Election Type:</strong> <span id="newReviewElectionType"></span></p>
                    <p><strong>Start Date & Time:</strong> <span id="newReviewStartDatetime"></span></p>
                    <p><strong>End Date & Time:</strong> <span id="newReviewEndDatetime"></span></p>
                </div>
                <h4>Candidates:</h4>
                <ul id="new-review-candidates-list">
                    <!-- Reviewed candidates will be dynamically added here -->
                    <li>No candidates added.</li>
                </ul>
            </div>

            <div class="new-wizard-navigation">
                <button type="button" class="new-prev-btn" style="display: none;">Previous</button>
                <button type="button" class="new-next-btn">Next</button>
                <button type="submit" class="new-submit-btn" style="display: none;">Submit Election</button>
            </div>
        </div>
    </div>


    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    
    <!-- Link of JavaScript file for candidates modal -->
    <script src="../Assets/js/admin_dashboard.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const electionTableBody = document.getElementById('election-table-body');
        const electionTypeFilter = document.getElementById('electionTypeFilter');
        const electionStatusFilter = document.getElementById('electionStatusFilter');
        const noElectionsMessageContainer = document.getElementById('no-elections-message-container');
        const noElectionsMessage = document.getElementById('no-elections-message');
        const noteSection = document.getElementById('note-section');
        const tableHeaders = document.querySelectorAll('th[data-column]');

        // Get references to custom dialog elements (for delete confirmation)
        const confirmOverlay = document.getElementById('custom-confirm-overlay');
        const confirmDialog = document.getElementById('custom-confirm-dialog');
        const confirmMessage = document.getElementById('confirm-message');
        const confirmYesBtn = document.getElementById('confirm-yes');
        const confirmNoBtn = document.getElementById('confirm-no');

        // Notification element
        const notification = document.getElementById('notification');

        // Variables to store current deletion context
        let currentDeleteButton = null;
        let currentPollId = null;

        let currentSortColumn = 'poll_id'; // Default sort column
        let currentSortDirection = 'asc'; // Default sort direction

        // Pagination elements
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const currentPageSpan = document.getElementById('currentPage');
        const totalPagesSpan = document.getElementById('totalPages');

        let currentPage = 1;
        const itemsPerPage = 5; // You can make this configurable

        // Search elements
        const electionSearchInput = document.getElementById('electionSearchInput');
        const clearSearchBtn = document.getElementById('clearSearchBtn');

        // Candidates Modal elements
        const candidatesModal = document.getElementById('candidatesModal');
        const candidatesContent = document.getElementById('candidatesContent');
        const closeModalBtn = candidatesModal.querySelector('.close-button');


        // Helper function to show notifications
        function showNotification(message, isError = false) {
            notification.textContent = message;
            notification.className = isError ? 'notification error' : 'notification';
            notification.style.display = 'block';

            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Function to render the elections table dynamically via AJAX
        async function fetchAndRenderElections() {
            electionTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding: 20px;">Loading elections...</td></tr>';
            noElectionsMessageContainer.style.display = 'none';
            noteSection.style.display = 'none';

            const searchQuery = electionSearchInput.value.trim();
            const electionType = electionTypeFilter.value;
            const electionStatus = electionStatusFilter.value;

            // Construct URL parameters
            const params = new URLSearchParams({
                fetch_table_data: true, // Indicate this is a request for table data
                page: currentPage,
                limit: itemsPerPage,
                sort_column: currentSortColumn,
                sort_direction: currentSortDirection,
                search_query: searchQuery,
                election_type_filter: electionType,
                election_status_filter: electionStatus // This will need to be handled PHP side for status calculation
            });

            try {
                const response = await fetch(`${window.location.href}?${params.toString()}`);
                const result = await response.json();

                if (result.success) {
                    const elections = result.elections;
                    const totalPages = result.total_pages;
                    const totalRecords = result.total_records;

                    electionTableBody.innerHTML = ''; // Clear previous content

                    if (elections.length > 0) {
                        elections.forEach(row => {
                            // Calculate status (redundant from PHP now, but good for consistency)
                            const now = new Date();
                            const start = new Date(row.start_datetime);
                            const end = row.end_datetime ? new Date(row.end_datetime) : null;

                            let display_status = 'Unknown';
                            if (end && end < now) {
                                display_status = 'Completed';
                            } else if (start > now) {
                                display_status = 'Upcoming';
                            } else if (start <= now && (!end || end >= now)) {
                                display_status = 'Ongoing';
                            }

                            const tr = document.createElement('tr');
                            tr.id = `election-${row.poll_id}`;
                            tr.dataset.electionName = row.election_name;
                            tr.dataset.electionType = row.election_type;
                            tr.dataset.status = display_status; // Use calculated status
                            tr.dataset.startDatetime = row.start_datetime;
                            tr.dataset.endDatetime = row.end_datetime;
                            tr.dataset.candidateCount = row.candidate_count;

                            tr.innerHTML = `
                                <td>${row.election_name}</td>
                                <td>${row.election_type}</td>
                                <td>${display_status}</td>
                                <td>${row.start_datetime}</td>
                                <td>${row.end_datetime}</td>
                                <td>${row.candidate_count}</td>
                                <td class="action-buttons">
                                    <button type="button" class="edit-election-btn tooltip"
                                        data-poll-id="${row.poll_id}"
                                        data-tooltip="Edit ${row.election_name} ${row.poll_id}">✏️</button>
                                    <a href="#"
                                        class="delete-button tooltip"
                                        data-poll-id="${row.poll_id}" data-tooltip="Delete ${row.election_name} ${row.poll_id}">🗑️</a>
                                    <button type="button"
                                        class="view-candidates-btn tooltip"
                                        data-poll-id="${row.poll_id}"
                                        data-tooltip="View Candidates for ${row.election_name}">👥</button>
                                </td>
                            `;
                            electionTableBody.appendChild(tr);
                        });
                        noElectionsMessageContainer.style.display = 'none';
                        noteSection.style.display = 'block';
                    } else {
                        noElectionsMessage.textContent = searchQuery || electionType || electionStatus ? "No matching elections found for the selected criteria." : "There are no current existing elections.";
                        noElectionsMessageContainer.style.display = 'block';
                        noteSection.style.display = 'none';
                    }

                    // Update pagination controls
                    currentPageSpan.textContent = result.current_page; // Use the page returned by PHP
                    totalPagesSpan.textContent = totalPages;
                    prevPageBtn.disabled = (currentPage === 1);
                    nextPageBtn.disabled = (currentPage === totalPages || totalPages === 0);

                } else {
                    electionTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color:red;">Error loading elections: ${result.message}</td></tr>`;
                    showNotification('Error loading elections: ' + result.message, true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                electionTableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color:red;">An unexpected error occurred: ${error.message}</td></tr>`;
                showNotification('An unexpected error occurred: ' + error.message, true);
            }
        }


        // Event listeners for the filter dropdowns
        electionTypeFilter.addEventListener('change', () => {
            currentPage = 1; // Reset to first page on filter change
            fetchAndRenderElections();
        });
        electionStatusFilter.addEventListener('change', () => {
            currentPage = 1; // Reset to first page on filter change
            fetchAndRenderElections();
        });

        // Event listeners for sorting (delegated to the parent table to handle clicks on th)
        document.querySelector('thead tr').addEventListener('click', function(event) {
            const targetTh = event.target.closest('th[data-column]');

            // Prevent sorting if click is directly on a select element within a th
            if (!targetTh || event.target.tagName === 'SELECT') {
                return;
            }

            const column = targetTh.dataset.column;

            // Update sort direction
            if (currentSortColumn === column) {
                currentSortDirection = (currentSortDirection === 'asc' ? 'desc' : 'asc');
            } else {
                currentSortColumn = column;
                currentSortDirection = 'asc'; // Default to ascending for new column
            }

            // Remove existing sort classes and arrows from all headers
            tableHeaders.forEach(th => {
                th.classList.remove('asc', 'desc');
            });

            // Add new sort class to the clicked header
            targetTh.classList.add(currentSortDirection);

            currentPage = 1; // Reset to first page on sort change
            fetchAndRenderElections(); // Re-fetch both filter and sort
        });


        // Delegated event listener for delete buttons
        electionTableBody.addEventListener('click', function(event) {
            const deleteButton = event.target.closest('.delete-button');
            if (!deleteButton) return;

            event.preventDefault();

            currentDeleteButton = deleteButton;
            currentPollId = deleteButton.dataset.pollId;

            // Show custom confirmation dialog
            confirmMessage.textContent = 'Are you sure you want to delete this election? This action cannot be undone.';
            confirmDialog.style.display = 'block';
            confirmOverlay.style.display = 'block';
        });

        // Handle "Yes" click on custom dialog
        confirmYesBtn.addEventListener('click', async function() {
            // Hide dialog
            confirmDialog.style.display = 'none';
            confirmOverlay.style.display = 'none';

            // Proceed with the actual delete
            if (currentDeleteButton && currentPollId) {
                const originalButtonText = currentDeleteButton.textContent;
                const originalTooltip = currentDeleteButton.dataset.tooltip;

                // Visual feedback: Change button state
                currentDeleteButton.textContent = 'Deleting...';
                currentDeleteButton.classList.add('loading');
                currentDeleteButton.style.pointerEvents = 'none';
                currentDeleteButton.dataset.tooltip = 'Deleting election...';

                try {
                    const response = await fetch('../admin/delete_election_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'poll_id=' + encodeURIComponent(currentPollId)
                    });

                    const data = await response.json();

                    if (data.success) {
                        showNotification(data.message); // Show success notification
                        fetchAndRenderElections(); // Re-fetch to update table
                    } else {
                        showNotification('Error: ' + data.message, true); // Show error notification
                        // Restore button state on error
                        currentDeleteButton.textContent = originalButtonText;
                        currentDeleteButton.classList.remove('loading');
                        currentDeleteButton.style.pointerEvents = 'auto';
                        currentDeleteButton.dataset.tooltip = originalTooltip;
                    }
                } catch (error) {
                    console.error('Fetch error:', error);
                    showNotification('An unexpected error occurred: ' + error.message, true); // Show error notification
                    // Restore button state on network/parsing error
                    currentDeleteButton.textContent = originalButtonText;
                    currentDeleteButton.classList.remove('loading');
                    currentDeleteButton.style.pointerEvents = 'auto';
                    currentDeleteButton.dataset.tooltip = originalTooltip;
                }
            }
        });

        // Handle "No" click on custom dialog or clicking outside the dialog
        confirmNoBtn.addEventListener('click', function() {
            confirmDialog.style.display = 'none';
            confirmOverlay.style.display = 'none';
            currentDeleteButton = null;
            currentPollId = null;
        });

        confirmOverlay.addEventListener('click', function() {
            confirmDialog.style.display = 'none';
            confirmOverlay.style.display = 'none';
            currentDeleteButton = null;
            currentPollId = null;
        });

        // --- Pagination Event Listeners ---
        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                fetchAndRenderElections();
            }
        });

        nextPageBtn.addEventListener('click', () => {
            // totalPagesSpan.textContent holds the current total pages from the last fetch
            if (currentPage < parseInt(totalPagesSpan.textContent)) {
                currentPage++;
                fetchAndRenderElections();
            }
        });

        // --- Search Event Listeners ---
        electionSearchInput.addEventListener('input', () => {
            currentPage = 1; // Reset to first page on search
            fetchAndRenderElections();
        });

        clearSearchBtn.addEventListener('click', () => {
            electionSearchInput.value = ''; // Clear search input
            currentPage = 1; // Reset to first page
            fetchAndRenderElections();
        });

        // --- View Candidates Modal Logic ---
        electionTableBody.addEventListener('click', async function(event) {
            const viewCandidatesBtn = event.target.closest('.view-candidates-btn');
            if (!viewCandidatesBtn) return;

            event.preventDefault();
            const pollIdToView = viewCandidatesBtn.dataset.pollId;

            try {
                // Use the existing fetch_poll_id endpoint, but specifically for viewing candidates
                const response = await fetch(`${window.location.href}?view_candidates_poll_id=${pollIdToView}`);
                const result = await response.json();

                if (result.success) {
                    const election = result.election;
                    const candidates = result.candidates;

                    let candidatesHtml = `<h3>Candidates for "${election.election_name}"</h3>`;
                    if (candidates.length > 0) {
                        candidatesHtml += '<ul>';
                        candidates.forEach(candidate => {
                            candidatesHtml += `
                                <li>
                                    <span><strong>${candidate.candidate_name}</strong></span>
                                    <span>(Party: ${candidate.party || 'N/A'}, Symbol: ${candidate.candidate_symbol || 'N/A'})</span>
                                </li>`;
                        });
                        candidatesHtml += '</ul>';
                    } else {
                        candidatesHtml += '<p style="text-align: center; color: #777;">No candidates found for this election.</p>';
                    }
                    candidatesContent.innerHTML = candidatesHtml;
                    candidatesModal.style.display = 'flex'; // Use flex to center the modal
                } else {
                    showNotification('Error loading candidates: ' + result.message, true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showNotification('An unexpected error occurred while loading candidates: ' + error.message, true);
            }
        });

        // Close candidates modal when close button is clicked
        closeModalBtn.addEventListener('click', () => {
            candidatesModal.style.display = 'none';
        });

        // Close candidates modal when clicking outside the modal content
        candidatesModal.addEventListener('click', (event) => {
            if (event.target === candidatesModal) {
                candidatesModal.style.display = 'none';
            }
        });

        // --- NEW ELECTION WIZARD LOGIC ---
        const triggerNewElectionWizardBtn = document.getElementById('triggerNewElectionWizardBtn');
        const newElectionWizardOverlay = document.getElementById('newElectionWizardOverlay');
        const newWizardSteps = document.querySelectorAll('.new-wizard-step');
        const newPrevBtn = document.querySelector('.new-prev-btn');
        const newNextBtn = document.querySelector('.new-next-btn');
        const newSubmitBtn = document.querySelector('.new-submit-btn');
        const newWizardHeader = newElectionWizardOverlay.querySelector('.new-wizard-header h2');

        // Step 1 Elements
        const newElectionNameInput = document.getElementById('newElectionName');
        const newElectionTypeSelect = document.getElementById('newElectionType');
        const newStartDatetimeInput = document.getElementById('newStartDatetime');
        const newEndDatetimeInput = document.getElementById('newEndDatetime');
        const editPollIdInput = document.getElementById('editPollId'); // Hidden input for edit mode

        // Step 2 Elements
        const newCandidateNameInput = document.getElementById('newCandidateName');
        const newCandidatePartyInput = document.getElementById('newCandidateParty');
        const newCandidateSymbolInput = document.getElementById('newCandidateSymbol');
        const newAddCandidateBtn = document.getElementById('new-add-candidate-btn');
        const newCandidatesListDiv = document.getElementById('new-candidates-list');
        const newNoCandidatesMessage = document.getElementById('new-no-candidates-message');
        const candidateAddConfirmation = document.getElementById('candidateAddConfirmation'); // New: Confirmation message element

        let newCurrentStep = 0;
        let newCandidatesData = []; // Array to store candidate objects for the new wizard
        let editingCandidateIndex = null; // New: To store the index of the candidate being edited

        // Step 3 Elements
        const newReviewElectionName = document.getElementById('newReviewElectionName');
        const newReviewElectionType = document.getElementById('newReviewElectionType');
        const newReviewStartDatetime = document.getElementById('newReviewStartDatetime');
        const newReviewEndDatetime = document.getElementById('newReviewEndDatetime');
        const newReviewCandidatesList = document.getElementById('new-review-candidates-list');


        // Function to update new wizard display
        function updateNewWizardDisplay() {
            newWizardSteps.forEach((step, index) => {
                step.classList.toggle('active', index === newCurrentStep);
            });

            // Handle button visibility and text
            newPrevBtn.style.display = newCurrentStep === 0 ? 'none' : 'inline-block';
            newNextBtn.style.display = newCurrentStep === newWizardSteps.length - 1 ? 'none' : 'inline-block';
            newSubmitBtn.style.display = newCurrentStep === newWizardSteps.length - 1 ? 'inline-block' : 'none';

            if (editPollIdInput.value) { // If in edit mode
                newSubmitBtn.textContent = 'Update Election';
                newWizardHeader.textContent = 'Edit Election';
            } else {
                newSubmitBtn.textContent = 'Submit Election';
                newWizardHeader.textContent = 'Create New Election';
            }

            // Populate review section if on the last step
            if (newCurrentStep === newWizardSteps.length - 1) {
                populateNewReview();
            }
        }

        // Function to populate the new review section
        function populateNewReview() {
            newReviewElectionName.textContent = newElectionNameInput.value || 'N/A';
            newReviewElectionType.textContent = newElectionTypeSelect.value || 'N/A';
            newReviewStartDatetime.textContent = newStartDatetimeInput.value ? new Date(newStartDatetimeInput.value).toLocaleString() : 'N/A';
            newReviewEndDatetime.textContent = newEndDatetimeInput.value ? new Date(newEndDatetimeInput.value).toLocaleString() : 'N/A';

            newReviewCandidatesList.innerHTML = '';
            if (newCandidatesData.length > 0) {
                newCandidatesData.forEach(candidate => {
                    const listItem = document.createElement('li');
                    listItem.textContent = `${candidate.name} (Party: ${candidate.party || 'N/A'}, Symbol: ${candidate.symbol || 'N/A'})`;
                    newReviewCandidatesList.appendChild(listItem);
                });
            } else {
                const listItem = document.createElement('li');
                listItem.textContent = 'No candidates added.';
                newReviewCandidatesList.appendChild(listItem);
            }
        }

        // Function to add or update a candidate in the newCandidatesData array and update the UI
        function addNewCandidate() {
            const name = newCandidateNameInput.value.trim();
            const party = newCandidatePartyInput.value.trim();
            const symbol = newCandidateSymbolInput.value.trim();

            if (name) {
                if (editingCandidateIndex !== null) {
                    // Update existing candidate
                    newCandidatesData[editingCandidateIndex] = { name, party, symbol };
                } else {
                    // Add new candidate
                    newCandidatesData.push({ name, party, symbol });
                }
                renderNewCandidatesList(); // Re-render the list
                
                // Show confirmation message
                candidateAddConfirmation.textContent = editingCandidateIndex !== null ? 'Candidate Updated!' : 'Candidate Added!';
                candidateAddConfirmation.style.display = 'block';
                setTimeout(() => {
                    candidateAddConfirmation.style.display = 'none';
                }, 2000); // Hide after 2 seconds

                // Clear input fields and reset editing state
                newCandidateNameInput.value = '';
                newCandidatePartyInput.value = '';
                newCandidateSymbolInput.value = '';
                editingCandidateIndex = null; // Reset editing index
                updateAddCandidateButtonText(); // Update button text
            } else {
                showNotification('Candidate Name is required.', true);
            }
        }

        // New function: Populate candidate input fields for editing
        function editNewCandidate(index) {
            const candidateToEdit = newCandidatesData[index];
            newCandidateNameInput.value = candidateToEdit.name;
            newCandidatePartyInput.value = candidateToEdit.party;
            newCandidateSymbolInput.value = candidateToEdit.symbol;
            editingCandidateIndex = index; // Set the index of the candidate being edited
            updateAddCandidateButtonText(); // Change button text to "Update Candidate"
        }


        // Function to render the list of candidates in the new UI
        function renderNewCandidatesList() {
            newCandidatesListDiv.innerHTML = ''; // Clear existing list
            if (newCandidatesData.length === 0) {
                newNoCandidatesMessage.style.display = 'block';
            } else {
                newNoCandidatesMessage.style.display = 'none';
                newCandidatesData.forEach((candidate, index) => {
                    const candidateItem = document.createElement('div');
                    candidateItem.classList.add('new-candidate-item'); // Use new class
                    candidateItem.innerHTML = `
                        <span class="new-candidate-info">
                            <strong>${candidate.name}</strong> 
                            ${candidate.party ? `(Party: ${candidate.party})` : ''}
                            ${candidate.symbol ? `(Symbol: ${candidate.symbol})` : ''}
                        </span>
                        <div class="new-candidate-actions">
                            <button type="button" data-index="${index}" class="edit-candidate-btn">✏️</button>
                            <button type="button" data-index="${index}" class="new-remove-candidate-btn">🗑️</button>
                        </div>
                    `;
                    newCandidatesListDiv.appendChild(candidateItem);
                });
            }
        }

        // Function to remove a candidate from the new wizard
        function removeNewCandidate(index) {
            newCandidatesData.splice(index, 1);
            renderNewCandidatesList(); // Re-render the list
            if (editingCandidateIndex === index) { // If the candidate being removed was the one being edited
                editingCandidateIndex = null; // Reset editing state
                newCandidateNameInput.value = ''; // Clear inputs
                newCandidatePartyInput.value = '';
                newCandidateSymbolInput.value = '';
                updateAddCandidateButtonText(); // Reset button text
            } else if (editingCandidateIndex > index) { // If a candidate before the edited one was removed
                editingCandidateIndex--; // Adjust the editing index
            }
        }

        // New function: Update the text of the "Add Candidate" button
        function updateAddCandidateButtonText() {
            if (editingCandidateIndex !== null) {
                newAddCandidateBtn.textContent = 'Update Candidate';
            } else {
                newAddCandidateBtn.textContent = 'Add Candidate';
            }
        }


        // Reset wizard state for new creation
        function resetNewWizardForCreate() {
            editPollIdInput.value = ''; // Clear poll_id for new creation
            newElectionNameInput.value = '';
            newElectionTypeSelect.value = '';
            newStartDatetimeInput.value = '';
            newEndDatetimeInput.value = '';
            newCandidateNameInput.value = '';
            newCandidatePartyInput.value = '';
            newCandidateSymbolInput.value = '';
            newCandidatesData = [];
            editingCandidateIndex = null; // Ensure editing state is reset
            renderNewCandidatesList();
            newCurrentStep = 0;
            updateNewWizardDisplay();
            updateAddCandidateButtonText(); // Update candidate button text
            candidateAddConfirmation.style.display = 'none'; // Hide confirmation on reset
        }

        // Event listener for "Add New Election" button (trigger for the new wizard)
        triggerNewElectionWizardBtn.addEventListener('click', function() {
            resetNewWizardForCreate(); // Reset for new creation
            newElectionWizardOverlay.style.display = 'flex'; // Show the new wizard
        });

        // Event listener for "Edit" buttons in the table (delegated)
        electionTableBody.addEventListener('click', async function(event) {
            const editButton = event.target.closest('.edit-election-btn');
            if (!editButton) return;

            event.preventDefault();
            const pollIdToEdit = editButton.dataset.pollId;

            // Reset wizard first in case it was used for creation
            resetNewWizardForCreate();

            try {
                // Fetch existing election and candidate data
                const response = await fetch(`${window.location.href}?fetch_poll_id=${pollIdToEdit}`);
                const result = await response.json();

                if (result.success) {
                    // Corrected: Access election and candidates directly from result
                    const election = result.election;
                    const candidates = result.candidates;

                    // Populate form fields
                    editPollIdInput.value = election.poll_id;
                    newElectionNameInput.value = election.election_name;
                    newElectionTypeSelect.value = election.election_type;
                    
                    // Format datetime-local input
                    newStartDatetimeInput.value = election.start_datetime ? new Date(election.start_datetime).toISOString().slice(0, 16) : '';
                    newEndDatetimeInput.value = election.end_datetime ? new Date(election.end_datetime).toISOString().slice(0, 16) : '';

                    // Populate candidates
                    newCandidatesData = candidates.map(c => ({
                        name: c.candidate_name,
                        party: c.party,
                        symbol: c.candidate_symbol
                    }));
                    renderNewCandidatesList();

                    // Open wizard in edit mode
                    newElectionWizardOverlay.style.display = 'flex';
                    newCurrentStep = 0; // Start at first step
                    updateNewWizardDisplay(); // Update display to show "Edit Election"
                } else {
                    showNotification('Error loading election data: ' + result.message, true);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                showNotification('An unexpected error occurred while loading election data: ' + error.message, true);
            }
        });


        // Event listener for New Wizard Next button
        newNextBtn.addEventListener('click', function() {
            // Validate current step before moving next
            if (newCurrentStep === 0) { // Election Details validation
                if (!newElectionNameInput.value.trim() || !newElectionTypeSelect.value || !newStartDatetimeInput.value) {
                    showNotification('Please fill in all required election details.', true);
                    return;
                }
                 // Validate dates
                const startDate = new Date(newStartDatetimeInput.value);
                const endDate = newEndDatetimeInput.value ? new Date(newEndDatetimeInput.value) : null;
                
                if (endDate && endDate < startDate) {
                    showNotification('End Date & Time cannot be before Start Date & Time.', true);
                    return;
                }

            } else if (newCurrentStep === 1) { // Candidate Details validation
                if (newCandidatesData.length === 0) {
                    showNotification('Please add at least one candidate.', true);
                    return;
                }
            }

            if (newCurrentStep < newWizardSteps.length - 1) {
                newCurrentStep++;
                updateNewWizardDisplay();
            }
        });

        // Event listener for New Wizard Previous button
        newPrevBtn.addEventListener('click', function() {
            if (newCurrentStep > 0) {
                newCurrentStep--;
                updateNewWizardDisplay();
            }
        });

        // Event listener for New Wizard Add Candidate button
        newAddCandidateBtn.addEventListener('click', addNewCandidate);

        // Delegated event listener for remove candidate buttons in new wizard
        newCandidatesListDiv.addEventListener('click', function(event) {
            if (event.target.classList.contains('new-remove-candidate-btn')) { // Use new class for remove
                const indexToRemove = parseInt(event.target.dataset.index);
                removeNewCandidate(indexToRemove);
            } else if (event.target.classList.contains('edit-candidate-btn')) { // New: Edit button
                const indexToEdit = parseInt(event.target.dataset.index);
                editNewCandidate(indexToEdit);
            }
        });

        // Event listener for New Wizard Submit/Update button
        newSubmitBtn.addEventListener('click', async function() {
            // Collect all data
            const electionDetails = {
                poll_id: editPollIdInput.value || null, // Will be null for new creation
                election_name: newElectionNameInput.value,
                election_type: newElectionTypeSelect.value,
                start_datetime: newStartDatetimeInput.value,
                end_datetime: newEndDatetimeInput.value
            };

            // Ensure candidatesData is clean (no extra UI properties if any were added)
            const candidatesToSend = newCandidatesData.map(c => ({
                name: c.name,
                party: c.party,
                symbol: c.symbol
            }));

            try {
                // Send AJAX request to the same Admin_Election.php file
                const response = await fetch(window.location.href, { // Pointing to itself
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        election: electionDetails,
                        candidates: candidatesToSend
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showNotification(result.message);
                    newElectionWizardOverlay.style.display = 'none'; // Close wizard
                    fetchAndRenderElections(); // Re-fetch to update the table
                } else {
                    showNotification('Error: ' + result.message, true);
                }
            } catch (error) {
                console.error('Submission error:', error);
                showNotification('An unexpected error occurred during submission: ' + error.message, true);
            }
        });

        // Close new wizard if clicking outside the container (on the overlay)
        newElectionWizardOverlay.addEventListener('click', function(event) {
            if (event.target === newElectionWizardOverlay) {
                newElectionWizardOverlay.style.display = 'none';
                resetNewWizardForCreate(); // Reset state when closing via overlay click
            }
        });

        // Initial fetch of elections when the page loads
        fetchAndRenderElections();
    });
    </script>
</body>
</html>
