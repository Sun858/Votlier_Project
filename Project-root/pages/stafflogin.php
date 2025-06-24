<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Votify Staff Login</title>
  <link rel="stylesheet" href="../Assets/css/loginTest.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

</head>

<body>

  <header>
    <h1>Votify</h1>
  </header>

  <nav>
    <a href="../index.html"><i class="fas fa-home"></i> Home</a>
    <a href="../pages/login.php"><i class="fas fa-user"></i> Login</a>
    <a href="../pages/contact.html"><i class="fa fa-phone"></i> Contact </a>
  </nav>

  <div class="container">
    <h2>Staff Authentication Votify</h2>

    <!-- Login Form -->
    <!-- The action attribute of the form points to the login.php file -->
    <form id="loginForm" action="../includes/admin-login-inc.php" method="POST">
      <div class="form-group">
        <label for="loginEmail">Email:</label>
        <input type="email" id="loginEmail" name="loginEmail" required>
      </div>

      <div class="form-group">
        <label for="loginPassword">Password:</label>
        <input type="password" id="loginPassword" name="loginPassword" required>
      </div>

      <button type="submit" name="submit">Login</button>
      
      <a href="../pages/forgot_password.html" class="forgot-password-link">Forgot Password?</a>

    </form>

  </div>
<?php
// This code will check for an "error parameter in the HTML, which occurs when an error is reported in functions.sn.php.
if (isset($_GET["error"])) {
    // Inline Css for Error-Message, the styling of the error message 
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
            color: #d32f2f;
            background: none;
            border: none;
            padding: 0 5px;
        }
        .close-btn:hover {
            color: #9a0007;
        }
    </style>
    ';

    // Display the error popup with a close button for a better user experience.
    echo '<div class="error-popup" id="errorPopup">
            <button class="close-btn" onclick="closePopup()">×</button>';
    // This code here is a manual check of the url, which checks if any of the keywords are present. If they are, the echo is performed and transformed by the styling.
    if ($_GET["error"] == "emptyinput") {
        echo '<p>⚠️ Fill all fields in!</p>';
    } else if ($_GET["error"] == "emailnotfound") {
        echo '<p>⚠️ Incorrect login information!</p>';
    } else if ($_GET["error"] == "incorrectpassword") {
        echo '<p>⚠️ Incorrect password!</p>';
    } else if ($_GET["error"] == "ratelimited") {
        echo '<p>⚠️ You have run out of chances, try again later!</p>';
    }
    else {
        echo '<p>⚠️ Incorrect details or an unexpected error has occurred.</p>';
    }
    echo '</div>';

    // JavaScript for both auto-close and manual close. This would make the popup interactive for the user.
    echo '
    <script>
        function closePopup() {
            var popup = document.getElementById("errorPopup");
            popup.style.opacity = "0";
            setTimeout(function() { 
                popup.style.display = "none"; 
            }, 500);
        }
        
        // Auto-close after 5 seconds
        setTimeout(closePopup, 5000);
    </script>
    ';
}
?>
  <footer>
    <p>&copy; 2025 My Website. All rights reserved.</p>
  </footer>

  <!-- js/loginscript.js -->
  <script src="../Assets/js/loginscript.js">
  </script>

</body>
</html>