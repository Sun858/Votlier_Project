<?php
session_start();

require_once '../DatabaseConnection/config.php';
require_once 'security.sn.php';
require_once 'functions.sn.php';


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