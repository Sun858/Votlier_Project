<?php
session_start();
// Include the database connection file and security functions
require_once '../DatabaseConnection/config.php';
require_once '../includes/security.sn.php';

// Check if the user is an admin; if not, redirect to login page
// Assuming a session variable 'is_admin' is set upon successful admin login
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php");
    exit();
}

// Handle FAQ form submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/faq_handler.php';
    // After handling the request, redirect to prevent form resubmission
    header("Location: Admin_FAQ.php");
    exit();
}

// Fetch all FAQs from the database to display in the table
$sql = "SELECT id, question, answer, date_created FROM faqs ORDER BY date_created DESC";
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
    <title>Admin Dashboard - Manage FAQs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../Assets/css/Documentation.css">
    <link rel="stylesheet" href="../Assets/css/Admin_FAQ.css">
</head>
<body>
    <header>
        <h1>Admin Dashboard</h1>
    </header>
    <nav>
        <a href="Admin_Home.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="Admin_FAQ.php" class="active"><i class="fas fa-question-circle"></i> Manage FAQs</a>
        <a href="Admin_Election.php"><i class="fas fa-vote-yea"></i> Manage Elections</a>
        <a href="Admin_Result.php"><i class="fas fa-poll"></i> View Results</a>
        <a href="../includes/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
    <main>
        <section class="admin-faq-container">
            <h2>Manage FAQs</h2>

            <!-- Add/Edit FAQ Form -->
            <form id="faq-form" action="Admin_FAQ.php" method="POST" class="faq-form">
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

            <!-- FAQ List Table -->
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
                                        <button class="btn btn-edit" onclick="editFaq(<?php echo htmlspecialchars(json_encode($faq)); ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <form action="Admin_FAQ.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="faq_id" value="<?php echo $faq['id']; ?>">
                                            <button type="submit" name="action" value="delete" class="btn btn-delete">
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
    <footer>
        <!-- Footer content from your other pages, assuming it's the same -->
        <!-- You would typically include the footer content here -->
    </footer>
    <script>
        function editFaq(faq) {
            document.getElementById('faq_id').value = faq.id;
            document.getElementById('question').value = faq.question;
            document.getElementById('answer').value = faq.answer;
            document.getElementById('submit-btn').value = 'update';
            document.getElementById('submit-btn').textContent = 'Update FAQ';
            document.getElementById('cancel-btn').style.display = 'inline-block';
        }

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
