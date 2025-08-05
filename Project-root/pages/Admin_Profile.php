<?php
session_start();
require_once '../includes/security.sn.php';
checkSessionTimeout();

require_once '../DataBaseConnection/config.php';

$adminName = "John Citizen";
$adminEmail = "admin@example.com";
$adminID = "063242";
$lastLogin = date("F j, Y, g:i a", strtotime($_SESSION['last_login'] ?? date("Y-m-d H:i:s")));

// Fetch all elections (no admin filter since admin_id column doesn't exist)
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
<style>
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }
  
  body, html {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f7f9fa;
    color: #333;
    height: 100%;
  }

  .sidebar {
    position: fixed;
    top: 0; 
    left: 0;
    height: 100vh;
    width: 80px;
    background-color: #3BAE5D;
    color: white;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 1000;
    transition: width 0.3s ease;
    overflow: hidden;
  }
  
  .sidebar:hover {
    width: 220px;
  }
  
  .sidebar-top-bar {
    display: flex;
    align-items: center;
    padding: 1.5rem 1rem 1.5rem 1.5rem;
    border-bottom: 1px solid rgba(255,255,255,0.15);
    white-space: nowrap;
  }
  
  .sidebar-top-bar ion-icon.voter-icon {
    font-size: 2.5rem;
    margin-right: 0.6rem;
    flex-shrink: 0;
  }
  
  .sidebar-top-bar h3 {
    font-weight: 700;
    font-size: 1.5rem;
    letter-spacing: 2px;
    opacity: 0;
    transition: opacity 0.2s ease 0.1s;
  }
  
  .sidebar:hover .sidebar-top-bar h3 {
    opacity: 1;
  }
  
  .sidebar-nav {
    flex-grow: 1;
    padding-top: 1rem;
  }
  
  .sidebar-nav ul {
    list-style: none;
  }
  
  .sidebar-nav li {
    margin-bottom: 0.25rem;
  }
  
  .sidebar-nav a {
    display: flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    color: white;
    text-decoration: none;
    font-weight: 600;
    transition: background-color 0.25s ease;
    white-space: nowrap;
  }
  
  .sidebar-nav a:hover,
  .sidebar-nav a.active {
    background-color: rgba(255, 255, 255, 0.1);
  }
  
  .sidebar-nav a .icon {
    margin-right: 1rem;
    font-size: 1.5rem;
    flex-shrink: 0;
  }
  
  .sidebar-nav a .text {
    opacity: 0;
    transition: opacity 0.2s ease;
  }
  
  .sidebar:hover .sidebar-nav a .text {
    opacity: 1;
  }
  
  .sidebar-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid rgba(255,255,255,0.15);
    white-space: nowrap;
  }
  
  .footer-link {
    display: flex;
    align-items: center;
    color: white;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: color 0.25s ease;
  }
  
  .footer-link:hover {
    color: #d3ffe6;
  }
  
  .footer-link .icon {
    margin-right: 0.75rem;
    font-size: 1.5rem;
    flex-shrink: 0;
  }
  
  .footer-link .text {
    opacity: 0;
    transition: opacity 0.2s ease;
  }
  
  .sidebar:hover .footer-link .text {
    opacity: 1;
  }

  main.main-content {
    margin-left: 80px;
    padding: 2rem 3rem;
    background-color: #f7f9fa;
    min-height: 100vh;
    transition: margin-left 0.3s ease;
  }
  
  .sidebar:hover ~ .main-content {
    margin-left: 220px;
  }

  .profile-card {
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 2rem 2.5rem;
    max-width: 900px;
    margin-bottom: 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
  }
  
  .profile-avatar {
    flex-shrink: 0;
    width: 110px;
    height: 110px;
    border-radius: 50%;
    background: url('https://www.svgrepo.com/show/510930/user-circle.svg') center/cover no-repeat;
    border: 2px solid #3BAE5D;
    background-color: #f0f0f0;
  }
  
  .profile-info h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 700;
    color: #3BAE5D;
  }
  
  .profile-info .role {
    font-size: 18px;
    color: #4a5568;
    margin-top: 6px;
    font-weight: 600;
  }
  
  .profile-info .admin-id,
  .profile-info .admin-email,
  .profile-info .last-login {
    font-size: 14px;
    color: #718096;
    margin-top: 4px;
  }

  .action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
  }
  
  .action-buttons button {
    border: 1px solid transparent;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .action-buttons button.green-btn {
    background-color: #e6f7f0;
    border-color: #b5ead7;
    color: #065f46;
  }
  
  .action-buttons button.green-btn:hover {
    background-color: #b5ead7;
    border-color: #84dbb6;
  }
  
  .action-buttons button.red-btn {
    background-color: #fde8e8;
    border-color: #f8b4b4;
    color: #9b1c1c;
  }
  
  .action-buttons button.red-btn:hover {
    background-color: #f8b4b4;
    border-color: #f38f8f;
  }

  .employee-info,
  .election-overview {
    background: white;
    max-width: 900px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 2rem 2.5rem;
    margin-bottom: 2rem;
  }
  
  .employee-info h2,
  .election-overview h2 {
    margin-top: 0;
    color: #3BAE5D;
    font-size: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 0.75rem;
    margin-bottom: 1.5rem;
  }
  
  .employee-info p {
    font-size: 15px;
    color: #4a5568;
    margin: 6px 0;
  }
  
  .employee-info .label {
    font-weight: 700;
    color: #3BAE5D;
    display: inline-block;
    width: 140px;
  }
  
  .election-list {
    list-style: none;
    padding-left: 0;
    margin: 0;
  }
  
  .election-list li {
    border-bottom: 1px solid #e2e8f0;
    padding: 12px 0;
    font-size: 15px;
    color: #2d3748;
  }
  
  .election-list li:last-child {
    border-bottom: none;
  }
  
  .election-list strong {
    color: #276749;
  }
  
  .start-info {
    font-style: italic;
    color: #4a5568;
    font-size: 14px;
    display: block;
    margin-top: 4px;
  }

  .election-actions {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
  }

  ion-icon {
    vertical-align: middle;
  }

  @media (max-width: 768px) {
    .sidebar {
      width: 220px;
      transform: translateX(-220px);
      transition: transform 0.3s ease;
    }
    
    .sidebar.active {
      transform: translateX(0);
    }
    
    .sidebar:hover {
      width: 220px;
    }
    
    .sidebar-top-bar h3,
    .sidebar-nav a .text,
    .footer-link .text {
      opacity: 1;
    }
    
    main.main-content {
      margin-left: 0;
      padding: 1rem;
    }
    
    .sidebar:hover ~ .main-content {
      margin-left: 0;
    }
    
    .profile-card,
    .employee-info,
    .election-overview {
      max-width: 100%;
      margin-bottom: 1.5rem;
      padding: 1.5rem;
    }
    
    .profile-card {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .action-buttons {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .mobile-menu-toggle {
      display: block;
      position: fixed;
      top: 1rem;
      left: 1rem;
      z-index: 1100;
      background: #3BAE5D;
      color: white;
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      font-size: 1.5rem;
      cursor: pointer;
    }
  }
</style>
<script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
<script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
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
                <button class="red-btn" onclick="window.location.href='../admin/forgot_password.html'">Reset Password</button>
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

<script>
    // Mobile menu toggle functionality
    document.querySelector('.mobile-menu-toggle').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });
</script>

</body>
</html>