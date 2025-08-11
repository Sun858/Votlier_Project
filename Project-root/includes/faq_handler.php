<?php
// faq_handler.php
// This script handles all database interactions for FAQ management.

// Include database connection
require_once 'config.php';

// Function to sanitize input to prevent XSS attacks
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = sanitize_input($_POST['action']);

    if ($action === 'add') {
        $question = sanitize_input($_POST['question']);
        $answer = sanitize_input($_POST['answer']);
        $date_created = date("Y-m-d H:i:s");

        // Prepare and execute the SQL query to insert data
        $stmt = $conn->prepare("INSERT INTO faqs (question, answer, date_created) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $question, $answer, $date_created);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ added successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif ($action === 'update') {
        $faq_id = sanitize_input($_POST['faq_id']);
        $question = sanitize_input($_POST['question']);
        $answer = sanitize_input($_POST['answer']);

        $stmt = $conn->prepare("UPDATE faqs SET question = ?, answer = ? WHERE id = ?");
        $stmt->bind_param("ssi", $question, $answer, $faq_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ updated successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $faq_id = sanitize_input($_POST['faq_id']);

        $stmt = $conn->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $faq_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "FAQ deleted successfully!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
    // Check if headers have already been sent before attempting to redirect
    if (!headers_sent()) {
        // Redirect back to the admin FAQ page to prevent form resubmission
        header("Location: Admin_FAQ.php");
        exit();
    } else {
        // This is a fallback if the redirect fails. You can add an error message here.
        echo "FAQ updated, but redirect failed. Please click <a href='Admin_FAQ.php'>here</a> to return to the admin page.";
    }
}

$conn->close();
?>
