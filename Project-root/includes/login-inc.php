<?php

if (isset($_POST["submit"])) {

    $email = $_POST["uid"];
    $pwd = $_POST["pwd"];

    require_once '../DatabaseConnection/config.php';
    require_once 'functions.sn.php';

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