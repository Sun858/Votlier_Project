<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}
?>

<!-- User login page with Show/Hide Hoover SideBar-->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login Page </title>
    <link rel="stylesheet" href="../Assets/css/userdashboard.css">
</head>

<body>

    <div class="sidebar">

        <div class="sidebar-top-bar">
            <ion-icon name="person-circle-outline" class="voter-icon"></ion-icon>
            <h3>Votify</h3>
        </div>

        <div class="sidebar-Navigation">
            <a href="Userlogin.html"><ion-icon name="home"></ion-icon> <span> Home</span></a>
            <a href="#"><ion-icon name="people"></ion-icon> <span> Profile</span></a>
            <a href="#"><ion-icon name="checkmark-done-circle"></ion-icon> <span> Election</span></a>
            <a href="#"><ion-icon name="eye"></ion-icon> <span> Result</span></a>
            <a href="#"><ion-icon name="cog-sharp"></ion-icon> <span> Setting </span></a>
        </div>

        <div class="sidebar-footer">
            <a href="../includes/logout.php"><ion-icon name="close-circle-sharp"></ion-icon><span> Sign Out </span></a>
 
            </div>
        </div>
    </div>
    
    <div class="content">
        <h1>Welcome to User Login Page</h1>
        <p>Hover over the sidebar to see the menu items.</p>
    </div>
    
   

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>