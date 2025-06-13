<?php
// Detect if running inside Docker (checks for Docker-specific hostname)
if (getenv("DOCKER_ENV") == "true") {
    $host = "mysql-container"; // Docker MySQL container name
} else {
    $host = "localhost"; // XAMPP users
}

$username = "admin";
$password = "adminpassword";
$database = "voting_system";

// Connect to database
$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Failed to connect to Database: " . mysqli_connect_error());
}
?>