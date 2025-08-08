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

// --- Handle AJAX POST request for deleting election ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_poll_id'])) {
    $poll_id = filter_var($_POST['delete_poll_id'], FILTER_VALIDATE_INT);

    if ($poll_id === false) {
        sendJsonResponse(false, 'Invalid poll ID provided for deletion.');
    }

    $conn->begin_transaction();

    try {
        // Delete candidates first due to foreign key constraint
        $stmt_candidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
        $stmt_candidates->bind_param("i", $poll_id);
        if (!$stmt_candidates->execute()) {
            throw new Exception('Failed to delete candidates: ' . $stmt_candidates->error);
        }
        $stmt_candidates->close();

        // Then delete the election
        $stmt_election = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
        $stmt_election->bind_param("i", $poll_id);
        if (!$stmt_election->execute()) {
            throw new Exception('Failed to delete election: ' . $stmt_election->error);
        }
        $stmt_election->close();

        $conn->commit();
        sendJsonResponse(true, 'Election and its candidates deleted successfully!');

    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(false, 'Deletion failed: ' . $e->getMessage());
    } finally {
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

    // Status filter - Dynamically build SQL based on selected status
    if (!empty($election_status_filter)) {
        switch ($election_status_filter) {
            case 'Ongoing':
                $where_clauses[] = "(e.start_datetime <= NOW() AND (e.end_datetime IS NULL OR e.end_datetime >= NOW()))";
                break;
            case 'Upcoming':
                $where_clauses[] = "(e.start_datetime > NOW())";
                break;
            case 'Completed':
                $where_clauses[] = "(e.end_datetime IS NOT NULL AND e.end_datetime < NOW())";
                break;
        }
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

// Ensure the connection is closed after all PHP logic that uses it
if ($conn) {
    $conn->close();
}

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
    <link rel="stylesheet" href="../Assets/css/Admin_Election_2.css">
    
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

    <!-- Main Content Area -->
    <main class="main-content">
        <header class="main-header">
            <h1>Welcome to Voter Dashboard</h1>
            <p>Explore your data and manage your business efficiently</p>
        </header>

        <!-- New Election Button -->
        <div class="New-Election">
            <button id="triggerNewElectionWizardBtn" class="New-Election-Wizard-Button">
                <ion-icon name="add-circle-outline"></ion-icon>
                 New Election 
            </button>
        </div>

        <!-- Search and Filter Section -->
        <div class="search-container">
            <input type="text" id="electionSearchInput" placeholder="Search elections by name, type, or candidate...">
            <button id="clearSearchBtn" class="pagination-button" style="background-color: #6c757d;">
                Clear Search
            </button>
        </div>

        <!-- Election Management Table -->
        <table>
            <caption class="table-caption">
                Election Management Table
            </caption>

            <!-- Table Headers -->
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

            <!-- Table Body -->
            <tbody id="election-table-body">
                <!-- Data will be loaded via JavaScript AJAX -->
                <tr><td colspan="7" style="text-align:center; padding: 20px;">Loading elections...</td></tr>
            </tbody>
        </table>

        <!-- No Elections Message -->
        <div id="no-elections-message-container" style="display: none;">
            <p id="no-elections-message" style="text-align: center; color: #555; padding: 20px;">
                There are no current existing elections.
            </p>
        </div>

        <!-- Note Section  -->
        <div id="note-section" class="delete-section" style="margin-top: 20px; display: none;">
            <p>
                <strong>Note:</strong> 
                You can EDIT or DELETE an election using the buttons in the "Action" column.
            </p>
        </div>

        <!-- Pagination Controls -->
        <div class="pagination-container">
            <button id="prevPageBtn" class="pagination-button">
                Previous
            </button>
            <span class="page-info">Page <span id="currentPage">1</span>
             of 
            <span id="totalPages">1</span></span>
            <button id="nextPageBtn" class="pagination-button">
                Next
            </button>
        </div>
    </main>

    <!-- Custom Confirmation Dialog box for Delete of election and candidates in the table -->
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
    
    <!-- Include the main JavaScript file for Admin Election -->  
    <script src="../Assets/js/Admin_Election.js" defer></script>

</body>
</html>
