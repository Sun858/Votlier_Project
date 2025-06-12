<?php
session_start();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Connect to your database
    $conn = new mysqli('localhost', 'root', '', 'voting_system');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if the admin user exists by email
    $stmt = $conn->prepare("SELECT * FROM administration WHERE email = ? AND hash_password = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $hashed_password = md5($password); // For testing only. Use password_hash() in production
    $stmt->bind_param("ss", $email, $hashed_password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();

        // Set session variables
        $_SESSION['admin_id'] = $admin['id']; // Assumes you still have a unique ID field
        $_SESSION['email'] = $admin['email'];
        $_SESSION['role'] = $admin['role']; // Should be 'administration'

        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid email or password.";
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Admin Login</h2>
    <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="POST" action="admin_login.php">
        <label for="email">Email:</label><br>
        <input type="email" name="email" required><br><br>

        <label for="password">Password:</label><br>
        <input type="password" name="password" required><br><br>

        <input type="submit" value="Login">
    </form>
</body>
</html>
