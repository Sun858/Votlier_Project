<?php
<?php
session_start();
header('Content-Type: application/json');

// Check admin authentication
if (!isset($_SESSION["admin_id"])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in as admin.']);
    exit;
}

// Validate poll_id
if (!isset($_POST['poll_id']) || !is_numeric($_POST['poll_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid poll_id.']);
    exit;
}

$poll_id = intval($_POST['poll_id']);

// Connect to the database
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete candidates first (if any)
    $stmt1 = $conn->prepare("DELETE FROM candidates WHERE poll_id = ?");
    $stmt1->bind_param("i", $poll_id);
    $stmt1->execute();
    $stmt1->close();

    // Delete the election
    $stmt2 = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
    $stmt2->bind_param("i", $poll_id);
    $stmt2->execute();

    if ($stmt2->affected_rows > 0) {
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Election deleted successfully.']);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Election not found or already deleted.']);
    }
    $stmt2->close();
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error deleting election: ' . $e->getMessage()]);
}

$conn->close();