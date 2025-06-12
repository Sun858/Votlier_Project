<?php
session_start();

// Only allow logged-in admin users
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administration') {
    die("Access denied. Admins only.");
}

// Check if poll_id is passed in the URL
if (!isset($_GET['poll_id'])) {
    die("No poll ID provided.");
}

$poll_id = $_GET['poll_id'];

$conn = new mysqli("localhost", "root", "", "voting_system");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Verify if the poll_id exists in the election table
$stmt_check_election = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
$stmt_check_election->bind_param("s", $poll_id);
$stmt_check_election->execute();
$election_result = $stmt_check_election->get_result();

if ($election_result->num_rows == 0) {
    die("Invalid election ID or no such election exists.");
}

// Prepare and execute the query to fetch candidates for the election
$stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ?");
$stmt->bind_param("s", $poll_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates for Election</title>
</head>
<body>
    <h2>👥 Candidates for Election #<?= htmlspecialchars($poll_id) ?></h2>

    <?php if ($result->num_rows > 0): ?>
        <ul>
            <?php while ($row = $result->fetch_assoc()): ?>
                <li>
                    <strong><?= htmlspecialchars($row['candidate_name']) ?></strong>
                    (ID: <?= htmlspecialchars($row['candidate_id']) ?>) - Party: <?= htmlspecialchars($row['party']) ?><br>
                    <?php if (!empty($row['candidate_image'])): ?>
                        <img src="images/<?php echo $row['candidate_image']; ?>" width="100" height="100">
                    <?php endif; ?>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <p>No candidates found for this election.</p>
    <?php endif; ?>

    <p><a href="dashboard.php">⬅ Back to Dashboard</a></p>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
