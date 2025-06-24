<?php

// Debug environment variables
error_log("MASTER_EMAIL_ENCRYPTION_KEY from getenv: " . (getenv('MASTER_EMAIL_ENCRYPTION_KEY') ?: 'NOT SET'));
error_log("BLIND_INDEX_SECRET_KEY from getenv: " . (getenv('BLIND_INDEX_SECRET_KEY') ?: 'NOT SET'));

// This is the SECURE KEY MANAGEMENT SECTION - Where we load the encryption keys from environmental variables
if (!defined('TRUE_MASTER_EMAIL_ENCRYPTION_KEY')) {
    $mek_base64 = getenv('MASTER_EMAIL_ENCRYPTION_KEY'); // Matches .env variable name
    if (!$mek_base64) {
        error_log("CRITICAL ERROR: MASTER_EMAIL_ENCRYPTION_KEY not found in environment variables. Check .env and Docker config.");
        die("Error Ocurred: Encryption key missing."); // Kill processes if encryption key isn't found.
    }
    define('TRUE_MASTER_EMAIL_ENCRYPTION_KEY', base64_decode($mek_base64));
}

if (!defined('TRUE_BLIND_INDEX_SECRET_KEY')) {
    $bisk_base64 = getenv('BLIND_INDEX_SECRET_KEY'); // Matches .env variable name
    if (!$bisk_base64) {
        error_log("CRITICAL ERROR: BLIND_INDEX_SECRET_KEY not found in environment variables. Check .env and Docker config.");
        die("Error Ocurred: Blind index key missing."); // Kill processes if encryption key isn't found.
    }
    define('TRUE_BLIND_INDEX_SECRET_KEY', base64_decode($bisk_base64));
}

// Input validation functions
function emptyInputSignup($firstName, $lastName, $email, $password, $confirmPassword) {
    // Checks if any of the required signup fields are empty.
    return empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword);
}

function invalidName($firstName, $lastName) {
    // Checks if first and last names contain only allowed characters (letters, hyphens, apostrophes, spaces).
    return !preg_match("/^[a-zA-Z-' ]*$/", $firstName) || !preg_match("/^[a-zA-Z-' ]*$/", $lastName);
}

function invalidEmail($email) {
    // Validates the email format using PHP's built-in filter.
    return !filter_var($email, FILTER_VALIDATE_EMAIL);
}

function passwordStrength($password) {
    // At least 8 characters, one uppercase, and one number
    return !preg_match('/^(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}


function pwdMatch($password, $confirmPassword) {
    // Checks if the password and confirm password fields match.
    return $password !== $confirmPassword;
}

function emailExists($conn, $email) {
    // Directly use the defined constants for keys, as they are global constants.
    $blindIndexSecretKey = TRUE_BLIND_INDEX_SECRET_KEY;
    $cipher = "aes-256-cbc";
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;

    // Generate the blind index for the inputted email.
    // hash_hmac with 'true' returns raw binary, which should be stored as BINARY/VARBINARY.
    $inputEmailBlindIndex = hash_hmac('sha256', $email, $blindIndexSecretKey, true);

    // Prepare the SQL statement to query the database using the blind index with a limit of 1
    $sql = "SELECT user_id, first_name, middle_name, last_name, email, hash_password, salt, iterations, iv FROM users WHERE email_blind_index = ? LIMIT 1;";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        // Log detailed error for debugging, but provide generic error to user.
        error_log("emailExists prepare failed: " . mysqli_stmt_error($stmt));
        header("location: ../pages/signup.php?error=stmtfailed");
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $inputEmailBlindIndex); // Bind the binary blind index as a string - had issues here but s seems to work.
   

    mysqli_stmt_execute($stmt);
    $resultstmt = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultstmt);

    mysqli_stmt_close($stmt);

    if ($row) {
        // If a row was found by blind index, perform the cryptographic decryption and exact email match.
        $storedEncryptedEmail = $row["email"];
        $storedIv = $row["iv"]; // Retrieve the IV stored for this user's email

        // Decrypt the stored email using the encryption key and its unique stored IV.
        $decryptedEmail = openssl_decrypt($storedEncryptedEmail, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

        if ($decryptedEmail === $email) {
            // Decrypt names for display or further processing.
            $row['first_name'] = openssl_decrypt($row['first_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['middle_name'] = openssl_decrypt($row['middle_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['last_name'] = openssl_decrypt($row['last_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

            // Remove sensitive encrypted/binary data from the row before returning it.
            unset($row["email"]); 
            unset($row["iv"]);    
            unset($row["email_blind_index"]); 

            return $row; // Return the user data (including decrypted names, password hash, salt, iterations)
        }
    }

    return false; // Email not found or decrypted email didn't match
}


function adminEmailExists($conn, $email) {
    $blindIndexSecretKey = TRUE_BLIND_INDEX_SECRET_KEY;
    $cipher = "aes-256-cbc";
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;

    $inputEmailBlindIndex = hash_hmac('sha256', $email, $blindIndexSecretKey, true);

    $sql = "SELECT admin_id, first_name, middle_name, last_name, email, hash_password, salt, iterations, iv FROM administration WHERE email_blind_index = ? LIMIT 1;";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        error_log("adminEmailExists prepare failed: " . mysqli_stmt_error($stmt));
        return false;
    }

    mysqli_stmt_bind_param($stmt, "s", $inputEmailBlindIndex);
    mysqli_stmt_execute($stmt);
    $resultstmt = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($resultstmt);
    mysqli_stmt_close($stmt);

    if ($row) {
        $storedEncryptedEmail = $row["email"];
        $storedIv = $row["iv"];
        $decryptedEmail = openssl_decrypt($storedEncryptedEmail, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

        if ($decryptedEmail === $email) {
            $row['first_name'] = openssl_decrypt($row['first_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['middle_name'] = openssl_decrypt($row['middle_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);
            $row['last_name'] = openssl_decrypt($row['last_name'], $cipher, $encryptionKey, OPENSSL_RAW_DATA, $storedIv);

            unset($row["email"]);
            unset($row["iv"]);
            unset($row["email_blind_index"]);

            return $row;
        }
    }

    return false;
}


function createUser($conn, $firstName, $middleName, $lastName, $email, $password) {
    // Check for duplicate email BEFORE preparing the SQL INSERT statement.
    if (emailExists($conn, $email) !== false) {
        header("location: ../pages/signup.php?error=emailtaken"); // Redirect if email already exists
        exit();
    }

    // Statement is now prepared AFTER we do the checks, it makes more sense this way.
    $sql = "INSERT INTO users (first_name, middle_name, last_name, email, email_blind_index, hash_password, salt, iterations, iv)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
    $stmt = mysqli_stmt_init($conn);

    if (!mysqli_stmt_prepare($stmt, $sql)) {
        error_log("createUser prepare failed: " . mysqli_stmt_error($stmt));
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    // Generate a unique salt for password hashing.
    $salt = random_bytes(16);
    // Define iteration count for PBKDF2; a higher number means more secure but slower.
    $iterations = 100000;
    // Hash the password using PBKDF2 with SHA256. The 'true' flag returns raw binary.
    $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    // Encryption deets
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $cipher = "aes-256-cbc";
    // IV must be different between each use.
    $iv = random_bytes(openssl_cipher_iv_length($cipher));

    // Encrypt sensitive fields. OPENSSL_RAW_DATA ensures raw binary output.
    $encryptedFirstName = openssl_encrypt($firstName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedMiddleName = openssl_encrypt($middleName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedLastName = openssl_encrypt($lastName, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);
    $encryptedEmail = openssl_encrypt($email, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);

    // Generate the blind index for the email.
    $blindIndexSecretKey = TRUE_BLIND_INDEX_SECRET_KEY;
    $emailBlindIndex = hash_hmac('sha256', $email, $blindIndexSecretKey, true);


    // This would bind all the parameters for the statement we are going to make. This is good against injection.
    mysqli_stmt_bind_param($stmt, "sssssssis",
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
        error_log("createUser execute failed: " . mysqli_stmt_error($stmt));
        header("location: ../pages/signup.php?error=stmtfailed");
        exit();
    }

    mysqli_stmt_close($stmt);
    header("location: ../pages/signup.php?error=none"); // Successful redirect, means there are no errors. Might have to remove these sorts of error logs.
    exit();
}

// Login functions
function emptyInputLogin($email, $password) {
    // Checks if email or password fields are empty during login.
    return empty($email) || empty($password);
}

function loginUser($conn, $email, $password) {
    // Attempt to find the user by email using the emailExists function.
    $emailExistsArray = emailExists($conn, $email);

    if ($emailExistsArray === false) {
        // Log the failure. So far I have two different types of logs and thats kinda bad for the user experience, well one of them is lmao.
        error_log("Login failed: Email not found for $email");
        header("location: ../pages/login.php?error=invalidcredentials");
        exit();
    }

    // Retrieve hashed password, salt, and iterations from the database.
    $pwdHashed = $emailExistsArray["hash_password"];
    $salt = $emailExistsArray["salt"];
    $iterations = $emailExistsArray["iterations"];

    // Hash the inputted password using the retrieved salt and iterations.
    $inputHash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    // Compare the newly generated hash with the stored hash using hash_equals.
    if (!hash_equals($pwdHashed, $inputHash)) {
        error_log("Login failed: Incorrect password for $email");
        header("location: ../pages/login.php?error=invalidcredentials"); // Another header... I need to stop using that and will probably remove these in the future.
        exit();
    }

    // Start the session and set session variables upon successful login.
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    } // this ensures it is only done once, and the below allows some info to go through and into the session for display to the user.
    $_SESSION["user_id"] = $emailExistsArray["user_id"];
    $_SESSION["userfirstname"] = $emailExistsArray["first_name"];
    $_SESSION["userlastname"] = $emailExistsArray["last_name"];

    // Redirect to the user dashboard.
    header("location:../pages/User_Home.php");
    exit();
}

function loginAdmin($conn, $email, $password) {
    $adminData = adminEmailExists($conn, $email);

    if ($adminData === false) {
        header("location: ../pages/stafflogin.php?error=invalidcredentials");
        exit();
    }

    $pwdHashed = $adminData["hash_password"];
    $salt = $adminData["salt"];
    $iterations = $adminData["iterations"];
    $inputHash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

    if (!hash_equals($pwdHashed, $inputHash)) {
        header("location: ../pages/stafflogin.php?error=invalidcredentials");
        exit();
    }

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION["admin_id"] = $adminData["admin_id"];
    $_SESSION["adminfirstname"] = $adminData["first_name"];
    $_SESSION["adminlastname"] = $adminData["last_name"];

    header("location: ../pages/Admin_Home.php");
    exit();
}