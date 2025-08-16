<?php
session_start();
// Security and database includes
require_once '../includes/security.sn.php';
require_once '../DatabaseConnection/config.php';
require_once '../includes/election_stats.php';

// Check session timeout and admin login
checkSessionTimeout();
if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Fetch statistics
$totalElections = getTotalElections($conn);
$totalVoters = getTotalVoters($conn);
$totalCandidates = getTotalCandidates($conn);
$electionCounts = getElectionStatusCounts($conn); // Get all status counts

$electionActivities = getElectionActivities($conn);
$lastLogin = getLastAdminLogin($conn);
$ongoingElectionsCount = getOngoingElectionsCount($conn);
$upcomingElectionsCount = getUpcomingElectionsCount($conn);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ionicon Sidebar Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Home.css">
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
            <h1>Welcome to Admin Dashboard</h1>
            <p>This is the admin dashboard for Votify.</p>
        </header>

        <section class="dashboard-section">
            <h2>Overview</h2>
            <div class="profile-container">
                <h3>Admin Updates</h3>
                <ul>
                    <li>
                        <strong>Last Login:</strong>
                        <?php echo htmlspecialchars($lastLogin, ENT_QUOTES, 'UTF-8'); ?>
                    </li>
                    <li>
                        <?php if ($upcomingElectionsCount == 1): ?>
                            There is 1 election happening in the next 7 days.
                        <?php elseif ($upcomingElectionsCount > 1): ?>
                            There are <?php echo $upcomingElectionsCount; ?> elections happening in the next 7 days.
                        <?php else: ?>
                            There are no elections happening in the next 7 days.
                        <?php endif; ?>
                    </li>
                    <li>
                        <?php if ($ongoingElectionsCount === 1): ?>
                            There is 1 election happening right now.
                        <?php elseif ($ongoingElectionsCount > 1): ?>
                            There are <?php echo $ongoingElectionsCount; ?> elections happening right now.
                        <?php else: ?>
                            There are no elections happening right now.
                        <?php endif; ?>
                    </li>
                </ul>
            </div>

            <div class="profile-container">
                <h3>Latest Election Activity</h3>
                <p>Recent events related to elections.</p>
                <ul class="activity-list">
                    <?php if (!empty($electionActivities)): ?>
                        <?php foreach ($electionActivities as $activity): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($activity['event_type']); ?>:</strong>
                                <?php echo htmlspecialchars($activity['details']); ?>
                                <?php
                                    $eventTimeUtc = $activity['event_time'];
                                    $eventDateTime = new DateTime($eventTimeUtc, new DateTimeZone('UTC'));
                                    $eventDateTime->setTimezone(new DateTimeZone('Australia/Melbourne'));
                                    $eventTimeMel = $eventDateTime->format('M d, Y H:i');
                                ?>
                                <small>(<?php echo htmlspecialchars($eventTimeMel); ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No recent election activity.</li>
                    <?php endif; ?>
                </ul>
            </div>



        </section>

        <section class="dashboard-section">
            <h2>Statistics</h2>
            <div class="stats-grid">
                <!-- Voters Card -->
                <div class="stat-card">
                    <ion-icon class="stat-icon" name="people-outline"></ion-icon>
                    <div class="stat-value">
                        <?php echo htmlspecialchars((string)$totalVoters, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="stat-label">Total Voters</div>
                </div>

                <!-- Candidates Card -->
                <div class="stat-card">
                    <ion-icon class="stat-icon" name="checkmark-circle-outline"></ion-icon>
                    <div class="stat-value">
                        <?php echo htmlspecialchars((string)$totalCandidates, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="stat-label">Total Candidates</div>
                </div>

                <!-- Election Status Card -->
                <div class="stat-card election-status-card">
                    <ion-icon class="stat-icon" name="time-outline"></ion-icon>
                    <div class="stat-value" id="election-count-display">
                        <?php echo htmlspecialchars((string)$electionCounts['total'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="stat-label" id="election-count-label">Total Elections</div>

                    <div class="election-status-buttons">
                        <button class="status-btn active" data-status="total">Total</button>
                        <button class="status-btn" data-status="active">Active</button>
                        <button class="status-btn" data-status="upcoming">Upcoming</button>
                        <button class="status-btn" data-status="completed">Completed</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Ionicon scripts -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const statusButtons = document.querySelectorAll('.status-btn');
            const displayElement = document.getElementById('election-count-display');
            const labelElement = document.getElementById('election-count-label');

            // Data from PHP
            const electionCounts = {
                total: <?= $electionCounts['total'] ?>,
                active: <?= $electionCounts['active'] ?>,
                upcoming: <?= $electionCounts['upcoming'] ?>,
                completed: <?= $electionCounts['completed'] ?>
            };

            // Function to update display based on button click and data 
            function updateDisplay(status) {
                displayElement.textContent = electionCounts[status] || '0';
                labelElement.textContent = status.charAt(0).toUpperCase() +
                    status.slice(1) + ' Elections';
            }

            // Button click handlers
            statusButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Update active button
                    statusButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');

                    // Update display
                    updateDisplay(this.dataset.status);
                });
            });
        });
    </script>
</body>

</html>