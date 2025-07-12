<?php
//Logout scripts for both Admin and Users(Candidates)

// Start the session to track user data
session_start();

// Terminates the session itself
session_unset();
session_destroy();

// Once successfully logged out Redirects Admin/Users to the login page
header("Location: ../pages/login.php");
exit;
?>
