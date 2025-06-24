<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../DatabaseConnection/config.php'; // This is the database connection including the authentication details
require 'functions.sn.php'; // This has most of the functions that are called within this file

if ($_SERVER['REQUEST_METHOD'] === 'POST') { // If the server request is UNIVERSALLY 'Post' then this will happen:
    // Grab all the data that user has inputted into the FORM
    $firstName = $_POST['first-name'];
    $middleName = $_POST['middle-name'];
    $lastName = $_POST['last-name'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm-password'];

    // Input Validation
    if (emptyInputSignup($firstName, $lastName, $email, $password, $confirmPassword) !== false) {
        header("location: ../pages/signup.php?error=emptyinput");
        exit();
    }

    // Name verification (A-Z Only)
    if (invalidName($firstName, $lastName) !== false) {
        header("location: ../pages/signup.php?error=invalidname");
        exit();
    }


    // Email verification (valid)
    if (invalidEmail($email) !== false) {
        header("location: ../pages/signup.php?error=invalidemail");
        exit();
    }

    // Password strength validayion
    if (passwordStrength($password) !== false) {
        header("location: ../pages/signup.php?error=weakpassword");
        exit();
    }

    // Password Verification
    if (pwdMatch($password, $confirmPassword) !== false) {
        header("location: ../pages/signup.php?error=passwordsdontmatch");
        exit();
    }

    // This function will check the database if the email already exists and stop the user from creating a duplicate
    if (emailExists($conn, $email) !== false) {
        header("location: ../pages/signup.php?error=userexists");
        exit();
    }
    


    // Store all the PII (ENCRYPTED) and hashed password into the database
    createUser($conn, $firstName, $middleName, $lastName, $email, $password);

}
else {
    header("location: ../pages/signup.php");
    exit();
}