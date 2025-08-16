<?php
header('Content-Type: application/json');
session_start();

// Include your database connection file
require_once '../DatabaseConnection/config.php';

// Function to handle errors and send a JSON response
function sendError($message)
{
    echo json_encode(['error' => $message]);
    exit();
}

// Function to get a database connection
function getDbConnection()
{
    global $conn;
    if ($conn->connect_error) {
        sendError("Database connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Get the requested action from either GET or POST
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Handle different API actions
switch ($action) {
    // --- Public Actions ---
    case 'getPublicCategories':
        try {
            $conn = getDbConnection();
            $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
            $result = $conn->query($sql);
            $categories = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $categories[] = $row;
                }
            }
            echo json_encode($categories);
        } catch (Exception $e) {
            sendError("Error fetching categories: " . $e->getMessage());
        }
        break;

    case 'getPublicDocumentsByCategory':
        $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        if (!$categoryId) {
            sendError("Category ID is required.");
        }
        try {
            $conn = getDbConnection();
            $sql = "SELECT document_id, title FROM documents WHERE category_id = ? ORDER BY title ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $categoryId);
            $stmt->execute();
            $result = $stmt->get_result();
            $documents = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $documents[] = $row;
                }
            }
            echo json_encode($documents);
        } catch (Exception $e) {
            sendError("Error fetching documents: " . $e->getMessage());
        }
        break;

    case 'getPublicDocument':
        $documentId = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
        if (!$documentId) {
            sendError("Document ID is required.");
        }
        try {
            $conn = getDbConnection();
            $sql = "SELECT title, content FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $documentId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                echo json_encode($result->fetch_assoc());
            } else {
                sendError("Document not found.");
            }
        } catch (Exception $e) {
            sendError("Error fetching document: " . $e->getMessage());
        }
        break;

    // --- Admin Actions ---
    case 'getCategories':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        try {
            $conn = getDbConnection();
            $sql = "SELECT category_id, category_name FROM categories ORDER BY category_name ASC";
            $result = $conn->query($sql);
            $categories = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $categories[] = $row;
                }
            }
            echo json_encode($categories);
        } catch (Exception $e) {
            sendError("Error fetching categories: " . $e->getMessage());
        }
        break;

    case 'getDocuments':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        try {
            $conn = getDbConnection();
            $sql = "SELECT d.document_id, d.title, c.category_name FROM documents d JOIN categories c ON d.category_id = c.category_id ORDER BY d.title ASC";
            $result = $conn->query($sql);
            $documents = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $documents[] = $row;
                }
            }
            echo json_encode($documents);
        } catch (Exception $e) {
            sendError("Error fetching documents: " . $e->getMessage());
        }
        break;

    case 'getDocument':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        $documentId = isset($_GET['document_id']) ? intval($_GET['document_id']) : 0;
        if (!$documentId) {
            sendError("Document ID is required.");
        }
        try {
            $conn = getDbConnection();
            $sql = "SELECT document_id, title, content, category_id FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $documentId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                echo json_encode($result->fetch_assoc());
            } else {
                sendError("Document not found.");
            }
        } catch (Exception $e) {
            sendError("Error fetching document: " . $e->getMessage());
        }
        break;

    case 'addCategory':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        // Get input for non-file POST requests
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data['category_name'])) {
            sendError("Invalid request for adding category.");
        }
        try {
            $conn = getDbConnection();
            $sql = "INSERT INTO categories (category_name) VALUES (?)";
            $stmt = $conn->prepare($sql);
            $categoryName = htmlspecialchars($data['category_name']);
            $stmt->bind_param("s", $categoryName);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'category_id' => $stmt->insert_id]);
            } else {
                sendError("Failed to add category: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendError("Error adding category: " . $e->getMessage());
        }
        break;

    case 'addDocument':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        // Get input for non-file POST requests
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data['title'], $data['content'], $data['category_id'])) {
            sendError("Missing required data for adding document.");
        }
        try {
            $conn = getDbConnection();
            $sql = "INSERT INTO documents (title, content, category_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            
            // Sanitize and bind parameters
            $title = htmlspecialchars($data['title']);
            $content = htmlspecialchars($data['content']);
            $categoryId = intval($data['category_id']);
            
            $stmt->bind_param("ssi", $title, $content, $categoryId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                // Return a more detailed error message on failure
                sendError("Failed to add document: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendError("Error adding document: " . $e->getMessage());
        }
        break;

    case 'updateDocument':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        // Get input for non-file POST requests
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data['document_id'], $data['title'], $data['content'], $data['category_id'])) {
            sendError("Missing required data for updating document.");
        }
        try {
            $conn = getDbConnection();
            $sql = "UPDATE documents SET title = ?, content = ?, category_id = ? WHERE document_id = ?";
            $stmt = $conn->prepare($sql);

            // Sanitize and bind parameters
            $title = htmlspecialchars($data['title']);
            $content = htmlspecialchars($data['content']);
            $categoryId = intval($data['category_id']);
            $documentId = intval($data['document_id']);

            $stmt->bind_param("ssii", $title, $content, $categoryId, $documentId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                sendError("Failed to update document: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendError("Error updating document: " . $e->getMessage());
        }
        break;

    case 'deleteCategory':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        // Get input for non-file POST requests
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($data['category_id'])) {
            sendError("Invalid request for deleting category.");
        }
        $categoryId = intval($data['category_id']);
        try {
            $conn = getDbConnection();
            $sql = "DELETE FROM categories WHERE category_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $categoryId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                sendError("Failed to delete category: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendError("Error deleting category: " . e->getMessage());
        }
        break;

    case 'deleteDocument':
        if (!isset($_SESSION['admin_id'])) {
            sendError("Unauthorized access.");
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_GET['document_id'])) {
            sendError("Invalid request for deleting document.");
        }
        $documentId = intval($_GET['document_id']);
        try {
            $conn = getDbConnection();
            $sql = "DELETE FROM documents WHERE document_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $documentId);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                sendError("Failed to delete document: " . $stmt->error);
            }
        } catch (Exception $e) {
            sendError("Error deleting document: " . $e->getMessage());
        }
        break;

    default:
        sendError("Invalid API action.");
        break;
}

if (isset($conn)) {
    $conn->close();
}
