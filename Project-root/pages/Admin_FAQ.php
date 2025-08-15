<?php
session_start();
// This is the security page for rate limiting and timeout. 15Min is currently set
require_once '../includes/security.sn.php';
require_once '../DatabaseConnection/config.php';
require_once '../includes/election_stats.php'; // This is where we'll modify the function
checkSessionTimeout();

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Handle FAQ form submissions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../includes/faq_handler.php';
}

// ---  CODE FOR PAGINATION ---
// 1. Define pagination variables
$items_per_page = 5;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;

// 2. Get the total number of FAQs
$total_faqs_count = getTotalFAQsCount($conn); 

// 3. Calculate the total number of pages
$total_pages = ceil($total_faqs_count / $items_per_page);

// 4. Calculate the offset for the SQL query
$offset = ($current_page - 1) * $items_per_page;
// --- END OF PAGINATION CODE ---


// Fetch all FAQs from the database to display in the table
$faqs = getAllFAQs($conn, $items_per_page, $offset);

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
                <li><a href="Admin_Documentation.php">
                        <span class="icon"><ion-icon name="document-text"></ion-icon></span>
                        <span class="text">Manage Documentation</span>
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
            <h1>Manage FAQs</h1>
            <p>Create, edit, and delete FAQs for Votify.</p>
        </header>

        <section class="admin-faq-container">
            <form id="faq-form" action="../pages/Admin_FAQ.php" method="POST" class="faq-form">
                <input type="hidden" name="faq_id" id="faq_id">
                <div class="form-group">
                    <label for="question">Question</label>
                    <input type="text" id="question" name="question" required>
                </div>
                <div class="form-group">
                    <label for="answer">Answer</label>
                    <textarea id="answer" name="answer" rows="4" required></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" name="action" value="add" id="submit-btn" class="btn btn-primary">Add FAQ</button>
                    <button type="button" id="cancel-btn" class="btn btn-secondary" style="display: none;">Cancel Edit</button>
                </div>
            </form>

            <hr style="margin: 2rem 0;">

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
                                            <i class="fas fa-edit"></i><ion-icon name="create-outline"></ion-icon> Edit
                                        </button>
                                        <button class="btn btn-delete" onclick="openDeleteModal(<?php echo htmlspecialchars($faq['faq_id']); ?>)">
                                            <i class="fas fa-trash-alt"></i><ion-icon name="trash-outline"></ion-icon> Delete
                                        </button>
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-buttons">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-btn prev-btn">Previous</a>
                    <?php endif; ?>

                    <span class="pagination-info">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-btn next-btn">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <p>Are you sure you want to delete this FAQ? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button id="cancelDelete" class="btn btn-secondary">Cancel</button>
                <button id="confirmDelete" class="btn btn-delete">Delete</button>
            </div>
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>

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

        // --- Delete Modal Javascript ---
        let faqToDelete = null;
        const deleteModal = document.getElementById('deleteModal');
        const confirmDeleteBtn = document.getElementById('confirmDelete');
        const cancelDeleteBtn = document.getElementById('cancelDelete');

        function openDeleteModal(faqId) {
            faqToDelete = faqId;
            deleteModal.style.display = 'flex';
        }

        cancelDeleteBtn.addEventListener('click', () => {
            deleteModal.style.display = 'none';
            faqToDelete = null;
        });

        confirmDeleteBtn.addEventListener('click', () => {
            if (faqToDelete !== null) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '../pages/Admin_FAQ.php';

                // Add the FAQ ID input
                const faqIdInput = document.createElement('input');
                faqIdInput.type = 'hidden';
                faqIdInput.name = 'faq_id';
                faqIdInput.value = faqToDelete;
                form.appendChild(faqIdInput);

                // Add the action input
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);

                // Append the form to the body and submit it
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Close the modal if the user clicks outside of it
        window.addEventListener('click', (event) => {
            if (event.target === deleteModal) {
                deleteModal.style.display = 'none';
                faqToDelete = null;
            }
        });
    </script>
</body>

</html>