<?php
// This page handles the admin login functionality
// It checks if the admin is already logged in, and if not, it processes the login.
session_start();

require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';
require_once '../includes/functions.sn.php';


$ip = $_SERVER['REMOTE_ADDR'];
$resource = 'admin_login';

if (isRateLimitedDB($conn, $ip, $resource, 4, 900)) {
    header("location: ../pages/stafflogin.php?error=ratelimited");
    exit();
}

// Once again logs the attempt so they cant just refresh the page, or the DB loses track of the attempts.

recordLoginAttemptDB($conn, $ip, $resource);



if (isset($_POST["submit"])) {
    $email = $_POST["loginEmail"];
    $pwd = $_POST["loginPassword"];

    if (emptyInputLogin($email, $pwd) !== false) {
        header("location: ../pages/stafflogin.php?error=emptyinput");
        exit();
    }

    loginAdmin($conn, $email, $pwd);
} else {
    header("location: ../pages/stafflogin.php");
    exit();
}