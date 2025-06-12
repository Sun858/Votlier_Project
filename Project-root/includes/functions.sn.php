<?php

// This is the SECURE KEY MANAGEMENT SECTION - Where we load the encryption keys from an environmental variable
if (!defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) {
    $mek_base64 = getenv('MASTER_EMAIL_ENCRYPTION_KEY');
    if (!$mek_base64) {
        error_log("MASTER_EMAIL_ENCRYPTION_KEY not found in .env");
        die("Error Ocurred"); // Kill processes if encryption key isn't found.
    } define ('TRUE_MASTER_EMAIL_ENCRYPTION_KEY', base64_decode($mek_base64));
}

if (!defined('TRUE_BLIND_INDEX_SECRET_KEY')) {
    $bisk_base64 = getenv('BLIND_INDEX_SECRET_KEY');
    if (!$bisk_base64) {
        error_log("BLIND_INDEX_SECRET_KEY not found in .env");
        die("Error Ocurred"); // Kill processes if encryption key isn't found.
    } define ('TRUE_BLIND_INDEX_SECRET_KEY', base64_decode($bisk_base64));
}

// This entire segment above would grab the key from the .env and decode it to be used as the encryption key for functions below.



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

        $decryptedEmail = openssl_decrypt($row["email"], $cipher, $encryptionKey, OPENSSL_RAW_DATA, 0, $iv);

        if ($decryptedEmail === $email) {
            mysqli_stmt_close($stmt);
            return $row;
        }
    }

    mysqli_stmt_close($stmt);
    return false;
}

function createUser($conn, $firstName, $middleName, $lastName, $email, $password) {
    // Insert into the database is below. NGL the structure of my code isn't the greatest lmao this should be lower I reckon.
    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, email_blind_index, hash_password, salt, iterations, iv)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    $salt = random_bytes(16); // This is a unique salt for password hashing
    $iterations = 100000; // Iteration count for PBKDF2 hashing method
    $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    $encryptionKey = TRUE_MASTER_ENCRYPTION_KEY; // This would be loaded from the .env
    $cipher = "aes-256-cbc"; // Very self explanatory
    $iv = random_bytes(openssl_cipher_iv_length($cipher));

    // Encrypt the fields with the protocol, the key, the OPTION (openssl_raw_data) and IV
    $encryptedFirstName = openssl_encrypt($firstName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedMiddleName = openssl_encrypt($middleName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedLastName = openssl_encrypt($lastName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedEmail = openssl_encrypt($email, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);

    // Now a blind index for the email for lookup later. This is added here because this function is first needed to enable the function loop.
    $blindIndexSecretKey = TRUE_BLIND_INDEX_SECRET_KEY; // Loaded from the .env
    $emailBlindIndex = hash_hmac('sha256', $email, $blindIndexSecretKey, true);

    // Binding all parameters for insertion
    mysqli_stmt_bind_param($stmt, "sssssssss", $encryptedFirstName, $encryptedMiddleName, $encryptedLastName, $encryptedEmail, $emailBlindIndex, $hash, $salt, $iterations, $iv);
    
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
    $decryptedEmail = openssl_decrypt($emailExists["email"], $cipher, $encryptionKey, OPENSSL_RAW_DATA, 0, $iv);

    // Removed session_start line here because of redundancy, don't want to overwrite the session data.
    $_SESSION["user_id"] = $decryptedEmail;
    header("location: ../pages/userdashboard.php");
    exit();
}
