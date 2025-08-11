<?php
session_start();
// This is the security page for rate limiting and timeout. 15Min is currently set
require_once '../includes/security.sn.php';
require_once '../DatabaseConnection/config.php';
require_once '../includes/election_stats.php';
checkSessionTimeout(); // Calling the function for the timeout, it redirects to login page and ends the session.

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Handle FAQ form submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/faq_handler.php';
}

// Fetch all FAQs from the database to display in the table
//This function is written in the includes/election_stats.php file
$faqs = getAllFAQs($conn);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ionicon Sidebar Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_FAQ.css">

</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="Admin_Home.php">
                    <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                    <span class="text">Home</span>
                </a></li>
                <li><a href="Admin_Profile.php">
                    <span class="icon"><ion-icon name="people-outline"></ion-icon></span>
                    <span class="text">Profile</span>
                </a></li>
                <li><a href="Admin_Election.php">
                    <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                    <span class="text">Election</span>
                </a></li>
                <li><a href="Admin_Result.php">
                    <span class="icon"><ion-icon name="eye-outline"></ion-icon></span>
                    <span class="text">Result</span>
                </a></li>
                <li><a href="Admin_FAQ.php">
                    <span class="icon"><ion-icon name="help-outline"></ion-icon></span>
                    <span class="text">Manage FAQs</span>
                </a></li>
                <li><a href="Admin_Settings.php">
                    <span class="icon"><ion-icon name="settings-outline"></ion-icon></span>
                    <span class="text">Settings</span>
                </a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../includes/logout.php" class="footer-link signout-link">
                <span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
                <span class="text">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Welcome to Voter Dashboard</h1>
            <p>Explore your data and manage your business efficiently</p>
        </header>

      <section class="admin-faq-container">
            <h2>Manage FAQs</h2>

            <form id="faq-form" action="../pages/Admin_FAQ.php" method="POST" class="faq-form">
                <input type="hidden" name="faq_id" id="faq_id">
                <div class="form-group">
                    <label for="question">Question:</label>
                    <input type="text" id="question" name="question" required>
                </div>
                <div class="form-group">
                    <label for="answer">Answer:</label>
                    <textarea id="answer" name="answer" rows="4" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="action" value="add" id="submit-btn" class="btn btn-primary">Add FAQ</button>
                    <button type="button" id="cancel-btn" class="btn btn-secondary" style="display: none;">Cancel Edit</button>
                </div>
            </form>

            <div class="faq-list">
                <h3>Current FAQs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Question</th>
                            <th>Answer</th>
                            <th>Date Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($faqs)): ?>
                            <?php foreach ($faqs as $faq): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($faq['question']); ?></td>
                                    <td><?php echo htmlspecialchars($faq['answer']); ?></td>
                                    <td><?php echo htmlspecialchars($faq['date_created']); ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick='editFaq(<?php echo json_encode($faq); ?>)'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form action="../pages/Admin_FAQ.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="faq_id" value="<?php echo htmlspecialchars($faq['faq_id']); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-delete">
                                                <i class="fas fa-trash-alt"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center;">No FAQs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

    <!-- JavaScript to handle the edit and cancel actions for FAQs -->
    <script>
        function editFaq(faq) {
        // Populate the form fields with the selected FAQ data
            document.getElementById('faq_id').value = faq.faq_id;
            document.getElementById('question').value = faq.question;
            document.getElementById('answer').value = faq.answer;
            document.getElementById('submit-btn').value = 'update';
            document.getElementById('submit-btn').textContent = 'Update FAQ';
            document.getElementById('cancel-btn').style.display = 'inline-block';
        }
        // Reset the form when the cancel button is clicked
        document.getElementById('cancel-btn').addEventListener('click', () => {
            document.getElementById('faq-form').reset();
            document.getElementById('faq_id').value = '';
            document.getElementById('submit-btn').value = 'add';
            document.getElementById('submit-btn').textContent = 'Add FAQ';
            document.getElementById('cancel-btn').style.display = 'none';
        });
    </script>
</body>
</html>