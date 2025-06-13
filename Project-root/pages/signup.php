<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="../Assets/css/signup.css">

</head>
<body>
    <h1>Let's create an Account</h1>
    <form action="../includes/submit-form.php" method="POST">

        <label for="first-name">First Name:</label>
        <input type="text" id="first-name" name="first-name" required>
          
        <label for="middle-name">Middle Name:</label>
        <input type="text" id="middle-name" name="middle-name">
          
        <label for="last-name">Last Name:</label>
        <input type="text" id="last-name" name="last-name" required>
                    
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <label for="confirm-password">Confirm Password:</label>
        <input type="password" id="confirm-password" name="confirm-password" required>

        <input type="submit" value="Sign Up">
    </form>
    <a href="../pages/login.php" class="back-to-login-link">Back to Login</a>

<?php
    if (isset($_GET["error"])) {
        if ($_GET["error"] == "emptyinput") {
            echo "<p> Fill in all required fields.</p>";
        }
        else if ($_GET["error"] == "invalidname") {
            echo "<p>Invalid first or last name!</p>";
        }
        else if ($_GET["error"] == "invalidemail") {
            echo "<p>Invalid email!</p>";
        }
        else if ($_GET["error"] == "passwordsdontmatch") {
            echo "<p>Passwords dont match!</p>";
        }
        else if ($_GET["error"] == "userexists") {
            echo "<p>User already exists!</p>";
        }
        else if ($_GET["error"] == "stmtfailed") {
            echo "<p>Something went wrong, try again later!</p>";
        }
        else if ($_GET["error"] == "none") {
            echo "<p>You have signed up!</p>";
        }
    }
?>




</body>
</html>
