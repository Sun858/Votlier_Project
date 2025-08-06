<?php
session_start();
require_once '../includes/security.sn.php';
checkSessionTimeout();

require_once '../DataBaseConnection/config.php';

$adminName = "John Citizen";
$adminEmail = "admin@example.com";
$adminID = "063242";
$lastLogin = date("F j, Y, g:i a", strtotime($_SESSION['last_login'] ?? date("Y-m-d H:i:s")));

// Fetch all elections
$elections = [];
$stmt = mysqli_prepare($conn, "SELECT poll_id, election_name, start_datetime, end_datetime FROM election ORDER BY start_datetime ASC");
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($result)) {
    $elections[] = $row;
}
mysqli_stmt_close($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Votify - Admin Profile</title>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
<link rel="stylesheet" href="../Assets/css/Admin_Profile.css">
</head>
<body>

<button class="mobile-menu-toggle" aria-label="Toggle menu">
    <ion-icon name="menu-outline"></ion-icon>
</button>

<aside class="sidebar">
    <div class="sidebar-top-bar">
        <ion-icon class="voter-icon" name="person-circle-outline"></ion-icon>
        <h3>Votify</h3>
    </div>
    <nav class="sidebar-nav">
        <ul>
            <li><a href="Admin_Home.php"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
            <li><a href="Admin_Profile.php" class="active"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
            <li><a href="Admin_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
            <li><a href="Admin_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
            <li><a href="Admin_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
        </ul>
    </nav>
    <div class="sidebar-footer">
        <a href="../includes/logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
    </div>
</aside>

<main class="main-content">
    <section class="profile-card" aria-label="Admin Profile Information">
        <div class="profile-avatar" role="img" aria-label="Profile Avatar"></div>
        <div class="profile-info">
            <h1><?= htmlspecialchars($adminName) ?></h1>
            <div class="role">Election Coordinator</div>
            <div class="admin-id">Admin ID: <?= htmlspecialchars($adminID) ?></div>
            <div class="admin-email"><?= htmlspecialchars($adminEmail) ?></div>
            <div class="last-login">Last login: <?= htmlspecialchars($lastLogin) ?></div>
            <div class="action-buttons">
                <button class="red-btn" onclick="showPasswordModal()">Change Password</button>
            </div>
        </div>
    </section>

    <section class="employee-info" aria-label="Employee Information">
        <h2>Employee Information</h2>
        <p><span class="label">Name:</span> <?= htmlspecialchars($adminName) ?></p>
        <p><span class="label">D.O.B:</span> 15 March 1990</p>
        <p><span class="label">Work Email:</span> <?= htmlspecialchars($adminEmail) ?></p>
        <p><span class="label">Team:</span> Administration - Election Coordinator</p>
        <p><span class="label">Supervisor:</span> Jane Citizen</p>
        <p><span class="label">Supervisor Email:</span> Jane.Citizen@example.com</p>
    </section>

    <section class="election-overview" aria-label="Election Overview">
        <h2>Election Overview</h2>
        <?php if (count($elections) > 0): ?>
            <ul class="election-list">
                <li><strong>Total Elections:</strong> <?= count($elections) ?></li>
                <?php
                $now = new DateTime();
                foreach ($elections as $election):
                    $start = new DateTime($election['start_datetime']);
                    $interval = $now->diff($start);
                    $timeUntilStart = $start > $now ? $interval->format('%a days, %h hours') : 'Already started';
                ?>
                    <li>
                        <strong><?= htmlspecialchars($election['election_name']) ?></strong>
                        <span class="start-info">Starts in: <?= $timeUntilStart ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="election-actions">
                <div class="action-buttons">
                    <button class="green-btn" onclick="window.location.href='../admin/admin_create_election.php'">Create New Election</button>
                    <button class="green-btn" onclick="window.location.href='../admin/dashboard.php'">Current Elections</button>
                </div>
            </div>
        <?php else: ?>
            <p>No elections found.</p>
            <div class="action-buttons">
                <button class="green-btn" onclick="window.location.href='../admin/admin_create_election.php'">Create New Election</button>
                <button class="green-btn" onclick="window.location.href='../admin/dashboard.php'">Current Elections</button>
            </div>
        <?php endif; ?>
    </section>
</main>

<!-- Password Change Modal -->
<div id="passwordModal" class="modal">
  <div class="modal-content">
    <h3>Change Password</h3>
    
    <!-- Password change messages -->
    <?php if (isset($_SESSION['password_error'])): ?>
        <div class="alert alert-error"><?= htmlspecialchars($_SESSION['password_error']) ?></div>
        <?php unset($_SESSION['password_error']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['password_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['password_success']) ?></div>
        <?php unset($_SESSION['password_success']); ?>
    <?php endif; ?>
    
    <form id="passwordForm" method="POST" action="../admin/Admin_Change_Password.php" onsubmit="return validatePasswordForm()">
      <div class="form-group">
        <label for="currentPassword">Current Password:</label>
        <input type="password" id="currentPassword" name="currentPassword" required>
      </div>
      <div class="form-group">
        <label for="newPassword">New Password:</label>
        <input type="password" id="newPassword" name="newPassword" required>
      </div>
      <div class="form-group">
        <label for="verifyPassword">Verify New Password:</label>
        <input type="password" id="verifyPassword" name="verifyPassword" required>
      </div>
      <div class="modal-buttons">
        <button type="button" class="cancel-btn" onclick="hidePasswordModal()">Back to Profile</button>
        <button type="submit" class="green-btn">Update Password</button>
      </div>
    </form>
  </div>
</div>

<script>
    // Mobile menu toggle functionality
    document.querySelector('.mobile-menu-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Password Modal Functions
    function showPasswordModal() {
        document.getElementById('passwordModal').style.display = 'block';
    }

    function hidePasswordModal() {
        document.getElementById('passwordModal').style.display = 'none';
    }

    // Form validation
    function validatePasswordForm() {
        const newPassword = document.getElementById('newPassword').value;
        const verifyPassword = document.getElementById('verifyPassword').value;
        
        if (newPassword !== verifyPassword) {
            alert('New passwords do not match!');
            return false;
        }
        return true;
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('passwordModal');
        if (event.target == modal) {
            hidePasswordModal();
        }
    }
    
    // Auto-show modal if there are password messages
    <?php if (isset($_SESSION['password_error']) || isset($_SESSION['password_success'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showPasswordModal();
        });
    <?php endif; ?>
</script>

</body>
</html>