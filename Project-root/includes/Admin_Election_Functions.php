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

/**
 * Handles fetching a single election's details and its candidates.
 * Used for both viewing candidates and populating the edit form.
 *
 * mysqli $conn The database connection object.
 *  $poll_id The ID of the election to fetch.
 */

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

    $stmt_candidates = $conn->prepare("SELECT candidate_id, candidate_name, party FROM candidates WHERE poll_id = ?");
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

/**
 * Handles the creation or update of an election and its candidates.
 *
 *  mysqli $conn The database connection object.
 *  $data The JSON decoded data from the AJAX request.
 *  $admin_id The ID of the logged-in admin.
 */
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
            // Update existing election
            $stmt = $conn->prepare("UPDATE election SET election_type = ?, election_name = ?, start_datetime = ?, end_datetime = ? WHERE poll_id = ?");
            if (!$stmt) throw new Exception('Prepare statement failed for election update: ' . $conn->error);
            $stmt->bind_param("ssssi", $election_type, $election_name, $start_datetime_str, $end_datetime_str, $poll_id_to_update);
            $stmt->execute();
            $stmt->close();

            $actual_poll_id_for_candidates = $poll_id_to_update;
            $message_suffix = 'updated';
            // Log the update action
            logAdminAction($conn, $admin_id, 'Edit Election', "Updated election '{$election_name}'.");
        } else {
            // Insert new election
            $stmt = $conn->prepare("INSERT INTO election (election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?)");
            if (!$stmt) throw new Exception('Prepare statement failed for new election: ' . $conn->error);
            $stmt->bind_param("ssss", $election_type, $election_name, $start_datetime_str, $end_datetime_str);
            $stmt->execute();
            $actual_poll_id_for_candidates = $conn->insert_id;
            $stmt->close();
            $message_suffix = 'added';
            // Log the creation action
            logAdminAction($conn, $admin_id, 'Add Election', "Added new election '{$election_name}'.");
        }

        // --- Begin Safe Candidate Update Logic ---
        // Fetch existing candidates for this poll
        $stmt_fetch_candidates = $conn->prepare("SELECT candidate_id, candidate_name, party FROM candidates WHERE poll_id = ?");
        $stmt_fetch_candidates->bind_param("i", $actual_poll_id_for_candidates);
        $stmt_fetch_candidates->execute();
        $result_existing = $stmt_fetch_candidates->get_result();
        $existing_candidates = [];
        while ($row = $result_existing->fetch_assoc()) {
            $existing_candidates[$row['candidate_id']] = $row;
        }
        $stmt_fetch_candidates->close();

        // Separate out submitted candidates into 'existing' and 'new'
        $submitted_candidates_by_id = [];
        $new_candidates = [];
        foreach ($candidates as $candidate) {
            if (!empty($candidate['candidate_id'])) {
                $submitted_candidates_by_id[$candidate['candidate_id']] = $candidate;
            } else {
                $new_candidates[] = $candidate;
            }
        }

        // 1. Update existing candidates if changed
        foreach ($submitted_candidates_by_id as $candidate_id => $submitted) {
            if (isset($existing_candidates[$candidate_id])) {
                $old = $existing_candidates[$candidate_id];
                $new_name = trim($submitted['name'] ?? '');
                $new_party = trim($submitted['party'] ?? '');

                // Only update if changed
                if ($old['candidate_name'] !== $new_name || $old['party'] !== $new_party) {
                    // Duplicate check for update (exclude self so it doesnt add a new candidate while ALSO updating it...)
                    $stmt_dup = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE poll_id = ? AND candidate_name = ? AND party = ? AND candidate_id != ?");
                    $stmt_dup->bind_param("issi", $actual_poll_id_for_candidates, $new_name, $new_party, $candidate_id);
                    $stmt_dup->execute();
                    $dup_count = $stmt_dup->get_result()->fetch_row()[0];
                    $stmt_dup->close();
                    if ($dup_count > 0) throw new Exception("Another candidate with the same name and party already exists in this election.");

                    $stmt_update = $conn->prepare("UPDATE candidates SET candidate_name = ?, party = ? WHERE candidate_id = ?");
                    if (!$stmt_update) throw new Exception('Prepare statement failed for updating candidate: ' . $conn->error);
                    $stmt_update->bind_param("ssi", $new_name, $new_party, $candidate_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }
                // Mark as processed
                unset($existing_candidates[$candidate_id]);
            }
        }

        // 2. Delete existing candidates not in submitted list, IF they have no votes
        foreach ($existing_candidates as $candidate_id => $candidate) {
            // Check for votes in ballot table
            $stmt_votes = $conn->prepare("SELECT COUNT(*) AS vote_count FROM ballot WHERE candidate_id = ?");
            $stmt_votes->bind_param("i", $candidate_id);
            $stmt_votes->execute();
            $vote_result = $stmt_votes->get_result();
            $vote_count = $vote_result->fetch_assoc()['vote_count'] ?? 0;
            $stmt_votes->close();

            if ($vote_count > 0) {
                // Cannot delete, votes exist
                throw new Exception("Cannot remove candidate '{$candidate['candidate_name']}' as they already have votes.");
            } else {
                // Safe to delete
                $stmt_del = $conn->prepare("DELETE FROM candidates WHERE candidate_id = ?");
                if (!$stmt_del) throw new Exception('Prepare statement failed for deleting candidate: ' . $conn->error);
                $stmt_del->bind_param("i", $candidate_id);
                $stmt_del->execute();
                $stmt_del->close();
            }
        }

        // 3. Insert new candidates
        if (!empty($new_candidates)) {
            $stmt_new = $conn->prepare("INSERT INTO candidates (poll_id, candidate_name, party, admin_id) VALUES (?, ?, ?, ?)");
            if (!$stmt_new) throw new Exception('Prepare statement failed for new candidates: ' . $conn->error);
            foreach ($new_candidates as $candidate) {
                $candidate_name = trim($candidate['name'] ?? '');
                $party = trim($candidate['party'] ?? '');
                if (empty($candidate_name)) throw new Exception('Candidate name cannot be empty for an election.');

                // Duplicate check for new candidate inside the backend, also in the front-end js code. I will need to seperate php code inside the PAGE into a controller.
                $stmt_dup = $conn->prepare("SELECT COUNT(*) FROM candidates WHERE poll_id = ? AND candidate_name = ? AND party = ?");
                $stmt_dup->bind_param("iss", $actual_poll_id_for_candidates, $candidate_name, $party);
                $stmt_dup->execute();
                $dup_count = $stmt_dup->get_result()->fetch_row()[0];
                $stmt_dup->close();
                if ($dup_count > 0) throw new Exception("Candidate '{$candidate_name}' with party '{$party}' already exists in this election.");

                $stmt_new->bind_param("issi", $actual_poll_id_for_candidates, $candidate_name, $party, $admin_id);
                $stmt_new->execute();
            }
            $stmt_new->close();
        }
        // --- End Safe Candidate Update Logic ---

        $conn->commit();
        sendJsonResponse(true, 'Election and candidates ' . $message_suffix . ' successfully!', ['poll_id' => $actual_poll_id_for_candidates]);
    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(false, 'Failed to ' . ($message_suffix ?? 'process') . ' election: ' . $e->getMessage());
    }
}

/**
 * Handles the deletion of an election and its associated candidates.
 *
 *  mysqli $conn The database connection object.
 *  int $poll_id The ID of the election to delete.
 */
function handleDeleteElection(mysqli $conn, $poll_id, $admin_id)
{
    if ($poll_id === false) {
        sendJsonResponse(false, 'Invalid poll ID provided for deletion.');
    }

    $conn->begin_transaction();
    try {

        // Step 1: Retrieve the election name before deleting it
        $stmt_get_name = $conn->prepare("SELECT election_name FROM election WHERE poll_id = ?");
        $stmt_get_name->bind_param("i", $poll_id);
        $stmt_get_name->execute();
        $election_name_result = $stmt_get_name->get_result()->fetch_assoc();
        $election_name = $election_name_result['election_name'] ?? 'Unknown Election';
        $stmt_get_name->close();

        // Step 2: Delete candidates and election
        $stmt_candidates = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
        if (!$stmt_candidates) throw new Exception('Failed to prepare statement for candidate deletion: ' . $conn->error);
        $stmt_candidates->bind_param("i", $poll_id);
        if (!$stmt_candidates->execute()) throw new Exception('Failed to delete candidates: ' . $stmt_candidates->error);
        $stmt_candidates->close();

        // Step 3: Delete the election itself
        $stmt_election = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
        if (!$stmt_election) throw new Exception('Failed to prepare statement for election deletion: ' . $conn->error);
        $stmt_election->bind_param("i", $poll_id);
        if (!$stmt_election->execute()) throw new Exception('Failed to delete election: ' . $stmt_election->error);
        $stmt_election->close();

        // Step 4: Log the deletion action with the retrieved name
        logAdminAction($conn, $admin_id, 'Delete Election', "Deleted election '{$election_name}'.");

        $conn->commit();
        sendJsonResponse(true, 'Election and its candidates deleted successfully!');

    } catch (Exception $e) {
        $conn->rollback();
        sendJsonResponse(false, 'Deletion failed: ' . $e->getMessage());
    }
}


/**
 * Fetches data for the election management table, including search, filter, sort, and pagination.
 *
 * mysqli $conn The database connection object.
 */
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

/**
 * Fetches distinct election types and possible statuses for dropdown filters.
 *
 *  mysqli $conn The database connection object.
 * An array containing 'election_types' and 'possible_statuses'.
 */
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
