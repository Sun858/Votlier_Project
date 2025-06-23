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
    if (isset($_GET["error"])) {
      if ($_GET["error"] == "emptyinput") {
          echo "<p>Fill all fields in!</p>";
      } else if ($_GET["error"] == "emailnotfound") {
          echo "<p>Incorrect login information!</p>";
      } else if ($_GET["error"] == "incorrectpassword") {
          echo "<p>Incorrect password!</p>";
      } else if ($_GET["error"] == "ratelimited") {
        echo "<p>Too many login attempts. Try again later</p>";
      }    
      else {
        echo "<p>Incorrect details or an unexpected error has occurred.</p>";
    } 
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