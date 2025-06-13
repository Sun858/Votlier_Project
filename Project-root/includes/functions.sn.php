<?php

function emptyInputSignup($firstName, $lastName, $email, $password, $confirmPassword) {
    return empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword);
}

function invalidName($firstName, $lastName) {
    return !preg_match("/^[a-zA-Z-' ]*$/", $firstName) || !preg_match("/^[a-zA-Z-' ]*$/", $lastName);
}

function invalidEmail($email) {
    return !filter_var($email, FILTER_VALIDATE_EMAIL);
}

function pwdMatch($password, $confirmPassword) {
    return $password !== $confirmPassword;
}

function emailExists($conn, $email) {
    $sql = "SELECT * FROM users;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    mysqli_stmt_execute($stmt);
    $resultstmt = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($resultstmt)) {
        $encryptionKey = $row["encryption_key"];
        $iv = $row["iv"];
        $cipher = "aes-256-cbc";

        $decryptedEmail = openssl_decrypt($row["email"], $cipher, $encryptionKey, 0, $iv);

        if ($decryptedEmail === $email) {
            mysqli_stmt_close($stmt);
            return $row;
        }
    }

    mysqli_stmt_close($stmt);
    return false;
}

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
    
    if (!mysqli_stmt_execute($stmt)) {
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    mysqli_stmt_close($stmt);
    header("location: ../pages/signup.php?error=none");
    exit();
}

function emptyInputLogin($email, $password) {
    return empty($email) || empty($password);
}

function loginUser($conn, $email, $password) {
    $emailExists = emailExists($conn, $email);

    if ($emailExists === false) {
        error_log("Login failed: Email not found for $email");
        header("location: ../pages/login.php?error=emailnotfound");
        exit();
    }

    $pwdHashed = $emailExists["hash_password"];
    $salt = $emailExists["salt"];
    $iterations = $emailExists["iterations"];
    $inputHash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    if (!hash_equals($pwdHashed, $inputHash)) {
        error_log("Login failed: Incorrect password for $email");
        header("location: ../pages/login.php?error=incorrectpassword");
        exit();
    }

    $encryptionKey = $emailExists["encryption_key"];
    $iv = $emailExists["iv"];
    $cipher = "aes-256-cbc";
    $decryptedEmail = openssl_decrypt($emailExists["email"], $cipher, $encryptionKey, 0, $iv);

    // Removed session_start line here because of redundancy, don't want to overwrite the session data.
    $_SESSION["user_id"] = $decryptedEmail;
    header("location: ../pages/userdashboard.php");
    exit();
}
