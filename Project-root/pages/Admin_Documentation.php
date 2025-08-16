<?php
session_start();
// This is the security page for rate limiting and timeout. 15Min is currently set
require_once '../includes/security.sn.php';
checkSessionTimeout(); // Calling the function for the timeout, it redirects to login page and ends the session.

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Documentation</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/Admin_Documentation.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <!-- Scripts for Markdown rendering -->
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@2.4.0/dist/purify.min.js"></script>

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
            <h1>Manage Documentation</h1>
            <p>Create, edit, and delete user documentation for Votify.</p>
        </header>

        <div class="documentation-manager">
            <div id="message-area" class="alert-message" style="display: none;"></div>

            <!-- Form for creating/editing a document -->
            <form id="doc-form" class="doc-form">
                <input type="hidden" id="doc-id" name="document_id">
                <div class="form-group">
                    <label for="doc-title">Document Title</label>
                    <input type="text" id="doc-title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="doc-category">Category</label>
                    <select id="doc-category" name="category_id" required>
                        <option value="">-- Select a Category --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new-category">Or create a new category</label>
                    <input type="text" id="new-category" placeholder="Enter new category name">
                </div>
                <div class="form-group">
                    <label for="doc-content">Content</label>
                    <textarea id="doc-content" name="content" required></textarea>
                </div>
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary" id="save-btn">Save Document</button>
                    <button type="button" class="btn btn-secondary" id="cancel-btn">Cancel</button>
                    <button type="button" class="btn btn-danger" id="delete-btn" style="display:none;">Delete Document</button>
                </div>
            </form>

            <hr style="margin: 2rem 0;">

            <div class="table-container">
                <h3>Current Documents</h3>
                <table id="documents-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Documents will be populated here by JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- The custom delete confirmation modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this document? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button class="btn btn-danger" id="confirm-delete-btn">Yes, Delete</button>
                <button class="btn btn-secondary" id="cancel-delete-btn">Cancel</button>
            </div>
        </div>
    </div>


    <!-- Ionicon scripts -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <script src="../Assets/js/Admin_Documentation.js"></script>

</body>

</html>