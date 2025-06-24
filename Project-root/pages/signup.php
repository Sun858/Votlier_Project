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
// This code handles displaying error or success messages based on URL parameters,
// utilizing a styled popup with auto-close and manual close functionality.
if (isset($_GET["error"])) {
    // Inline CSS for Error-Message styling. This defines the look and feel of the popup.
    echo '
    <style>
        .error-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: #ffebee;
            border-left: 4px solid #f44336;
            color: #d32f2f;
            padding: 20px 40px 20px 30px; /* Extra right padding for close button */
            border-radius: 4px;
            font-family: Arial, sans-serif;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            z-index: 1000;
            text-align: center;
            max-width: 80%;
            opacity: 1;
            transition: opacity 0.5s ease-out;
            /* Added for success message styling */
            box-sizing: border-box; /* Include padding and border in the element\'s total width and height */
        }
        .error-popup.success {
            background-color: #e8f5e9; /* Light green for success */
            border-left-color: #4CAF50; /* Green border for success */
            color: #2E7D32; /* Darker green text for success */
        }
        .error-popup p {
            margin: 0;
            font-weight: bold;
            font-size: 18px;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            cursor: pointer;
            font-size: 20px;
            color: #d32f2f; /* Error close button color */
            background: none;
            border: none;
            padding: 0 5px;
        }
        .error-popup.success .close-btn {
            color: #2E7D32; /* Success close button color */
        }
        .close-btn:hover {
            color: #9a0007; /* Error hover color */
        }
        .error-popup.success .close-btn:hover {
            color: #1B5E20; /* Success hover color */
        }
    </style>
    ';

    
    // Display the error/success popup with a close button for a better user experience.
    echo '<div class="error-popup" id="errorPopup">
            <button class="close-btn" onclick="closePopup()">×</button>';

    /**  This code here is a manual check of the url, which checks if any of the keywords are present.
    *If they are, the echo message is displayed.
    */
    switch ($_GET["error"]) {
        case "emptyinput":
            echo '<p>⚠️ Fill in all required fields.</p>';
            break;
        case "invalidname":
            echo '<p>⚠️ Invalid first or last name!</p>';
            break;
        case "invalidemail":
            echo '<p>⚠️ Invalid email!</p>';
            break;
        case "passwordsdontmatch":
            echo '<p>⚠️ Passwords don\'t match!</p>';
            break;
        case "userexists":
            echo '<p>⚠️ User already exists!</p>';
            break;
        case "stmtfailed":
            echo '<p>⚠️ Something went wrong, please try again later!</p>';
            break;
        case "weakpassword":
            echo '<p>⚠️ Password must be at least 8 characters long, include at least one uppercase letter and one number.</p>';
            break;
        case "ratelimited":
            echo '<p>⚠️ You have run out of chances, try again later!</p>';
            break;
        case "none": 
            echo '<p>✅ You have signed up successfully!</p>';
            break;
        default:
            echo '<p>⚠️ An unexpected error has occurred.</p>';
            break;
    }
    echo '</div>'; 

    // JavaScript for both auto-close and manual close. This makes the popup interactive for the user
    echo '
    <script>
        function closePopup() {
            var popup = document.getElementById("errorPopup");
            popup.style.opacity = "0"; 
            setTimeout(function() {
                popup.style.display = "none"; 
            }, 500); 
        }

        // Auto-close after 5 seconds (5000 milliseconds)
        setTimeout(closePopup, 5000);
    </script>
    ';
}
?>
    <footer>
        <p>&copy; 2025 Votify. All rights reserved.</p>
    </footer>
</body>
</html>