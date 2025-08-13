<?php
session_start();
// Include the database connection file
require_once '../DatabaseConnection/config.php';

// Fetch all FAQs from the database
$sql = "SELECT question, answer FROM faqs ORDER BY date_created DESC";
$result = $conn->query($sql);
$faqs = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation</title>

    <!-- Link to Cloudflare-hosted font CSS file -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../Assets/css/FAQs.css">

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
    <main>
        <!-- Dynamic FAQ section starts here -->
        <div class="faq-section">
            <h2>Frequently Asked Questions</h2>
            <?php if (!empty($faqs)): ?>
                <?php foreach ($faqs as $faq): ?>
                    <div class="accordion-item">
                        <button class="accordion-button">
                            <span><?php echo htmlspecialchars($faq['question']); ?></span>
                            <span class="accordion-icon"><i class="fas fa-chevron-down"></i></span>
                        </button>
                        <div class="accordion-content">
                            <p><?php echo htmlspecialchars($faq['answer']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-faqs">No FAQs available at this time.</p>
            <?php endif; ?>
        </div>
        <!-- Dynamic FAQ section ends here -->

    </main>

    <footer>
        <div class="footer-wave">
            <svg viewBox="0 0 1200 60" preserveAspectRatio="none" style="height: 40px;" width="200%">
                <path d="M0,0V23.14c23.9,11.1,51.8,16.08,79,14,35.18-2.68,68.16-16.66,103.4-18.75C219.32,16.22,256.17,26.84,291.5,36.03c34.63,9,69.15,12.44,104.7,6.54,18.08-3,34.93-8.92,52.23-14.67C494.74,12.5,556.5-7.14,600,26.23V0Z"
                    opacity=".25" fill="rgba(241, 237, 237, 0.15)"></path>
                <path d="M0,0V7.91C6.5,18.46,13.82,28.43,23.85,36.03,49.7,55.63,82.5,55.5,112.29,45.79c15.58-5.08,30.04-13.03,44.84-19.9,20.46-9.5,42.37-23,65.41-24.84,18.13-1.43,35.45,4.71,49.3,15.78,15.88,12.7,31.16,31,51.82,36.5,20.22,5.39,40.68-3.34,59.57-12.14s37.58-19.5,58.46-21.52c29.87-2.93,56.64,11.44,84.45,19.42,15.1,4.33,29.5,3.09,43.54-3.75,11.22-5.44,24-13.46,30.33-24.62V0Z"
                    opacity=".5" fill="rgba(241, 237, 237, 0.15)"></path>
                <path d="M0,0V2.82C74.97,29.5,157.04,35.66,237.92,21.28c21.5-3.82,42.12-10.06,63.81-13.23,29.5-4.32,56.24,6.12,82.78,17.7C413.96,38.61,443,47.62,475.6,45c43.26-3.5,86.23-22.86,124.4-42.4V0Z"
                    fill="rgba(241, 237, 237, 0.15)"></path>
            </svg>
        </div>

        <div class="footer-container">
            <div class="footer-section">
                <h3>Quicklinks</h3>
                <ul class="footer-links">
                    <li><a href="../index.html"> Home</a></li>
                    <li><a href="../pages/login.php"> Login</a></li>
                    <li><a href="../pages/contact.html"> Contact </a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Resources</h3>
                <ul class="footer-links">
                    <li><a href="../pages/FAQs.php">FAQs</a></li>
                    <li><a href="../pages/Documentation.html">Documentation</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Company</h3>
                <ul class="footer-links">
                    <li><a href="../pages/Aboutus.html">About Us</a></li>
                    <li><a href="../pages/Team-members.html">Team Members</a></li>
                </ul>
            </div>
        </div>

        <div class="social-links">
            <a href="https://www.facebook.com/profile.php?id=61574787246840" target="_blank" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
            <a href="mailto:contact@votify.com" aria-label="Email"><i class="fas fa-envelope"></i></a>
        </div>

        <div class="footer-text">
            <p>Votify acknowledges and pays respect to the past, present and future Traditional Custodians and Elders of this nation and the continuation of cultural, spiritual and educational practices of Aboriginal and Torres Strait Islander peoples.</p>
        </div>

        <div class="footer-bottom">
            <div class="footer-legal">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Cookie Policy</a>
                <a href="#">Accessibility</a>
                <a href="#">Sitemap</a>
            </div>
            <div class="footer-copyright">
                <p>© 2025
                    <span class="highlight">Votify.</span>
                    All rights reserved. Designed with <i class="fas fa-heart"></i> in Australia
                </p>
            </div>
        </div>
    </footer>

    <script src="../Assets/js/Functions.js"></script>
    <script>
        initAccordions();
        initSocialIcons();
    </script>

</body>

</html>