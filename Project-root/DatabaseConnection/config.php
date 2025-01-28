<?php
// Database connection config
$conn = mysqli_connect("localhost", "root", "", "voting_system");
if (mysqli_connect_errno()) {
    die("Failed to connect to Database: " . mysqli_connect_error());
}
?>

