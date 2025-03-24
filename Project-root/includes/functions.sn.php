<?php

// This function will work as a error handler for when nothing is inputted in the form. :)
function emptyInputSignup($firstName, $lastName, $email, $password, $confirmPassword) {
    $result;
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
        $result = true;
    }
    else {
        $result = false;
    }
    return $result;
}

// This function will work as an error handler for an invalid syntax within the first name and last name fields within the form.
function invalidName($firstName, $lastName) {
    $result;
    if (!preg_match("/^[a-zA-Z-' ]*$/",$firstName,$lastName)) {
        $result = true;
    }
    else {
        $result = false;
    }
    return $result;
}

// This function will work as a validation for a correct email or not within the form using a build in variable on PHP
function invalidEmail($email) {
    $result;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $result = true;
    }
    else {
        $result = false;
    }
    return $result;
}

// This function will check the the password and confirm password inputs within the form are the same
function pwdMatch($password, $confirmPassword) {
    $result;
    if ($password !== $confirmPassword) {
        $result = true;
    }
    else {
        $result = false;
    }
    return $result;
}


// Check if email exists (This is currently operating as our USERNAME)
function emailExists($conn, $email) {
   $sql = "SELECT * FROM users WHERE email = ?;";
   $stmt = mysqli_stmt_init($conn);
   if (!mysqli_stmt_prepare($stmt, $sql)) {
    header("location: ../pages/signup.php?error=stmtfailed");
        exit();
   }

   mysqli_stmt_bind_param($stmt, "s", $email);
   mysqli_stmt_execute($stmt);

   $resultstmt = mysqli_stmt_get_result($stmt);

   if ($row = mysqli_fetch_assoc($resultstmt)) {
    return $row;
   }
   else {
    $result = false;
    return $result;
   }
    

   mysqli_stmt_close($stmt);
}


// Create the USER into the database USER
function createUser($conn, $firstName, $middleName, $lastName, $email, $password) {
    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, hash_password, salt, iterations, encryption_key, iv)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    $salt = random_bytes(16);
    $iterations = 100000;
    $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    $encryptionKey = random_bytes(32);
    $cipher = "aes-256-cbc";
    $iv = random_bytes(openssl_cipher_iv_length($cipher));

    $encryptedFirstName = openssl_encrypt($firstName, $cipher, $encryptionKey, 0, $iv);
    $encryptedMiddleName = openssl_encrypt($middleName, $cipher, $encryptionKey, 0, $iv);
    $encryptedLastName = openssl_encrypt($lastName, $cipher, $encryptionKey, 0, $iv);
    $encryptedEmail = openssl_encrypt($email, $cipher, $encryptionKey, 0, $iv);

    mysqli_stmt_bind_param($stmt, "sssssssss", $encryptedFirstName, $encryptedMiddleName, $encryptedLastName, $encryptedEmail, $hash, $salt, $iterations, $encryptionKey, $iv);
    if(!mysqli_stmt_execute($stmt)){
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    mysqli_stmt_close($stmt);

    header("location: ../pages/signup.php?error=none");
    exit();
}


// --------------------------------------------------------- 
// Functions for the login.php FILE!

function emptyInputLogin($email, $pwd) {
    $result;
    if (empty($email) || empty($password)) {
        $result = true;
    }
    else {
        $result = false;
    }
    return $result;
}

function loginUser($conn, $email, $pwd) {
    $emailExists = emailExists($conn, $email);

    if ($emailExists === false) {
        header("location: ../pages/login.php?error=wronglogin");
        exit();
    }

    $pwdHashed = $emailExists["hash_password"];
    $checkPwd = password_verify($pwd, $pwdHashed);

    if ($checkPwd === false) {
        header("location: ../pages/login.php?error=wronglogin");
        exit();
    } else if ($checkPwd === true) {
        session_start();
        $_SESSION["user_id"] = $emailExists["email"];
        header("location: ../index.html");
        exit();
    }
}


    $pwdHashed = $emailExists["usersPwd"];
    $checkPwd = password_verify($pwd, $pwdHashed); // MAKE SURE THAT THESE BOTH WORK WHEN LOGGING IN!

    if ($checkHash === false) {
        header("location: ../pages/login.php?error=wronglogin");
        exit();
    }
    else if ($checkPwd === true) {
        session_start();
        $_SESSION["user_id"] = $emailExists["user_id"]; // REMEMBER TO SEARCH THIS ONE UP AND MAKE SURE IT IS APPLICABLE IN DATABASE!!!
        header("location: ../index.html"); 
        exit();
    }


