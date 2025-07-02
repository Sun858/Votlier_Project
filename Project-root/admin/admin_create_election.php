<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    die("Access denied.");
}

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) die("DB Error: Check 1) Docker containers 2) .env credentials");

$poll_id = $_GET['poll_id'] ?? null;
$election = null;
$candidates = [];

if ($poll_id) {
    $stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $election = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ?");
    $stmt->bind_param("i", $poll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $poll_id ? "Edit Election: " . htmlspecialchars($election['election_name']) : "Create New Election" ?></title>
    <style>
        .candidate-fields {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 10px;
            background: #f9f9f9;
        }
        .remove-candidate {
            color: red;
            cursor: pointer;
            background-color: #f8d7da;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
            border: none;
        }
        .button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        #confirmModal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        #confirmModal .modal-content {
            background: white;
            margin: 15% auto;
            padding: 20px;
            width: 300px;
            text-align: center;
            border-radius: 10px;
        }
        #confirmModal button {
            margin: 10px;
            padding: 10px 15px;
            cursor: pointer;
        }
        label {
            display: block;
            margin: 8px 0;
        }
        input, select {
            padding: 8px;
            margin: 4px 0;
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div style="margin: 10px;">
        <a href="../pages/admin_election.php">⬅️ Back</a>
    </div>
    <h2><?= $poll_id ? "✏️ Edit Election: " . htmlspecialchars($election['election_name']) : "🗳️ Create New Election" ?></h2>

    <form id="electionForm" action="<?= $poll_id ? 'election_updates_saved.php' : 'save_election.php' ?>" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="poll_id" value="<?= htmlspecialchars($election['poll_id'] ?? '') ?>">
        
        <fieldset style="margin-bottom: 20px; padding: 20px;">
            <legend><strong>Election Details</strong></legend>
            
            <label>Poll ID: 
                <input type="text" name="poll_id" required 
                       value="<?= htmlspecialchars($election['poll_id'] ?? '') ?>" 
                       <?= $poll_id ? "readonly" : "" ?>>
            </label>

            <label>Election Type: 
                <select name="election_type" required>
                    <option value="Select" disabled <?= !isset($election['election_type']) ? "selected" : "" ?>>Select</option>
                    <option value="Local" <?= (isset($election['election_type']) && $election['election_type'] === "Local") ? "selected" : "" ?>>Local</option>
                    <option value="State" <?= (isset($election['election_type']) && $election['election_type'] === "State") ? "selected" : "" ?>>State</option>
                    <option value="Federal" <?= (isset($election['election_type']) && $election['election_type'] === "Federal") ? "selected" : "" ?>>Federal</option>
                </select>
            </label>

            <label>Election Name: 
                <input type="text" name="election_name" required 
                       value="<?= htmlspecialchars($election['election_name'] ?? '') ?>">
            </label>

            <label>Start Date & Time: 
                <input type="datetime-local" name="start_datetime" required 
                       value="<?= isset($election['start_datetime']) ? date('Y-m-d\TH:i', strtotime($election['start_datetime'])) : '' ?>">
            </label>

            <label>End Date & Time: 
                <input type="datetime-local" name="end_datetime" required 
                       value="<?= isset($election['end_datetime']) ? date('Y-m-d\TH:i', strtotime($election['end_datetime'])) : '' ?>">
            </label>
        </fieldset>

        <fieldset id="candidates-fieldset" style="margin-bottom: 20px; padding: 20px;">
            <legend><strong>Candidates</strong></legend>
            <?php foreach ($candidates as $index => $candidate): ?>
                <div class="candidate-fields" id="candidate-<?= $index + 1 ?>">
                    <h4>Candidate <?= $index + 1 ?></h4>
                    <label>Candidate ID: 
                        <input type="text" name="candidates[<?= $index ?>][candidate_id]" required 
                               value="<?= htmlspecialchars($candidate['candidate_id']) ?>">
                    </label>
                    <label>Candidate Name: 
                        <input type="text" name="candidates[<?= $index ?>][candidate_name]" required 
                               value="<?= htmlspecialchars($candidate['candidate_name']) ?>">
                    </label>
                    <label>Party: 
                        <input type="text" name="candidates[<?= $index ?>][party]" 
                               value="<?= htmlspecialchars($candidate['party']) ?>">
                    </label>
                    <label>Party Symbol: 
                        <input type="text" name="candidates[<?= $index ?>][symbol]" 
                               value="<?= htmlspecialchars($candidate['candidate_symbol']) ?>">
                    </label>
                    <button type="button" class="remove-candidate" onclick="removeCandidate(<?= $index + 1 ?>)">Remove Candidate</button>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="button" id="add-candidate" class="button">➕ Add Candidate</button><br><br>
        <input type="hidden" name="candidate_count" id="candidateCountField" value="<?= count($candidates) ?>">
        <button type="submit" id="submit-button" class="button"><?= $poll_id ? "Update Election" : "Create Election" ?></button>
    </form>

    <!-- Confirmation Modal -->
    <div id="confirmModal">
        <div class="modal-content">
            <p>You have unsaved changes. What would you like to do?</p>
            <button id="applyChanges">Apply Changes✅</button>
            <button id="discardChanges">Discard Changes❌</button>
        </div>
    </div>

    <script>
        let candidateCount = <?= count($candidates) ?>;
        let formChanged = false;

        const form = document.getElementById('electionForm');
        const confirmModal = document.getElementById('confirmModal');
        const candidatesFieldset = document.getElementById('candidates-fieldset');

        form.addEventListener('input', () => formChanged = true);
        form.addEventListener('change', () => formChanged = true);

        window.addEventListener('beforeunload', function (e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', function (e) {
            if (formChanged) {
                e.preventDefault();
                confirmModal.style.display = 'block';
            }
        });

        document.getElementById('applyChanges').onclick = function () {
            confirmModal.style.display = 'none';
            formChanged = false;
            form.submit();
        };

        document.getElementById('discardChanges').onclick = function () {
            confirmModal.style.display = 'none';
            formChanged = false;
            window.location.href = "dashboard.php";
        };

        function addCandidate() {
            const newIndex = candidateCount;
            candidateCount++;
            document.getElementById('candidateCountField').value = candidateCount;
            
            const div = document.createElement('div');
            div.className = 'candidate-fields';
            div.id = `candidate-${candidateCount}`;
            
            div.innerHTML = `
                <h4>Candidate ${candidateCount}</h4>
                <label>Candidate ID: 
                    <input type="text" name="candidates[${newIndex}][candidate_id]" required>
                </label>
                <label>Candidate Name: 
                    <input type="text" name="candidates[${newIndex}][candidate_name]" required>
                </label>
                <label>Party: 
                    <input type="text" name="candidates[${newIndex}][party]">
                </label>
                <label>Party Symbol: 
                    <input type="text" name="candidates[${newIndex}][symbol]">
                </label>
                <button type="button" class="remove-candidate">Remove Candidate</button>
            `;

            div.querySelector('.remove-candidate').addEventListener('click', function() {
                div.remove();
                updateCandidateIndexes();
            });

            candidatesFieldset.appendChild(div);
        }

        function removeCandidate(index) {
            const div = document.getElementById(`candidate-${index}`);
            if (div) {
                div.remove();
                updateCandidateIndexes();
            }
        }

        function updateCandidateIndexes() {
            const candidateDivs = candidatesFieldset.querySelectorAll('.candidate-fields');
            candidateCount = candidateDivs.length;
            document.getElementById('candidateCountField').value = candidateCount;

            candidateDivs.forEach((div, index) => {
                const newIndex = index;
                div.querySelector('h4').textContent = `Candidate ${index + 1}`;
                
                // Update all input names
                div.querySelectorAll('input').forEach(input => {
                    const fieldMatch = input.name.match(/\[(\w+)\]$/);
                    if (fieldMatch) {
                        const fieldName = fieldMatch[1];
                        input.name = `candidates[${newIndex}][${fieldName}]`;
                    }
                });
            });
        }

        // Initialize existing remove buttons
        document.querySelectorAll('.remove-candidate').forEach(btn => {
            btn.addEventListener('click', function() {
                const div = this.closest('.candidate-fields');
                if (div) {
                    div.remove();
                    updateCandidateIndexes();
                }
            });
        });

        document.getElementById('add-candidate').addEventListener('click', addCandidate);
    </script>
</body>
</html>