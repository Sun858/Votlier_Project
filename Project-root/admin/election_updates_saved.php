<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    die("Access denied.");
}

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) die("DB Error: Check 1) Docker containers 2) .env credentials");

$poll_id = $_POST['poll_id'];

// Update election info
$stmt = $conn->prepare("UPDATE election SET election_type = ?, election_name = ?, start_datetime = ?, end_datetime = ? WHERE poll_id = ?");
$stmt->bind_param(
    "sssss",
    $_POST['election_type'],
    $_POST['election_name'],
    $_POST['start_datetime'],
    $_POST['end_datetime'],
    $poll_id
);

if (!$stmt->execute()) {
    die("❌ Error updating election: " . $conn->error);
}
$stmt->close();

// Delete existing candidates for this poll
$conn->query("DELETE FROM candidates WHERE poll_id = '$poll_id'");

// Insert updated candidates
$index = 1;
while (isset($_POST["candidate_id_$index"])) {
    $candidate_id = $_POST["candidate_id_$index"];
    $candidate_name = $_POST["candidate_name_$index"];
    $party = $_POST["party_$index"] ?? '';
    $symbol = $_POST["symbol_$index"] ?? '';
    $image_name = '';

    if (isset($_FILES["image_$index"]) && $_FILES["image_$index"]["error"] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES["image_$index"]["tmp_name"];
        $extension = pathinfo($_FILES["image_$index"]["name"], PATHINFO_EXTENSION);
        $image_name = uniqid("img_", true) . "." . $extension;
        $target_path = "uploads/" . $image_name;

        if (!move_uploaded_file($tmp_name, $target_path)) {
            echo "⚠️ Failed to upload image for candidate $candidate_name.<br>";
            $image_name = ''; // Skip image
        }
    }

    $stmt = $conn->prepare("INSERT INTO candidates (candidate_id, poll_id, candidate_name, party, candidate_symbol, candidate_image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $candidate_id, $poll_id, $candidate_name, $party, $symbol, $image_name);

    if (!$stmt->execute()) {
        die("❌ Error inserting candidate $candidate_name: " . $conn->error);
    }
    $stmt->close();

    $index++;
}

echo "<div style='text-align:center; margin-top:50px;'>
        <h2>Election and candidates updated successfully!✅</h2>
        <a href='dashboard.php'><button style='padding: 10px 20px; background-color: green; color: white; border: none; border-radius: 5px;'>Back to Dashboard</button></a>
      </div>";

$conn->close();
?>
