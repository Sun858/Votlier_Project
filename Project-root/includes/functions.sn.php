<?php

// Debug environment variables
error_log("MASTER_EMAIL_ENCRYPTION_KEY from getenv: " . (getenv('MASTER_EMAIL_ENCRYPTION_KEY') ?: 'NOT SET'));
error_log("BLIND_INDEX_SECRET_KEY from getenv: " . (getenv('BLIND_INDEX_SECRET_KEY') ?: 'NOT SET'));

// This is the SECURE KEY MANAGEMENT SECTION - Where we load the encryption keys from an environmental variable
if (!defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) {
    $mek_base64 = getenv('MASTER_EMAIL_ENCRYPTION_KEY'); // Matches .env variable name
    if (!$mek_base64) {
        error_log("CRITICAL ERROR: MASTER_EMAIL_ENCRYPTION_KEY not found in environment variables. Check .env and Docker config.");
        die("Error Ocurred: Encryption key missing."); // Kill processes if encryption key isn't found.
    } define ('TRUE_MASTER_EMAIL_ENCRYPTION_KEY', base64_decode($mek_base64));
}

if (!defined('TRUE_BLIND_INDEX_SECRET_KEY')) {
    $bisk_base64 = getenv('BLIND_INDEX_SECRET_KEY'); // Matches .env variable name
    if (!$bisk_base64) {
        error_log("CRITICAL ERROR: BLIND_INDEX_SECRET_KEY not found in environment variables. Check .env and Docker config.");
        die("Error Ocurred: Blind index key missing."); // Kill processes if encryption key isn't found.
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
    $blindIndexSecretKey = TRUE_BLIND_INDEX_SECRET_KEY; // Loaded from the .env.
    $cipher = "aes-256-cbc"; // The cipher method for encryption that will be used
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY; // Also loaded from the .env

    // Generate an index from the inputted email
    $inputEmailBlindIndex = hash_hmac('sha256', $email, $blindIndexSecretKey, true);

    // Querying the database using this index to find the email inputted by the user
    $sql = "SELECT user_id, first_name, middle_name, last_name, email, hash_password, salt, iterations, iv FROM users WHERE email_blind_index = ? LIMIT 1;";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        // Log the actual error for debugging
        error_log("emailExists prepare failed: " . mysqli_stmt_error($stmt));
        header("location: ../pages/signup.php?error=stmtfailed");
        return false;
    }

    mysqli_stmt_bind_param($stmt, "b", $inputEmailBlindIndex); //Binding the index created above
    mysqli_stmt_execute($stmt);
    $resultstmt = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultstmt);

    mysqli_stmt_close($stmt);
    if ($row) {
        // If a row was found we perform the following:
        $storedEncryptedEmail = $row["email"];
        $storedIv = $row["iv"]; // Retrieve the unique random IV stored for this users email

        // Decrypt the stored email using the encryption key and its unique stored IV with OPENSSL_RAW_DATA, so basically it decrypts the entry it finds.
        // USE $encryptionKey here, not $masterKey
        $decryptedEmail = openssl_decrypt($storedEncryptedEmail, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

        // Final check for email match.
        // This mitigates the risk of blind index collisions and confirms the email's authenticity.
        if ($decryptedEmail === $email) {
            // Decrypt names to return in plain text for display in the dashboard.
            // USE $encryptionKey here, not $masterKey
            $row['first_name'] = openssl_decrypt($row['first_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['middle_name'] = openssl_decrypt($row['middle_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['last_name'] = openssl_decrypt($row['last_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

            // Remove sensitive encrypted data/IV/index from the row before returning it to stop any data from being stolen.
            unset($row["email"]); // Remove encrypted email (plain email is verified and implicitly used)
            unset($row["iv"]);    // Remove IV
            unset($row["email_blind_index"]); // Remove blind index

            return $row; // Return the user data (including decrypted names, password hash, salt, iterations)
        }
    }

    return false;
}

function createUser($conn, $firstName, $middleName, $lastName, $email, $password) {
    // Check for duplicate email BEFORE preparing the SQL INSERT statement for better flow
    if (emailExists($conn, $email) !== false) { // Call emailExists function
        header("location: ../pages/signup.php?error=emailtaken"); // Redirect if email already exists
        exit();
    }

    // Prepare SQL statement AFTER the duplicate check
    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, email_blind_index, hash_password, salt, iterations, iv)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);
    if (!mysqli_stmt_prepare($stmt, $sql)) {
        // Log the actual error for debugging
        error_log("createUser prepare failed: " . mysqli_stmt_error($stmt));
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    $salt = random_bytes(16); // This is a unique salt for password hashing
    $iterations = 100000; // Iteration count for PBKDF2 hashing method
    $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY; // This would be loaded from the .env
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

    // Binding all parameters for insertion - CORRECTED BIND PARAM TYPES
    // Order: first_name, middle_name, last_name, email, email_blind_index, hash_password, salt, iterations, iv
    // Types: BLOB, BLOB, BLOB, BLOB, BLOB, BLOB, BLOB, INT, BLOB
    mysqli_stmt_bind_param($stmt, "bbbbbbisb",
        $encryptedFirstName,
        $encryptedMiddleName,
        $encryptedLastName,
        $encryptedEmail,
        $emailBlindIndex,
        $hash,
        $salt,
        $iterations,
        $iv
    );

    if (!mysqli_stmt_execute($stmt)) {
        // Log the actual error for debugging
        error_log("createUser execute failed: " . mysqli_stmt_error($stmt));
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
    $emailExistsArray = emailExists($conn, $email);

    if ($emailExistsArray === false) {
        error_log("Login failed: Email not found for $email");
        header("location: ../pages/login.php?error=emailnotfound");
        exit();
    }

    $pwdHashed = $emailExistsArray["hash_password"];
    $salt = $emailExistsArray["salt"];
    $iterations = $emailExistsArray["iterations"];

    // Use all grabbed info above to hash the password to compare in the database for verification.
    $inputHash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    // Compare the hash in the database to the hash created from input / check if they are the same.
    if (!hash_equals($pwdHashed, $inputHash)) {
        error_log("Login failed: Incorrect password for $email");
        header("location: ../pages/login.php?error=incorrectpassword");
        exit();
    }

    // Set session variables - CORRECTED user_id
    session_start(); // Ensure session is started before setting variables
    $_SESSION["user_id"] = $emailExistsArray["user_id"]; // Use the actual user_id from the array
    $_SESSION["userfirstname"] = $emailExistsArray["first_name"];
    $_SESSION["userlastname"] = $emailExistsArray["last_name"];
    header("location: ../pages/userdashboard.php");
    exit();
}
