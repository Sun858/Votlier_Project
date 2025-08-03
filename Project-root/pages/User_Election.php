<?php
session_start(); //figure out if i can move this out of the view
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/election.sn.php';
checkSessionTimeout();

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}

$userId = $_SESSION["user_id"];
$polls = getActivePollsForUser($conn, $userId);

// Determine which poll is selected, if any (GET or POST, preference GET for reloads)
$selectedPollId = isset($_GET['poll_id']) ? intval($_GET['poll_id']) : (isset($_POST['poll_id']) ? intval($_POST['poll_id']) : null);

// Only fetch candidates if a poll is selected
$candidates = [];
if ($selectedPollId) {
    $candidates = getCandidatesByPoll($conn, $selectedPollId);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Your Vote</title>
    <link rel="stylesheet" href="../Assets/css/User_Election.css">
</head>
<body>
    <h1>Cast Your Vote</h1>

    <?php if (count($polls) === 0): ?>
        <p>No elections are currently available for you to vote in.</p>
    <?php else: ?>

        <!-- Election Picker -->
        <form method="GET" action="">
            <label for="poll_id">Choose an election:</label>
            <select id="poll_id" name="poll_id" required onchange="this.form.submit()">
                <option value="">-- Select an election --</option>
                <?php foreach ($polls as $poll): ?>
                    <option value="<?php echo $poll['poll_id']; ?>" <?php if ($selectedPollId == $poll['poll_id']) echo 'selected'; ?>>
                        <?php echo htmlspecialchars($poll['election_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>

        <!-- Voting Form: Only show if a poll is selected and candidates exist -->
        <?php if ($selectedPollId && count($candidates) > 0): ?>
            <form action="../includes/submit_vote.php" method="POST">
                <input type="hidden" name="poll_id" value="<?php echo htmlspecialchars($selectedPollId); ?>">
                <?php
                // Up to 3 preferences (adjust as needed)
                for ($i = 1; $i <= 3; $i++) {
                    echo "<label for='candidate_id_$i'>Preference $i:</label>";
                    echo "<select id='candidate_id_$i' name='candidate_id_$i'>";
                    echo "<option value=''>-- Select a candidate --</option>";
                    foreach ($candidates as $cand) {
                        echo "<option value='{$cand['candidate_id']}'>{$cand['candidate_name']} ({$cand['party']})</option>";
                    }
                    echo "</select><br>";
                }
                ?>
                <input type="submit" value="Submit Vote">
            </form>
        <?php elseif ($selectedPollId): ?>
            <p>No candidates have been added for this election yet.</p>
        <?php endif; ?>

    <?php endif; ?>

    <a href="User_Home.php" class="back-to-home-link">Back to Home</a>

<?php
// Error/success popup handling (styled, per your signup.php)
if (isset($_GET["error"]) || isset($_GET["success"])) {
    echo '
    <style>
        .error-popup { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            background-color: #ffebee; border-left: 4px solid #f44336; color: #d32f2f;
            padding: 20px 40px 20px 30px; border-radius: 4px; font-family: Arial, sans-serif;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); z-index: 1000; text-align: center; max-width: 80%;
            opacity: 1; transition: opacity 0.5s ease-out; box-sizing: border-box; }
        .error-popup.success { background-color: #e8f5e9; border-left-color: #4CAF50; color: #2E7D32; }
        .error-popup p { margin: 0; font-weight: bold; font-size: 18px; }
        .close-btn { position: absolute; top: 10px; right: 10px; cursor: pointer; font-size: 20px; color: #d32f2f; background: none; border: none; padding: 0 5px; }
        .error-popup.success .close-btn { color: #2E7D32; }
        .close-btn:hover { color: #9a0007; }
        .error-popup.success .close-btn:hover { color: #1B5E20; }
    </style>
    ';

    echo '<div class="error-popup'.(isset($_GET["success"]) ? ' success' : '').'" id="errorPopup">
        <button class="close-btn" onclick="closePopup()">×</button>';

    // Cases for error and success
    if (isset($_GET["error"])) {
        switch ($_GET["error"]) {
            case "ratelimited":
                echo '<p>⚠️ Too many attempts. Try later!</p>'; break;
            case "novotesubmitted":
                echo '<p>⚠️ Please select at least one candidate.</p>'; break;
            case "missingpoll":
                echo '<p>⚠️ No election selected.</p>'; break;
            case "notloggedin":
                echo '<p>⚠️ You must be logged in to vote.</p>'; break;
            case "You have already voted in this election.":
                echo '<p>⚠️ You have already voted in this election.</p>'; break;
            case "This election is not currently active.":
                echo '<p>⚠️ This election is not currently active.</p>'; break;
            case "Failed to record vote.":
                echo '<p>⚠️ Failed to record vote. Please try again.</p>'; break;
            default:
                echo '<p>⚠️ '.htmlspecialchars($_GET["error"]).'</p>'; break;
        }
    }
    if (isset($_GET["success"])) {
        switch ($_GET["success"]) {
            case "vote":
                echo '<p>✅ Your vote has been submitted!</p>'; break;
            default:
                echo '<p>✅ '.htmlspecialchars($_GET["success"]).'</p>'; break;
        }
    }
    echo '</div>';

    echo '
    <script>
        function closePopup() {
            var popup = document.getElementById("errorPopup");
            popup.style.opacity = "0";
            setTimeout(function() { popup.style.display = "none"; }, 500);
        }
        setTimeout(closePopup, 5000);
    </script>
    ';
}
?>
    <footer>
        <p>&copy; 2025 Votify. All rights reserved.</p>
    </footer>
</body>
</html>