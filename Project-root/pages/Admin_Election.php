<?php
ob_start();
session_start();
header('Content-Type: text/html; charset=utf-8');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include the file with all the functions
require_once '../includes/admin_election.sn.php';
require_once '../DatabaseConnection/config.php'; // New line to include the config file
require_once '../includes/security.sn.php';

// Check session timeout and admin login
if (!isset($_SESSION["admin_id"])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        sendJsonResponse(false, 'Unauthorized access. Please log in.');
    } else {
        header("Location: ../pages/login.php");
        exit();
    }
}
$admin_id = $_SESSION['admin_id'] ?? null;

// Handle AJAX GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['fetch_poll_id']) || isset($_GET['view_candidates_poll_id'])) {
        $poll_id = $_GET['fetch_poll_id'] ?? $_GET['view_candidates_poll_id'];
        handleFetchElectionDetails($conn, $poll_id);
    } elseif (isset($_GET['fetch_table_data'])) {
        handleFetchTableData($conn);
    }
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if it's a JSON POST (for create/update) or form data (for delete)
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        handleSaveElection($conn, $data, $admin_id);
    } elseif (isset($_POST['delete_poll_id'])) {
        $poll_id = filter_var($_POST['delete_poll_id'], FILTER_VALIDATE_INT);
        handleDeleteElection($conn, $poll_id, $admin_id);
    }
}

// Data Fetching for the Page.
$filter_data = getFilterData($conn);
$election_types = $filter_data['election_types'];
$possible_statuses = $filter_data['possible_statuses'];

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
                <li><a href="Admin_FAQ.php">
                        <span class="icon"><ion-icon name="help-outline"></ion-icon></span>
                        <span class="text">Manage FAQ</span>
                </a></li>
                <li><a href="Admin_Documentation.php">
                    <span class="icon"><ion-icon name="document-text"></ion-icon></span>
                    <span class="text">Manage Documentation</span>
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

        <div class="New-Election">
            <button id="triggerNewElectionWizardBtn" class="New-Election-Wizard-Button">
                <ion-icon name="add-circle-outline"></ion-icon>
                    New Election
            </button>
        </div>

        <div class="search-container">
            <input type="text" id="electionSearchInput" placeholder="Search elections by name, type, or candidate...">
            <button id="clearSearchBtn" class="pagination-button" style="background-color: #6c757d;">
                Clear Search
            </button>
        </div>

        <table>
            <caption class="table-caption">
                Election Management Table
            </caption>

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
                <tr><td colspan="7" style="text-align:center; padding: 20px;">Loading elections...</td></tr>
            </tbody>
        </table>

        <div id="no-elections-message-container" style="display: none;">
            <p id="no-elections-message" style="text-align: center; color: #555; padding: 20px;">
                There are no current existing elections.
            </p>
        </div>

        <div id="note-section" class="delete-section" style="margin-top: 20px; display: none;">
            <p>
                <strong>Note:</strong>
                You can EDIT or DELETE an election using the buttons in the "Action" column.
            </p>
        </div>

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

    <div id="custom-confirm-overlay"></div>
    <div id="custom-confirm-dialog">
        <p id="confirm-message"></p>
        <div style="display: flex; justify-content: center; gap: 10px;">
            <button id="confirm-yes">Yes, Delete</button>
            <button id="confirm-no">Cancel</button>
        </div>
    </div>

    <div id="notification"></div>

    <div id="candidatesModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="candidatesContent">
                </div>
        </div>
    </div>

    <div id="newElectionWizardOverlay" class="new-wizard-overlay">
        <div class="new-wizard-container">
            <div class="new-wizard-header">
                <h2>Create New Election</h2>
            </div>

            <input type="hidden" id="editPollId">

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
                <button type="button" id="new-add-candidate-btn">Add Candidate</button>
                <div id="candidateAddConfirmation" style="color: green; margin-top: 10px; font-weight: bold; display: none;">Candidate Added!</div>


                <h4>Current Candidates:</h4>
                <div id="new-candidates-list">
                    <p id="new-no-candidates-message" style="text-align: center; color: #777;">No candidates added yet.</p>
                </div>
            </div>

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

    <script src="../Assets/js/Admin_Election.js" defer></script>

</body>
</html>