<?php
session_start();

require_once '../DatabaseConnection/config.php';
require_once 'security.sn.php';
require_once 'functions.sn.php';


$ip = $_SERVER['REMOTE_ADDR'];
$resource = 'user_login';

if (isRateLimitedDB($conn, $ip, $resource, 5, 600)) {
    header("location: ../pages/login.php?error=ratelimited");
    exit();
}

// Logs the attempt, so they cant just refresh the page or something.

recordLoginAttemptDB($conn, $ip, $resource);


if (isset($_POST["submit"])) {

    $email = $_POST["loginEmail"];
    $pwd = $_POST["loginPassword"];

    // This is where the error handlers and other stuff comes in. This first one is if the inputs are empty. More can be added, take inspiration from submit-form.php
    if (emptyInputLogin($email, $pwd) !== false) {
        header("location: ../pages/login.php?error=emptyinput");
        exit();
    }
    loginUser($conn, $email, $pwd);
}
else {
    header("location: ../pages/login.php");
    exit();
}