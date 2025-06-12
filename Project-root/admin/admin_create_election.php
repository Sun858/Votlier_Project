<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administration') {
    die("Access denied.");
}

$conn = new mysqli("localhost", "root", "", "voting_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$poll_id = $_GET['poll_id'] ?? null;
$election = null;
$candidates = [];

if ($poll_id) {
    $stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
    $stmt->bind_param("s", $poll_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $election = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ?");
    $stmt->bind_param("s", $poll_id);
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
        }
        .remove-candidate {
            color: red;
            cursor: pointer;
            background-color: #f8d7da;
            padding: 5px;
            border-radius: 5px;
            display: inline-block;
            margin-top: 10px;
        }
        .button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
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
        .logout-button {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background-color 0.3s ease;
        }

        .logout-button:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <h2><?= $poll_id ? "✏️ Edit Election: " . htmlspecialchars($election['election_name']) : "🗳️ Create New Election" ?></h2>

    <!-- Logout Button -->
    <a href="?logout=true" class="logout-button">Logout</a>

    <form id="electionForm" action="<?= $poll_id ? 'election_updates_saved.php' : 'save_election.php' ?>" method="POST" enctype="multipart/form-data">
        <fieldset>
            <legend><strong>Election Details</strong></legend>
            <label>Poll ID: <input type="text" name="poll_id" required value="<?= htmlspecialchars($election['poll_id'] ?? '') ?>" <?= $poll_id ? "readonly" : "" ?>></label><br><br>

            <label>Election Type: 
                <select name="election_type" required>
                    <option value="Select" disabled <?= !isset($election['election_type']) ? "selected" : "" ?>>Select</option>
                    <option value="Local" <?= (isset($election['election_type']) && $election['election_type'] === "Local") ? "selected" : "" ?>>Local</option>
                    <option value="State" <?= (isset($election['election_type']) && $election['election_type'] === "State") ? "selected" : "" ?>>State</option>
                    <option value="Federal" <?= (isset($election['election_type']) && $election['election_type'] === "Federal") ? "selected" : "" ?>>Federal</option>
                </select>
            </label><br><br>

            <label>Election Name: <input type="text" name="election_name" required value="<?= htmlspecialchars($election['election_name'] ?? '') ?>"></label><br><br>

            <label>Start Date & Time: <input type="datetime-local" name="start_datetime" required value="<?= isset($election['start_datetime']) ? date('Y-m-d\TH:i', strtotime($election['start_datetime'])) : '' ?>"></label><br><br>

            <label>End Date & Time: <input type="datetime-local" name="end_datetime" required value="<?= isset($election['end_datetime']) ? date('Y-m-d\TH:i', strtotime($election['end_datetime'])) : '' ?>"></label>
        </fieldset>

        <br>
        <fieldset id="candidates-fieldset">
            <legend><strong>Candidates</strong></legend>
            <?php $index = 0; foreach ($candidates as $candidate): $index++; ?>
                <div class="candidate-fields" id="candidate-<?= $index ?>">
                    <h4>Candidate <?= $index ?></h4>
                    <label>Candidate ID: <input type="text" name="candidate_id_<?= $index ?>" required value="<?= htmlspecialchars($candidate['candidate_id']) ?>"></label><br><br>
                    <label>Candidate Name: <input type="text" name="candidate_name_<?= $index ?>" required value="<?= htmlspecialchars($candidate['candidate_name']) ?>"></label><br><br>
                    <label>Party: <input type="text" name="party_<?= $index ?>" value="<?= htmlspecialchars($candidate['party']) ?>"></label><br><br>
                    <label>Party Symbol: <input type="text" name="symbol_<?= $index ?>" value="<?= htmlspecialchars($candidate['candidate_symbol']) ?>"></label><br><br>
                    <label>Image: <input type="file" name="image_<?= $index ?>" accept="image/*"></label><br><br>
                    <?php if (!empty($candidate['candidate_image'])): ?>
                        <img src="uploads/<?= htmlspecialchars($candidate['candidate_image']) ?>" width="50" height="50"><br><br>
                    <?php endif; ?>
                    <button type="button" class="remove-candidate" onclick="removeCandidate(<?= $index ?>)">Remove Candidate</button>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <button type="button" id="add-candidate" class="button">Add Candidate</button><br><br>
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
        candidateCount++;
        const div = document.createElement('div');
        div.className = 'candidate-fields';

        div.innerHTML = `
            <h4>Candidate ${candidateCount}</h4>
            <label>Candidate ID: <input type="text" name="candidate_id_${candidateCount}" required></label><br><br>
            <label>Candidate Name: <input type="text" name="candidate_name_${candidateCount}" required></label><br><br>
            <label>Party: <input type="text" name="party_${candidateCount}"></label><br><br>
            <label>Party Symbol: <input type="text" name="symbol_${candidateCount}"></label><br><br>
            <label>Image: <input type="file" name="image_${candidateCount}" accept="image/*"></label><br><br>
            <button type="button" class="remove-candidate">Remove Candidate</button>
        `;

        div.querySelector('.remove-candidate').addEventListener('click', function () {
            div.remove();
            updateCandidateIndexes();
        });

        candidatesFieldset.appendChild(div);
        updateCandidateIndexes();
    }

    function updateCandidateIndexes() {
        const candidateDivs = candidatesFieldset.querySelectorAll('.candidate-fields');
        candidateCount = candidateDivs.length;

        candidateDivs.forEach((div, index) => {
            const number = index + 1;
            div.querySelector('h4').textContent = `Candidate ${number}`;

            const inputs = div.querySelectorAll('input');
            inputs.forEach(input => {
                const fieldName = input.name.split('_')[0]; // e.g., 'candidate_id', 'candidate_name'
                input.name = `${fieldName}_${number}`;
            });

            const removeBtn = div.querySelector('.remove-candidate');
            removeBtn.onclick = function () {
                div.remove();
                updateCandidateIndexes();
            };
        });
    }

    // Attach event to existing remove buttons
    document.querySelectorAll('.remove-candidate').forEach(btn => {
        btn.addEventListener('click', function () {
            const div = btn.closest('.candidate-fields');
            div.remove();
            updateCandidateIndexes();
        });
    });

    document.getElementById('add-candidate').addEventListener('click', addCandidate);
    </script>
</body>
</html>
