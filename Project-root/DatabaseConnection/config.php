<?php
// Database connection config
$con = mysqli_connect("localhost", "root", "", "voting_system"); // Add an empty string for the password (default is empty in XAMPP)
if (mysqli_connect_errno()) {
    echo "Failed to connect to Database: " . mysqli_connect_error();
} else {
    echo "<p>Connection Established </p>";
}
?>

