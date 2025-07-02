<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    die("Access denied.");
}

error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) die("DB Error: Check 1) Docker containers 2) .env credentials");

// Transaction start
$conn->begin_transaction();
try {
    // Validate required fields
    $required = ['poll_id', 'election_type', 'election_name', 'start_datetime', 'end_datetime'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and assign election details
    $poll_id = (int)$_POST['poll_id'];
    $election_type = $conn->real_escape_string($_POST['election_type']);
    $election_name = $conn->real_escape_string($_POST['election_name']);
    $start_datetime = $conn->real_escape_string($_POST['start_datetime']);
    $end_datetime = $conn->real_escape_string($_POST['end_datetime']);

    // Insert election into database
    $stmt = $conn->prepare("INSERT INTO election (poll_id, election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $poll_id, $election_type, $election_name, $start_datetime, $end_datetime);
    if (!$stmt->execute()) {
        throw new Exception("Error saving election: " . $stmt->error);
    }
    $stmt->close();

    // Process candidates
    $candidates_saved = false;
    
    if (!isset($_POST['candidates']) || !is_array($_POST['candidates'])) {
        throw new Exception("No candidate data submitted");
    }

    foreach ($_POST['candidates'] as $index => $candidate) {
        // Validate required candidate fields
        if (empty($candidate['candidate_id']) || empty($candidate['candidate_name'])) {
            error_log("Skipping incomplete candidate at index $index");
            continue;
        }

        $candidate_id = (int)$candidate['candidate_id'];
        $candidate_name = $conn->real_escape_string($candidate['candidate_name']);
        $party = isset($candidate['party']) ? $conn->real_escape_string($candidate['party']) : '';
        $symbol = isset($candidate['symbol']) ? $conn->real_escape_string($candidate['symbol']) : '';
        $admin_id = (int)$_SESSION['admin_id'];
        $image_path = '';

        // Insert candidate into database
        $stmt = $conn->prepare("INSERT INTO candidates (candidate_id, poll_id, candidate_name, party, candidate_symbol, admin_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssi", $candidate_id, $poll_id, $candidate_name, $party, $symbol, $admin_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error saving candidate $candidate_name: " . $stmt->error);
        }
        $candidates_saved = true;
        $stmt->close();
    }

    if (!$candidates_saved) {
        throw new Exception("No valid candidates were processed");
    }

    $conn->commit();

    // Success message
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Election Created</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f4;
                text-align: center;
                padding-top: 100px;
            }
            .success-box {
                background-color: #fff;
                display: inline-block;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .success-box h2 {
                color: #28a745;
                margin-bottom: 20px;
            }
            .success-box button {
                padding: 10px 20px;
                background-color: #28a745;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
            }
        </style>
    </head>
    <body>
        <div class="success-box">
            <h2>✅ Election and candidates created successfully!</h2>
            <a href="dashboard.php">
                <button>Back to Dashboard</button>
            </a>
        </div>
    </body>
    </html>
    <?php

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(400);
    die("❌ Error: " . $e->getMessage());
}

$conn->close();
?>