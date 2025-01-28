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
    header("location: ../pages/signup.html?error=stmtfailed");
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
function createUser($conn, $firstName, $middleName, $lastName, $email, $password, $confirmPassword) {
    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, hash_password, salt, iterations)
                        VALUES (?, ?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        header("location: ../pages/signup.html?error=stmtfailed");
            exit();
       }

       $salt = random_bytes(16); // 16 bytes = 128 bits
       $iterations = 100000;
       $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true); // 32 bytes = 256 bits
   
       // Encrypt PII (names and email)
       $encryptionKey = random_bytes(32); // Generate a 256-bit encryption key
       $cipher = "aes-256-cbc";
       $iv = random_bytes(openssl_cipher_iv_length($cipher));
   
       $encryptedFirstName = openssl_encrypt($firstName, $cipher, $encryptionKey, 0, $iv);
       $encryptedMiddleName = openssl_encrypt($middleName, $cipher, $encryptionKey, 0, $iv);
       $encryptedLastName = openssl_encrypt($lastName, $cipher, $encryptionKey, 0, $iv);
       $encryptedEmail = openssl_encrypt($email, $cipher, $encryptionKey, 0, $iv);

       mysqli_stmt_bind_param($stmt, "ssssssi", $encryptedFirstName, $encryptedMiddleName, $encryptedlastName, $encryptedEmail, $hash, $salt, $iterations);
       mysqli_stmt_execute($stmt);
       mysqli_stmt_close($stmt);

       header("location: ../pages/signup.html?error=none");
       exit();

} 