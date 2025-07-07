<?php
session_start();
require_once '../includes/security.sn.php';
// This is the security page for rate limiting and timeout. 15Min is currently set
require_once '../includes/election.sn.php';
require_once '../DatabaseConnection/config.php';
checkSessionTimeout(); // Calling the function for the timeout, it redirects to login page and ends the session.

// I will need to move this stuff to the handler when I can, I feel this is bad for MVC...
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

if (isset($_GET['delete_poll_id'])) {
    if (deleteElection($conn, $_GET['delete_poll_id'])) {
        $_SESSION['message'] = "Election deleted successfully.";
    } else {
        $_SESSION['message'] = "Failed to delete election.";
    }
    header("Location: admin_election.php");
    exit();
}

$editing = false;
$editData = [];
$existingCandidates = [];

if (isset($_GET['edit_poll_id'])) {
    $editing = true;
    $editData = getElectionById($conn, $_GET['edit_poll_id']);
    if ($editData) {
        $editData['start_datetime'] = $editData['start_datetime'] ? date('Y-m-d\TH:i', strtotime($editData['start_datetime'])) : '';
        $editData['end_datetime'] = $editData['end_datetime'] ? date('Y-m-d\TH:i', strtotime($editData['end_datetime'])) : '';
        $existingCandidates = getCandidatesByPoll($conn, $editData['poll_id']);
    } else {
        $_SESSION['message'] = "Invalid election ID for editing.";
        header("Location: admin_election.php");
        exit();
    }
}

$allElectionsResult = getAllElections($conn);
$allElectionsForImport = [];
$allElectionsForTable = [];

if ($allElectionsResult->num_rows > 0) {
    while($row = $allElectionsResult->fetch_assoc()) {
        $allElectionsForImport[] = $row;
        $allElectionsForTable[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Election Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Election.css">
    <style>
        .form-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .form-container label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        .form-container input[type="text"],
        .form-container input[type="datetime-local"] {
            width: calc(100% - 20px);
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        .form-container select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .datetime-input-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .datetime-input-group label {
            flex: 0 0 120px;
            margin-right: 10px;
            margin-bottom: 0;
            font-weight: bold;
        }
        .datetime-input-group input[type="datetime-local"] {
            flex-grow: 1;
            margin-right: 10px;
            width: auto;
        }
        .datetime-input-group button {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background-color: #5cb85c;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
        }

        .candidate-entry {
            border: 1px solid #e0e0e0;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #fcfcfc;
            position: relative;
        }
        .candidate-entry h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        .candidate-entry input[type="text"] {
            width: calc(100% - 10px);
            margin-bottom: 10px;
        }
        .candidate-entry .remove-candidate {
            background-color: #dc3545;
            color: white;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 5px;
        }

        .import-candidates-section {
            border: 1px solid #b3d9ff;
            padding: 15px;
            margin-top: 20px;
            margin-bottom: 20px;
            background-color: #e6f2ff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        .import-candidates-section h3 {
            width: 100%;
            margin-top: 0;
            margin-bottom: 15px;
            color: #0056b3;
        }
        .import-candidates-section select {
            flex-grow: 1;
            margin-right: 10px;
            min-width: 200px;
            width: auto;
            margin-top: 0;
        }
        .import-candidates-section button {
            padding: 10px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            background-color: #007bff;
            color: white;
            font-size: 14px;
            flex-shrink: 0;
            margin-top: 0;
        }

        .action-buttons {
            margin-top: 20px;
            text-align: left;
        }
        .action-buttons button {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin-right: 10px;
        }
        .action-buttons button[type="submit"] {
            background-color: #28a745;
            color: white;
        }
        .action-buttons button#addCandidateBtn {
            background-color: #17a2b8;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .action-links a {
            margin-right: 10px;
            text-decoration: none;
            color: #007bff;
        }
        .action-links a:last-child {
            margin-right: 0;
        }
        .action-links a.delete {
            color: #dc3545;
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
        <h1>Manage Elections</h1>

        <?php if (isset($_SESSION['message'])): ?> 
            <p style="color: green; font-weight: bold;"> <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?> </p>
        <?php endif; ?>

        <div class="form-container">
            <h2><?= $editing ? 'Edit Election' : 'Create New Election' ?></h2>
            <form action="../includes/save_election.php" method="post" id="electionForm">
                <?php if ($editing): ?>
                    <label>Poll ID: <input type="text" value="<?= htmlspecialchars($editData['poll_id'] ?? '') ?>" readonly></label>
                    <input type="hidden" name="poll_id" value="<?= htmlspecialchars($editData['poll_id'] ?? '') ?>">
                <?php else: ?>
                    <p>Poll ID will be automatically generated upon creation.</p>
                    <input type="hidden" name="poll_id" value="">
                <?php endif; ?>

                <label>Election Type:
                    <input type="text" name="election_type" value="<?= htmlspecialchars($editing ? ($editData['election_type'] ?? '') : '') ?>" required>
                </label>
                <label>Election Name:
                    <input type="text" name="election_name" value="<?= htmlspecialchars($editing ? ($editData['election_name'] ?? '') : '') ?>" required>
                </label>

                <div class="datetime-input-group">
                    <label for="start_datetime_input">Start Date/Time:</label>
                    <input type="datetime-local" id="start_datetime_input" name="start_datetime" value="<?= htmlspecialchars($editing ? ($editData['start_datetime'] ?? '') : '') ?>" required>
                    <button type="button" id="setCurrentStartTimeBtn">Current Time</button>
                </div>
                
                <div class="datetime-input-group">
                    <label for="end_datetime_input">End Date/Time:</label>
                    <input type="datetime-local" id="end_datetime_input" name="end_datetime" value="<?= htmlspecialchars($editing ? ($editData['end_datetime'] ?? '') : '') ?>">
                </div>

                <h2 style="margin-top: 20px;">Candidates</h2>

                <?php if ($editing || count($allElectionsForImport) > 0): ?>
                    <div class="import-candidates-section">
                        <h3>Import Candidates from an Existing Election</h3>
                        <select id="importElectionSelect">
                            <option value="">-- Select an Election --</option>
                            <?php foreach ($allElectionsForImport as $electionOption): ?>
                                <?php if ($editing && $electionOption['poll_id'] == ($editData['poll_id'] ?? '')) continue; ?>
                                <option value="<?= htmlspecialchars($electionOption['poll_id']) ?>">
                                    <?= htmlspecialchars($electionOption['election_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="importCandidatesBtn">Import Candidates</button>
                    </div>
                <?php endif; ?>

                <div id="candidatesContainer" data-existing-candidates='<?= json_encode($existingCandidates) ?>'>
                    <p>Loading candidates... (If this message persists, JavaScript might be disabled or have errors.)</p>
                </div>

                <div class="action-buttons">
                    <button type="button" id="addCandidateBtn">+ Add Candidate</button>
                    <button type="submit"><?= $editing ? 'Update Election' : 'Create Election' ?></button>
                </div>
            </form>
        </div>

        <h2>Current Elections</h2>
        <?php if (!empty($allElectionsForTable)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Poll ID</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allElectionsForTable as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['poll_id']) ?></td>
                            <td><?= htmlspecialchars($row['election_type']) ?></td>
                            <td><?= htmlspecialchars($row['election_name']) ?></td>
                            <td><?= htmlspecialchars($row['start_datetime']) ?></td>
                            <td><?= htmlspecialchars($row['end_datetime'] ?? 'No End Date') ?></td>
                            <td class="action-links">
                                <a href="?edit_poll_id=<?= urlencode($row['poll_id']) ?>">Edit</a>
                                <a href="?delete_poll_id=<?= urlencode($row['poll_id']) ?>" onclick="return confirm('Delete this election and all its candidates?');" class="delete">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No elections available.</p>
        <?php endif; ?>
    </main>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <script>
        // WHAT HAVE I DONE HERE SANJAY HELP!!! sorry bro use gpt to understand this lmao it is used to dynamically shape the page with my functions, apparently i cant do it normally.
        document.addEventListener('DOMContentLoaded', () => {
            const candidatesContainer = document.getElementById('candidatesContainer');
            const addCandidateBtn = document.getElementById('addCandidateBtn');
            const importElectionSelect = document.getElementById('importElectionSelect');
            const importCandidatesBtn = document.getElementById('importCandidatesBtn');
            const startDatetimeInput = document.getElementById('start_datetime_input');
            const setCurrentStartTimeBtn = document.getElementById('setCurrentStartTimeBtn');
            const existingCandidatesJson = candidatesContainer.dataset.existingCandidates;
            const existingCandidates = existingCandidatesJson ? JSON.parse(existingCandidatesJson) : [];
            
            let candidateCounter = 0;

            function formatDateTime(date) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                const hours = String(date.getHours()).padStart(2, '0');
                const minutes = String(date.getMinutes()).padStart(2, '0');
                return `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            setCurrentStartTimeBtn.addEventListener('click', () => {
                startDatetimeInput.value = formatDateTime(new Date());
            });

            const isEditing = document.querySelector('input[name="poll_id"][value]').value !== '';
            if (!isEditing && startDatetimeInput.value === '') {
                 startDatetimeInput.value = formatDateTime(new Date());
            }

            function createCandidateField(candidate = {}) {
                const currentIndex = candidateCounter++;

                const div = document.createElement('div');
                div.classList.add('candidate-entry');
                div.innerHTML = `
                    <h3>Candidate <span class="candidate-number">${currentIndex + 1}</span></h3>
                    <label>Candidate Name: <input type="text" name="candidates[${currentIndex}][candidate_name]" value="${candidate.candidate_name || ''}" required></label><br>
                    <label>Party: <input type="text" name="candidates[${currentIndex}][party]" value="${candidate.party || ''}"></label><br>
                    <button type="button" class="remove-candidate">Remove Candidate</button>
                `;

                const removeButton = div.querySelector('.remove-candidate');
                removeButton.addEventListener('click', () => {
                    div.remove();
                    updateDisplayedCandidateNumbers();
                });

                return div;
            }

            function updateDisplayedCandidateNumbers() {
                const candidateEntries = candidatesContainer.querySelectorAll('.candidate-entry');
                candidateEntries.forEach((entry, idx) => {
                    const numberSpan = entry.querySelector('.candidate-number');
                    if (numberSpan) {
                        numberSpan.textContent = idx + 1;
                    }
                });
            }

            candidatesContainer.innerHTML = '';

            if (existingCandidates.length > 0) {
                existingCandidates.forEach(candidate => {
                    candidatesContainer.appendChild(createCandidateField(candidate));
                });
            } else {
                candidatesContainer.appendChild(createCandidateField());
            }

            candidateCounter = candidatesContainer.querySelectorAll('.candidate-entry').length;
            updateDisplayedCandidateNumbers();

            addCandidateBtn.addEventListener('click', () => {
                candidatesContainer.appendChild(createCandidateField());
                updateDisplayedCandidateNumbers();
            });

        importCandidatesBtn.addEventListener('click', async () => {
            const selectedPollId = importElectionSelect.value;
            if (!selectedPollId) {
                alert('Please select an election to import candidates from.');
                return;
            }

            try {
                const response = await fetch(`../includes/fetch_candidates.php?poll_id=${selectedPollId}`);
                const result = await response.json();

                if (!result.success) {
                    alert(result.message || 'Import failed.');
                    return;
                }

                const candidatesToImport = result.candidates;

                if (candidatesToImport.length > 0) {
                    candidatesToImport.forEach(candidate => {
                        const existingNames = Array.from(
                            candidatesContainer.querySelectorAll('input[name*="[candidate_name]"]')
                        ).map(input => input.value.toLowerCase());

                        if (!existingNames.includes(candidate.candidate_name.toLowerCase())) {
                            candidatesContainer.appendChild(createCandidateField(candidate));
                        } else {
                            console.log(`Skipping duplicate candidate: ${candidate.candidate_name}`);
                        }
                    });
                    updateDisplayedCandidateNumbers();
                    alert('Candidates imported successfully!');
                } else {
                    alert('No candidates found to import.');
                }
            } catch (error) {
                console.error('Error importing candidates:', error);
                alert('Failed to import candidates. Please try again.');
            }
        });
    });

    </script>
</body>
</html>