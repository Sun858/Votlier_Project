<?php
session_start();
require_once '../includes/security.sn.php';
require_once '../includes/election.sn.php';
require_once '../DatabaseConnection/config.php';
checkSessionTimeout();

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Handle deletion if requested
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
$existingCandidates = []; // Initialize an empty array for candidates

if (isset($_GET['edit_poll_id'])) {
    $editing = true;
    $editData = getElectionById($conn, $_GET['edit_poll_id']);
    if ($editData) {
        $existingCandidates = getCandidatesByPoll($conn, $editData['poll_id']);
    } else {
        // If edit_poll_id is invalid, redirect back to clear the state
        $_SESSION['message'] = "Invalid election ID for editing.";
        header("Location: admin_election.php");
        exit();
    }
}

$elections = getAllElections($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Election Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Election.css">
    <style>
        /* Basic styling for candidate entries to make them visually distinct */
        .candidate-entry {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .candidate-entry label {
            display: block; /* Make labels take full width */
            margin-bottom: 8px;
        }
        .candidate-entry input[type="text"] {
            width: calc(100% - 10px); /* Adjust width */
            padding: 8px;
            margin-top: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .remove-candidate {
            padding: 8px 12px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
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
            <p style="color: green;"> <?= htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?> </p>
        <?php endif; ?>

        <form action="../includes/save_election.php" method="post" id="electionForm">
            <h2>Election Details</h2>
            <?php if ($editing): ?>
                <label>Poll ID: <input type="text" value="<?= htmlspecialchars($editData['poll_id'] ?? '') ?>" readonly></label><br>
                <input type="hidden" name="poll_id" value="<?= htmlspecialchars($editData['poll_id'] ?? '') ?>">
            <?php else: ?>
                <p>Poll ID will be automatically generated upon creation.</p>
                <input type="hidden" name="poll_id" value="">
            <?php endif; ?>

            <label>Election Type: <input type="text" name="election_type" value="<?= htmlspecialchars($editing ? ($editData['election_type'] ?? '') : '') ?>" required></label><br>
            <label>Election Name: <input type="text" name="election_name" value="<?= htmlspecialchars($editing ? ($editData['election_name'] ?? '') : '') ?>" required></label><br>
            <label>Start Date/Time: <input type="datetime-local" name="start_datetime" value="<?= htmlspecialchars($editing ? ($editData['start_datetime'] ?? '') : '') ?>" required></label><br>
            <label>End Date/Time: <input type="datetime-local" name="end_datetime" value="<?= htmlspecialchars($editing ? ($editData['end_datetime'] ?? '') : '') ?>" required></label><br>

            <h2 style="margin-top: 20px;">Candidates</h2>
            <div id="candidatesContainer" data-existing-candidates='<?= json_encode($existingCandidates) ?>'>
                <p>Loading candidates... (If this message persists, JavaScript might be disabled or have errors.)</p>
            </div>

            <button type="button" id="addCandidateBtn" style="background-color: green; color: white; margin-top: 10px; padding: 10px 15px; border-radius: 5px; cursor: pointer;">+ Add Candidate</button><br>

            <button type="submit" style="margin-top: 20px; padding: 10px 15px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;"> <?= $editing ? 'Update Election' : 'Create Election' ?> </button>
        </form>

        <h2>Current Elections</h2>
        <?php if ($elections->num_rows > 0): ?>
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
                    <?php while ($row = $elections->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['poll_id']) ?></td>
                            <td><?= htmlspecialchars($row['election_type']) ?></td>
                            <td><?= htmlspecialchars($row['election_name']) ?></td>
                            <td><?= htmlspecialchars($row['start_datetime']) ?></td>
                            <td><?= htmlspecialchars($row['end_datetime']) ?></td>
                            <td>
                                <a href="?edit_poll_id=<?= urlencode($row['poll_id']) ?>">Edit</a>
                                <a href="?delete_poll_id=<?= urlencode($row['poll_id']) ?>" onclick="return confirm('Delete this election and all its candidates?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No elections available.</p>
        <?php endif; ?>
    </main>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const candidatesContainer = document.getElementById('candidatesContainer');
            const addCandidateBtn = document.getElementById('addCandidateBtn');
            const existingCandidatesJson = candidatesContainer.dataset.existingCandidates;
            const existingCandidates = JSON.parse(existingCandidatesJson);
            
            // candidateCounter needs to start at the highest existing index + 1
            // or 0 if no existing candidates. This ensures unique indices for new fields.
            let candidateCounter = existingCandidates.length > 0 ? existingCandidates.length : 0; 

            // --- Helper function to create a single candidate input block ---
            function createCandidateField(candidate = {}) {
                // Use candidateCounter for the unique index in the name attribute
                const currentIndex = candidateCounter++;

                const div = document.createElement('div');
                div.classList.add('candidate-entry'); // For styling
                div.innerHTML = `
                    <h3>Candidate <span class="candidate-number">${currentIndex + 1}</span></h3>
                    <label>Candidate Name: <input type="text" name="candidates[${currentIndex}][candidate_name]" value="${candidate.candidate_name || ''}" required></label><br>
                    <label>Party: <input type="text" name="candidates[${currentIndex}][party]" value="${candidate.party || ''}"></label><br>
                    <button type="button" class="remove-candidate" style="background-color: red; color: white; margin-top: 5px;">Remove Candidate</button>
                `;

                // Add event listener for the remove button
                const removeButton = div.querySelector('.remove-candidate');
                removeButton.addEventListener('click', () => {
                    div.remove();
                    // Re-index displayed numbers after removal for better UX
                    updateDisplayedCandidateNumbers();
                });

                return div;
            }

            // --- Helper function to update the displayed candidate numbers (e.g., Candidate 1, Candidate 2) ---
            function updateDisplayedCandidateNumbers() {
                const candidateEntries = candidatesContainer.querySelectorAll('.candidate-entry');
                candidateEntries.forEach((entry, idx) => {
                    const numberSpan = entry.querySelector('.candidate-number');
                    if (numberSpan) {
                        numberSpan.textContent = idx + 1;
                    }
                });
            }

            // --- INITIAL RENDERING LOGIC ON PAGE LOAD ---
            // Clear the "Loading candidates..." message or any initial placeholder
            candidatesContainer.innerHTML = '';

            if (existingCandidates && existingCandidates.length > 0) {
                // If editing and there are existing candidates, render them
                existingCandidates.forEach(candidate => {
                    candidatesContainer.appendChild(createCandidateField(candidate));
                });
            } else {
                // If not editing or no existing candidates, add one empty field
                candidatesContainer.appendChild(createCandidateField());
            }

            // Ensure displayed numbers are correct after initial load
            updateDisplayedCandidateNumbers();


            // --- EVENT LISTENER FOR 'ADD CANDIDATE' BUTTON ---
            addCandidateBtn.addEventListener('click', () => {
                candidatesContainer.appendChild(createCandidateField());
                updateDisplayedCandidateNumbers(); // Update numbers after adding
            });
        });
    </script>
</body>
</html>