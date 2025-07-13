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

// --- Handle AJAX GET request for fetching election details for editing ---
// This block is kept in case you want to implement an "edit" flow later
// For now, it's not directly used by the single button on this page, but the wizard supports it.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_poll_id'])) {
    $poll_id = $_GET['fetch_poll_id'];

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

// End output buffering for the main HTML content, sending it to the browser.
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Election</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }

        .container {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 90%;
            box-sizing: border-box;
        }

        h1 {
            color: #333;
            font-size: 2.2em;
            margin-bottom: 30px;
            font-weight: 600;
        }

        #triggerNewElectionWizardBtn {
            display: inline-flex;
            align-items: center;
            padding: 15px 30px;
            background-color: rgb(101, 76, 175);
            color: white;
            text-decoration: none;
            border-radius: 75px;
            font-weight: 500;
            gap: 10px;
            border: none;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            box-shadow: 0 4px 15px rgba(101, 76, 175, 0.3);
        }

        #triggerNewElectionWizardBtn:hover {
            background-color: rgb(80, 60, 140);
            transform: translateY(-3px);
        }

        #triggerNewElectionWizardBtn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(101, 76, 175, 0.4);
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

        @keyframes fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
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
    </style>
</head>
<body>

    <div class="container">
        <h1>Election Management</h1>
        <!-- Button to trigger the New Election Wizard -->
        <button id="triggerNewElectionWizardBtn">
            <ion-icon name="add-circle-outline"></ion-icon>
            Create New Election
        </button>
    </div>

    <div id="notification"></div>

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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Notification element
        const notification = document.getElementById('notification');

        // Helper function to show notifications
        function showNotification(message, isError = false) {
            notification.textContent = message;
            notification.className = isError ? 'notification error' : 'notification';
            notification.style.display = 'block';

            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

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
                    // No need to re-fetch elections table as it's not present on this page
                    // If you re-integrate the table later, uncomment fetchAndRenderElections();
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

        // Initially render the candidate list (will be empty)
        renderNewCandidatesList();
    });
    </script>
</body>
</html>
