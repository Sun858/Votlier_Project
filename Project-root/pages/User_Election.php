<?php
session_start();
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/admin_election.sn.php';
require_once '../includes/vote.sn.php';
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
    <title>Ionicon Sidebar Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/User_Election.css">
    <link rel="stylesheet" href="../Assets/css/User_Election_Extra.css"> <!-- Voting Form Stylesheet -->
    

</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="User_Home.php">
                    <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                    <span class="text">Home</span>
                </a></li>
                <li><a href="User_Profile.php">
                    <span class="icon"><ion-icon name="people-outline"></ion-icon></span>
                    <span class="text">Profile</span>
                </a></li>
                <li><a href="User_Election.php">
                    <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                    <span class="text">Election</span>
                </a></li>
                <li><a href="User_Result.php">
                    <span class="icon"><ion-icon name="eye-outline"></ion-icon></span>
                    <span class="text">Result</span>
                </a></li>
                <li><a href="User_Settings.php">
                    <span class="icon"><ion-icon name="settings-outline"></ion-icon></span>
                    <span class="text">Settings</span>
                </a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../controllers/Logout.php" class="footer-link signout-link">
                <span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
                <span class="text">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Welcome to Voter Election Page</h1>
            <p>Explore active elections, submit your vote, and track results.</p>
        </header>

        <section class="vote-section">
            <h2>Cast Your Vote</h2>
            <?php if (count($polls) === 0): ?>
                <p  style="
                    padding: 12px 16px;
                    color: #1b5e20;
                    background: #e8f5e9;
                    border-left: 4px solid #2e7d32;
                    margin: 12px 0;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 0 4px 4px 0;
                ">No elections are currently available for you to vote in.</p>
            <?php else: ?>

            <!-- Election Picker -->
            <form method="GET" action="">
                <label for="poll_id">Choose an election:</label>
                <select id="poll_id" name="poll_id" required onchange="this.form.submit()" class="styled-select">
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
                <form action="../controllers/submit_vote.php" method="POST" class="voting-form">
                    <input type="hidden" name="poll_id" value="<?php echo htmlspecialchars($selectedPollId); ?>">
                    <div class="preferences-container">
                        <?php 
                        // Up to 3 Preferences (Adjust as Needed)
                        for ($i = 1; $i <= 3; $i++): ?>
                            <div class="preference-group">
                                <div class="preference-header">
                                    <span class="preference-number"><?php echo $i; ?>)</span>
                                    <label for="candidate_id_<?php echo $i; ?>">Preference <?php echo $i; ?></label>
                                </div>
                                <select id="candidate_id_<?php echo $i; ?>" name="candidate_id_<?php echo $i; ?>" class="candidate-select">
                                    <option value="">Select your <?php echo $i; ?><?php echo ($i == 1) ? 'st' : (($i == 2) ? 'nd' : 'rd'); ?> choice</option>
                                    <?php foreach ($candidates as $cand): ?>
                                        <option value="<?php echo $cand['candidate_id']; ?>">
                                            <?php echo htmlspecialchars($cand['candidate_name']); ?> 
                                            <span class="party-name">(<?php echo htmlspecialchars($cand['party']); ?>)</span>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <button type="submit" class="submit-vote-btn">
                        <ion-icon name="checkmark-circle-outline"></ion-icon>
                        Cast Your Vote
                    </button>
                </form>
        
            <?php elseif ($selectedPollId): ?>
                <p style="
                    padding: 12px 16px;
                    color: #1b5e20;
                    background: #e8f5e9;
                    border-left: 4px solid #2e7d32;
                    margin: 12px 0;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 0 4px 4px 0;
                    ">No candidates have been added for this election yet.
                </p>
            <?php endif; ?>

        <?php endif; ?>

            <a href="User_Home.php" class="back-to-home-link" 
                style="
                    background: #4CAF50; 
                    color: #fff; 
                    border: none; 
                    padding: 8px 18px; 
                    border-radius: 4px; 
                    margin-left: 10px; 
                    font-weight: 600; 
                    cursor: pointer; 
                    transition: 
                    background 0.2s;
                    text-decoration: none;" >Back to Home
            </a>
        </section>

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
    </main>

    <!-- Ionicon scripts -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const prefSelects = [
            document.getElementById('candidate_id_1'),
            document.getElementById('candidate_id_2'),
            document.getElementById('candidate_id_3')
        ];

        function updateSelectOptions() {
            // Get all selected candidate_ids
            const selected = prefSelects.map(sel => sel.value).filter(val => val !== '');
            prefSelects.forEach((select, i) => {
                Array.from(select.options).forEach(opt => {
                    if (opt.value === '') {
                        opt.disabled = false; // "Select..." option always enabled
                    } else {
                        // Disable if selected in any other select
                        opt.disabled = selected.includes(opt.value) && select.value !== opt.value;
                    }
                });
            });
        }

        prefSelects.forEach(sel => {
            sel.addEventListener('change', updateSelectOptions);
        });

        // Initial run
        updateSelectOptions();
    });
    </script> 
</body>
</html>