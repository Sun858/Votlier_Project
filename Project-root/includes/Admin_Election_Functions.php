<?php
// Admin_Election_Functions.php, This page contains all the functions for Admin_Election.php and related AJAX operations.

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = [])
{
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}


//Handles fetching a single election's details and its candidates.
function handleFetchElectionDetails(mysqli $conn, $poll_id)
{
    $stmt_election = $conn->prepare("SELECT poll_id, election_name, election_type, start_datetime, end_datetime FROM election WHERE poll_id = ?");
    $stmt_election->bind_param("i", $poll_id);
    $stmt_election->execute();
    $election_result = $stmt_election->get_result();
    $election_data = $election_result->fetch_assoc();
    $stmt_election->close();

    if (!$election_data) {
        sendJsonResponse(false, 'Election not found.');
    }

    $stmt_candidates = $conn->prepare("SELECT candidate_name, party FROM candidates WHERE poll_id = ?");
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

// Create and or update elections
function handleSaveElection(mysqli $conn, $data, $admin_id)
{
    if (!isset($data['election']) || !isset($data['candidates'])) {
        sendJsonResponse(false, 'Invalid data provided. Missing election or candidates data.');
    }

    $election = $data['election'];
    $candidates = $data['candidates'];
    $poll_id_to_update = filter_var($election['poll_id'] ?? null, FILTER_VALIDATE_INT);

    $election_name = trim($election['election_name'] ?? '');
    $election_type = trim($election['election_type'] ?? '');
    $start_datetime_str = trim($election['start_datetime'] ?? '');
    $end_datetime_str = trim($election['end_datetime'] ?? '');

    if (empty($election_name) || empty($election_type) || empty($start_datetime_str)) {
        sendJsonResponse(false, 'Election name, type, and start date are required.');
    }

    $start_datetime = new DateTime($start_datetime_str);
    if (!empty($end_datetime_str) && new DateTime($end_datetime_str) < $start_datetime) {
        sendJsonResponse(false, 'End Date & Time cannot be before Start Date & Time.');
    }

    $conn->begin_transaction();
    try {
        if ($poll_id_to_update) {
            $stmt = $conn->prepare("UPDATE election SET election_type = ?, election_name = ?, start_datetime = ?, end_datetime = ? WHERE poll_id = ?");
            if (!$stmt) throw new Exception('Prepare statement failed for election update: ' . $conn->error);
            $stmt->bind_param("ssssi", $election_type, $election_name, $start_datetime_str, $end_datetime_str, $poll_id_to_update);
            $stmt->execute();
            $stmt->close();

            $stmt_delete_candidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
            if (!$stmt_delete_candidates) throw new Exception('Prepare statement failed for deleting old candidates: ' . $conn->error);
            $stmt_delete_candidates->bind_param("i", $poll_id_to_update);
            $stmt_delete_candidates->execute();
            $stmt_delete_candidates->close();

            $actual_poll_id_for_candidates = $poll_id_to_update;
            $message_suffix = 'updated';
            // Log the update action
            logAdminAction($conn, $admin_id, 'Edit Election', "Updated election '{$election_name}' (ID: {$poll_id_to_update}).");
        } else {
            $stmt = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare statement failed for new election: ' . $conn->error);
            $stmt->bind_param("ssss", $election_type, $election_name, $start_datetime_str, $end_datetime_str);
            $stmt->execute();
            $actual_poll_id_for_candidates = $conn->insert_id;
            $stmt->close();
            $message_suffix = 'added';
            // Log the creation action
            logAdminAction($conn, $admin_id, 'Add Election', "Added new election '{$election_name}' (ID: {$new_poll_id}).");
        }

        if (!empty($candidates)) {
            $stmt_candidates = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, admin_id) VALUES (?, ?, ?, ?)");
            if (!$stmt_candidates) throw new Exception('Prepare statement failed for candidates: ' . $conn->error);
            foreach ($candidates as $candidate) {
                $candidate_name = trim($candidate['name'] ?? '');
                $party = trim($candidate['party'] ?? '');
                if (empty($candidate_name)) throw new Exception('Candidate name cannot be empty for an election.');
                $stmt_candidates->bind_param("issi", $actual_poll_id_for_candidates, $candidate_name, $party, $admin_id);
                $stmt_candidates->execute();
            }
            $stmt_candidates->close();
        }

        $conn->commit();
        sendJsonResponse(true, 'Election and candidates ' . $message_suffix . ' successfully!', ['poll_id' => $actual_poll_id_for_candidates]);
    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(false, 'Failed to ' . $message_suffix . ' election: ' . $e->getMessage());
    }
}

// Delete election obv
function handleDeleteElection(mysqli $conn, $poll_id, $admin_id)
{
    if ($poll_id === false) {
        sendJsonResponse(false, 'Invalid poll ID provided for deletion.');
    }

    $conn->begin_transaction();
    try {
        // Log the deletion action before sending the response
        logAdminAction($conn, $admin_id, 'Delete Election', "Deleted election with ID: {$poll_id}.");

        $stmt_candidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
        if (!$stmt_candidates) throw new Exception('Failed to prepare statement for candidate deletion: ' . $conn->error);
        $stmt_candidates->bind_param("i", $poll_id);
        if (!$stmt_candidates->execute()) throw new Exception('Failed to delete candidates: ' . $stmt_candidates->error);
        $stmt_candidates->close();

        $stmt_election = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
        if (!$stmt_election) throw new Exception('Failed to prepare statement for election deletion: ' . $conn->error);
        $stmt_election->bind_param("i", $poll_id);
        if (!$stmt_election->execute()) throw new Exception('Failed to delete election: ' . $stmt_election->error);
        $stmt_election->close();

        $conn->commit();
        sendJsonResponse(true, 'Election and its candidates deleted successfully!');

    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(false, 'Deletion failed: ' . $e->getMessage());
    }
}


// Search and filters. Futureproof UI.
function handleFetchTableData(mysqli $conn)
{
    $search_query = $_GET['search_query'] ?? '';
    $election_type_filter = $_GET['election_type_filter'] ?? '';
    $election_status_filter = $_GET['election_status_filter'] ?? '';
    $sort_column = $_GET['sort_column'] ?? 'poll_id';
    $sort_direction = $_GET['sort_direction'] ?? 'asc';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $limit_per_page = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
    $offset = ($page - 1) * $limit_per_page;

    $where_clauses = [];
    $bind_types = '';
    $bind_params = [];
    $join_clause = "";

    if (!empty($search_query)) {
        $search_term = '%' . $search_query . '%';
        $where_clauses[] = "(e.election_name LIKE ? OR e.election_type LIKE ? OR c.candidate_name LIKE ?)";
        $bind_types .= 'sss';
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
        $bind_params[] = $search_term;
        $join_clause = "LEFT JOIN candidates c ON e.poll_id = c.poll_id";
    }

    if (!empty($election_type_filter)) {
        $where_clauses[] = "e.election_type = ?";
        $bind_types .= 's';
        $bind_params[] = $election_type_filter;
    }

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

    $where_sql = count($where_clauses) > 0 ? "WHERE " . implode(" AND ", $where_clauses) : "";
    $group_by_sql = !empty($join_clause) ? "GROUP BY e.poll_id" : "";

    $count_query = "SELECT COUNT(DISTINCT e.poll_id) AS total_records FROM election e {$join_clause} {$where_sql}";
    $stmt_count = $conn->prepare($count_query);

    if ($stmt_count && !empty($bind_params)) {
        $references = array();
        foreach ($bind_params as $key => $value) {
            $references[$key] = &$bind_params[$key];
        }
        call_user_func_array([$stmt_count, 'bind_param'], array_merge([$bind_types], $references));
    }

    $stmt_count->execute();
    $total_records = $stmt_count->get_result()->fetch_assoc()['total_records'];
    $stmt_count->close();
    $total_pages = ceil($total_records / $limit_per_page);
    if ($page > $total_pages && $total_pages > 0) {
        $page = $total_pages;
        $offset = ($page - 1) * $limit_per_page;
    } elseif ($total_pages == 0) {
        $page = 1;
        $offset = 0;
    }

    $allowed_sort_columns = ['election_name', 'election_type', 'start_datetime', 'end_datetime', 'candidate_count', 'poll_id'];
    $sort_column = in_array($sort_column, $allowed_sort_columns) ? $sort_column : 'poll_id';
    $sort_direction = (strtolower($sort_direction) === 'desc') ? 'DESC' : 'ASC';

    $select_cols = "e.poll_id, e.election_name, e.election_type, e.start_datetime, e.end_datetime, (SELECT COUNT(c2.candidate_id) FROM candidates c2 WHERE c2.poll_id = e.poll_id) AS candidate_count";
    $query_sql = "SELECT {$select_cols} FROM election e {$join_clause} {$where_sql} {$group_by_sql} ORDER BY {$sort_column} {$sort_direction} LIMIT ? OFFSET ?";
    $stmt_data = $conn->prepare($query_sql);

    if (!$stmt_data) sendJsonResponse(false, 'Prepare statement failed for data fetch: ' . $conn->error);

    $data_bind_types = $bind_types . 'ii';
    $data_bind_params = array_merge($bind_params, [&$limit_per_page, &$offset]);
    call_user_func_array([$stmt_data, 'bind_param'], array_merge([$data_bind_types], $data_bind_params));
    $stmt_data->execute();
    $elections_data = $stmt_data->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_data->close();

    $timezone = new DateTimeZone('Australia/Melbourne');
    $now = new DateTime('now', $timezone);
    $current_time = $now->getTimestamp();
    foreach ($elections_data as &$row) {
        $start_timestamp = strtotime($row['start_datetime'] ?? '');
        $end_timestamp = strtotime($row['end_datetime'] ?? '');
        $display_status = 'Unknown';
        if ($end_timestamp && $end_timestamp < $current_time) $display_status = 'Completed';
        elseif ($start_timestamp && $start_timestamp > $current_time) $display_status = 'Upcoming';
        elseif ($start_timestamp && $end_timestamp && $start_timestamp <= $current_time && $end_timestamp >= $current_time) $display_status = 'Ongoing';
        $row['status'] = $display_status;
    }
    unset($row);

    sendJsonResponse(true, 'Elections data fetched successfully.', [
        'elections' => $elections_data,
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $page,
        'limit_per_page' => $limit_per_page
    ]);
}

// Filter data func
function getFilterData(mysqli $conn)
{
    $election_types_result = $conn->query("SELECT DISTINCT election_type FROM election ORDER BY election_type ASC");
    $election_types = [];
    if ($election_types_result) {
        while ($row = $election_types_result->fetch_assoc()) {
            $election_types[] = htmlspecialchars($row['election_type']);
        }
    }
    $possible_statuses = ['Ongoing', 'Upcoming', 'Completed'];
    sort($possible_statuses);
    return ['election_types' => $election_types, 'possible_statuses' => $possible_statuses];
}

// Got my old import candidates function, just added it here with a bit of the added javascript stuff sanjay did.
function handleImportCandidates(mysqli $conn, $dest_poll_id, $source_poll_id, $admin_id)
{
    // Fetch candidates from source poll
    $stmt = $conn->prepare("SELECT candidate_name, party FROM candidates WHERE poll_id = ? ORDER BY candidate_name ASC");
    if (!$stmt) {
        sendJsonResponse(false, 'Failed to prepare candidate fetch statement.');
    }
    $stmt->bind_param("i", $source_poll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidates = [];
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();

    if (empty($candidates)) {
        sendJsonResponse(false, 'No candidates found to import from source election.');
    }
    $stmt_insert = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, admin_id) VALUES (?, ?, ?, ?)");
    if (!$stmt_insert) {
        sendJsonResponse(false, 'Failed to prepare candidate insert statement.');
    }
    foreach ($candidates as $cand) {
        $stmt_insert->bind_param("issi", $dest_poll_id, $cand['candidate_name'], $cand['party'], $admin_id);
        $stmt_insert->execute();
    }
    $stmt_insert->close();

    logAdminAction($conn, $admin_id, 'Import Candidates', "Imported candidates from poll {$source_poll_id} to {$dest_poll_id}.");
    sendJsonResponse(true, 'Candidates imported successfully.');
}

// Ends an election immediately.
function handleManualEndElection(mysqli $conn, $poll_id, $admin_id)
{
    $now = date("Y-m-d H:i:s");
    $stmt = $conn->prepare("UPDATE election SET end_datetime = ? WHERE poll_id = ?");
    if (!$stmt) {
        sendJsonResponse(false, 'Failed to prepare end election statement.');
    }
    $stmt->bind_param("si", $now, $poll_id);
    if (!$stmt->execute()) {
        $stmt->close();
        sendJsonResponse(false, 'Failed to end election.');
    }
    $stmt->close();
    logAdminAction($conn, $admin_id, 'End Election', "Election {$poll_id} ended manually at {$now}.");
    sendJsonResponse(true, 'Election ended successfully.');
}

// Re-open election by having it set the end time to null.
function handleManualReopenElection(mysqli $conn, $poll_id, $admin_id)
{
    $stmt = $conn->prepare("UPDATE election SET end_datetime = NULL WHERE poll_id = ?");
    if (!$stmt) {
        sendJsonResponse(false, 'Failed to prepare reopen election statement.');
    }
    $stmt->bind_param("i", $poll_id);
    if (!$stmt->execute()) {
        $stmt->close();
        sendJsonResponse(false, 'Failed to reopen election.');
    }
    $stmt->close();
    logAdminAction($conn, $admin_id, 'Reopen Election', "Election {$poll_id} reopened (end_datetime cleared).");
    sendJsonResponse(true, 'Election re-opened successfully.');
}
