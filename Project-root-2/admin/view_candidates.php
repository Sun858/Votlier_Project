<?php
session_start();

// Only allow logged-in admin users
if (!isset($_SESSION["admin_id"])) {
    die("Access denied. Admins only.");
}

// Check if poll_id is passed in the URL
if (!isset($_GET['poll_id'])) {
    die("No poll ID provided.");
}

$poll_id = $_GET['poll_id'];

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) die("DB Error: Check 1) Docker containers 2) .env credentials");

// Verify if the poll_id exists in the election table and get election name
$stmt_check_election = $conn->prepare("SELECT election_name FROM election WHERE poll_id = ?");
$stmt_check_election->bind_param("s", $poll_id);
$stmt_check_election->execute();
$election_result = $stmt_check_election->get_result();

if ($election_result->num_rows == 0) {
    die("Invalid election ID or no such election exists.");
}

// Get the election name
$election_data = $election_result->fetch_assoc();
$election_name = $election_data['election_name'];

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
    <style>
        /* Basic styling for the table */
        table {
            width: 80%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        img {
            max-width: 100px;
            max-height: 100px;
            height: auto;
            display: block; /* Ensures image is centered in table cell if needed */
            margin: auto;
        }
    </style>
</head>
<body>
    <h2>👥 Candidates for Election: <?= htmlspecialchars($election_name) ?></h2>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Candidate Name</th>
                    <th>Party</th>
                    <th>Image</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['candidate_name']) ?></td>
                        <td><?= htmlspecialchars($row['party']) ?></td>
                        <td>
                            <?php if (!empty($row['candidate_image'])): ?>
                                <img src="images/<?php echo htmlspecialchars($row['candidate_image']); ?>" alt="Candidate Image">
                            <?php else: ?>
                                No Image
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No candidates found for this election.</p>
    <?php endif; ?>

</body>
</html>

<?php
$stmt->close();
$stmt_check_election->close();
$conn->close();
?>
