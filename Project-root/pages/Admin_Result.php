<?php
session_start();
// This is the security page for rate limiting and timeout. 15Min is currently set
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/result_functions.php';

checkSessionTimeout(); // Calling the function for the timeout, it redirects to login page and ends the session.


// Redirect if not logged in as an admin
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Load all the variables to pre-apply for the view
$pageState = loadAdminResultPageState($conn);
$pollId = $pageState['pollId'];
$results = $pageState['results'];
$tallyMsg = $pageState['tallyMsg'];
$elections = $pageState['elections']; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ionicon Sidebar Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Result.css">


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

        <h2>Election Results</h2>

        <form action="../includes/submit_tally.php" method="POST" style="margin-bottom: 20px;">
            <label for="poll_id" class="Title">
                Select Election:
            </label>
            <select name="poll_id" id="poll_id" class="styled-select" required>
                <option value="">-- Select --</option>
                <?php foreach ($elections as $e): ?>
                    <option value="<?= $e['poll_id'] ?>" <?= $pollId == $e['poll_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['election_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="tally_votes" class="tally-button">
                Tally Votes
            </button>
            <button type="submit" name="view_results" class="View-Results-button">
                View Results
            </button>
        </form>

        <!--Display success message if available-->
        <?php if ($tallyMsg):?>
            <div class="Result-banner">
                <?= htmlspecialchars($tallyMsg) ?>
            </div>
        <?php endif; ?>

        <!--Display Results table if results are available-->
        <?php if (!empty($results)): ?>
            <!-- Table Header -->
            <h3 class ="Result-banner">
                 Results for Selected Election (Poll ID: <?= htmlspecialchars($pollId) ?>)
            </h3>
            <!-- Table to display candidate voting results. -->
           <table class ="results-table">
                <thead>
                    <tr class="results-table-header-row">
                        <th>Candidate ID</th>
                        <th>Candidate Name</th>
                        <th>Total Votes</th>
                        <th>Rank 1 Votes</th>
                        <th>Rank 2 Votes</th>
                        <th>Rank 3 Votes</th>
                        <th>Rank 4 Votes</th>
                        <th>Rank 5 Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $index => $result): ?>
                    <tr>
                        <td><?= htmlspecialchars($result['candidate_id']) ?></td>
                        <td><?= htmlspecialchars($result['candidate_name']) ?></td>
                        <td><?= htmlspecialchars($result['total_votes']) ?></td>
                        <td><?= htmlspecialchars($result['r1_votes']) ?></td>
                        <td><?= htmlspecialchars($result['r2_votes']) ?></td>
                        <td><?= htmlspecialchars($result['r3_votes']) ?></td>
                        <td><?= htmlspecialchars($result['r4_votes']) ?></td>
                        <td><?= htmlspecialchars($result['r5_votes']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php elseif ($pollId && empty($tallyMsg) && empty($results)): ?>
                <p class ="Result-banner">No results found for the selected election, or votes have not yet been tallied.</p>
            <?php endif; ?>

    </main>

        <!-- Ionicon scripts -->

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>