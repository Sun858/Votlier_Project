<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administration') {
    die("Access denied.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Election Created</title>
</head>
<body>
    <h2>✅ Election Created Successfully!</h2>
    <a href="dashboard.php">Go to Dashboard</a>
</body>
</html>