<?php
session_start();

// Check if the user is logged in and has the 'administration' role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administration') {
    header("Location: admin_login.php");
    exit();
}

// Logout logic
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    session_destroy();
    header("Location: admin_login.php");
    exit();
}

// Connect to database
$conn = new mysqli("localhost", "root", "", "voting_system");
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch elections
$result = $conn->query("SELECT * FROM election");
if (!$result) {
    die("Query failed: " . $conn->error);
}

// Handle election deletion
if (isset($_GET['delete_poll_id'])) {
    $delete_poll_id = $_GET['delete_poll_id'];

    $stmt = $conn->prepare("DELETE FROM election WHERE poll_id = ?");
    $stmt->bind_param("s", $delete_poll_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Election successfully deleted.";
    } else {
        $_SESSION['message'] = "Failed to delete election.";
    }

    $stmt->close();
    header("Location: dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        a {
            text-decoration: none;
            color: #007BFF;
        }
        a:hover {
            text-decoration: underline;
        }
        .action-buttons a {
            margin-right: 10px;
        }
        .tooltip {
            position: relative;
            cursor: pointer;
        }
        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            background-color: #333;
            color: #fff;
            padding: 6px 8px;
            border-radius: 4px;
            top: -35px;
            left: 0;
            white-space: nowrap;
            font-size: 12px;
            z-index: 1000;
        }
        .delete-button {
            color: red;
            font-weight: bold;
        }
        .delete-button:hover {
            text-decoration: underline;
        }
        .logout-button {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 15px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: background-color 0.3s ease;
        }
        .logout-button:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>

    <a href="?logout=true" class="logout-button">Logout</a>
    <h2>Existing Elections 📋</h2>

    <?php if (isset($_SESSION['message'])): ?>
        <script>alert("<?= $_SESSION['message']; ?>");</script>
        <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Poll ID</th>
                    <th>Election Type</th>
                    <th>Election Name</th>
                    <th>Start of Election</th>
                    <th>End of Election</th>
                    <th>Candidates</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $poll_id = htmlspecialchars($row['poll_id']);
                        $election_type = htmlspecialchars($row['election_type']);
                        $election_name = htmlspecialchars($row['election_name']);
                        $tooltip_edit = "Edit {$election_name} {$poll_id}";
                        $tooltip_delete = "Delete {$election_name} {$poll_id}";
                        $tooltip_candidates = "View Candidates for {$election_name}";
                    ?>
                    <tr>
                        <td><?= $poll_id ?></td>
                        <td><?= $election_type ?></td>
                        <td><?= $election_name ?></td>
                        <td><?= htmlspecialchars($row['start_datetime']) ?></td>
                        <td><?= htmlspecialchars($row['end_datetime']) ?></td>
                        <td>
                            <a href="view_candidates.php?poll_id=<?= urlencode($poll_id) ?>"
                               class="tooltip"
                               data-tooltip="<?= $tooltip_candidates ?>">👥 View Candidates</a>
                        </td>
                        <td class="action-buttons">
                            <a href="admin_create_election.php?poll_id=<?= urlencode($poll_id) ?>"
                               class="tooltip"
                               data-tooltip="<?= $tooltip_edit ?>">✏️ Edit</a>
                            <a href="?delete_poll_id=<?= urlencode($poll_id) ?>"
                               class="delete-button tooltip"
                               onclick="return confirm('Are you sure you want to delete this election?');"
                               data-tooltip="<?= $tooltip_delete ?>">🗑️ Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- Only show this when elections exist -->
        <div class="delete-section" style="margin-top: 20px;">
            <p><strong>Note:</strong> You can EDIT or DELETE an election using the buttons in the "Actions" column.</p>
        </div>

    <?php else: ?>
        <p style="margin-top: 40px; font-size: 18px; color: #555;">
            There are no current existing elections.
        </p>
    <?php endif; ?>

    <form action="admin_create_election.php" method="get" style="margin-top: 30px;">
        <button style="padding: 10px 20px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; font-weight: bold;">
            ➕ Create New Election
        </button>
    </form>

</body>
</html>

<?php $conn->close(); ?>
