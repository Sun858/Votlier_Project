<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    die("Access denied.");
}

$conn = new mysqli("localhost", "root", "", "voting_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sanitize and assign election details
$poll_id = $_POST['poll_id'];
$election_type = $_POST['election_type'];
$election_name = $_POST['election_name'];
$start_datetime = $_POST['start_datetime'];
$end_datetime = $_POST['end_datetime'];

// Insert election into database
$stmt = $conn->prepare("INSERT INTO election (poll_id, election_type, election_name, start_datetime, end_datetime) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $poll_id, $election_type, $election_name, $start_datetime, $end_datetime);
if (!$stmt->execute()) {
    die("Error saving election: " . $stmt->error);
}
$stmt->close();

// Create uploads folder if needed
$upload_dir = 'uploads/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Loop over candidates
$index = 1;
while (isset($_POST["candidate_id_$index"])) {
    $candidate_id = $_POST["candidate_id_$index"];
    $candidate_name = $_POST["candidate_name_$index"];
    $party = $_POST["party_$index"] ?? '';
    $symbol = $_POST["symbol_$index"] ?? '';
    $image_name = "";

    // Process image upload
    if (isset($_FILES["image_$index"]) && $_FILES["image_$index"]['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES["image_$index"]["tmp_name"];
        $original_name = basename($_FILES["image_$index"]["name"]);
        $image_name = time() . "_$original_name";
        move_uploaded_file($tmp_name, $upload_dir . $image_name);
    }

    // Insert candidate into database
    $stmt = $conn->prepare("INSERT INTO candidates (candidate_id, poll_id, candidate_name, party, candidate_symbol, candidate_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $candidate_id, $poll_id, $candidate_name, $party, $symbol, $image_name);
    
    if (!$stmt->execute()) {
        die("Error saving candidate $index: " . $stmt->error);
    }
    $stmt->close();

    $index++;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Election Created</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f4f4; text-align: center; padding-top: 100px;">
    <div style="background-color: #fff; display: inline-block; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="color: green;">Election and candidates created successfully! ✅</h2>
        <a href="dashboard.php">
            <button style="padding: 10px 20px; background-color: green; color: white; border: none; border-radius: 5px; cursor: pointer;">Back to Dashboard</button>
        </a>
    </div>
</body>
</html>
