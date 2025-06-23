<?php
session_start();

if (isset($_POST["submit"])) {
    $email = $_POST["loginEmail"];
    $pwd = $_POST["loginPassword"];

    require_once '../DatabaseConnection/config.php';
    require_once 'functions.sn.php';

    if (emptyInputLogin($email, $pwd) !== false) {
        header("location: ../pages/stafflogin.php?error=emptyinput");
        exit();
    }

    loginAdmin($conn, $email, $pwd);
} else {
    header("location: ../pages/stafflogin.php");
    exit();
}