<?php
// faq_handler.php
// This script handles all database interactions for FAQ management.

// Include database connection
require_once '../DatabaseConnection/config.php';

// Get the admin ID.
$admin_id = $_SESSION['admin_id'] ?? null;

if (!$admin_id) {
    die("Error: Admin not logged in or session expired");
}

// Function to sanitize input to prevent XSS attacks
function sanitize_input($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}

// Handle form submissions for adding, updating, and deleting FAQs
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action']);

    if ($action === 'add') {
        $question = sanitize_input($_POST['question']);
        $answer = sanitize_input($_POST['answer']);

        // Prepare and execute the SQL query to insert data
        $stmt = $conn->prepare("INSERT INTO faqs (admin_id, question, answer) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $admin_id, $question, $answer);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ added successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
        // Handle the redirect after adding
    } elseif ($action === 'update') {
        $faq_id = sanitize_input($_POST['faq_id']);
        $question = sanitize_input($_POST['question']);
        $answer = sanitize_input($_POST['answer']);

        $stmt = $conn->prepare("UPDATE faqs SET question = ?, answer = ? WHERE faq_id = ? AND admin_id = ?");
        $stmt->bind_param("ssii", $question, $answer, $faq_id, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ updated successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
        // Handle the redirect after updating
    } elseif ($action === 'delete') {
        $faq_id = sanitize_input($_POST['faq_id']);

        // Added security check to ensure the admin can only delete their own FAQs
        $stmt = $conn->prepare("DELETE FROM faqs WHERE faq_id = ? AND admin_id = ?");
        $stmt->bind_param("ii", $faq_id, $admin_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ deleted successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }

    // Redirect back to the admin FAQ page to prevent form resubmission
    if (!headers_sent()) {
        header("Location: Admin_FAQ.php");
        exit();
    }
}


?>