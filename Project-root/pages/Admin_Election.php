<?php
session_start();
require_once '../includes/security.sn.php';
require_once '../includes/election.sn.php';
require_once '../DatabaseConnection/config.php';
checkSessionTimeout();

if (!isset($_SESSION["admin_id"])) {
    header("location: ../pages/login.php");
    exit();
}

// Handle deletion if requested
if (isset($_GET['delete_poll_id'])) {
    deleteElection($conn, $_GET['delete_poll_id']);
    $_SESSION['message'] = "Election deleted successfully.";
    header("Location: admin_election.php");
    exit();
}

$editing = false;
$editData = [];
if (isset($_GET['edit_poll_id'])) {
    $editing = true;
    $editData = getElectionById($conn, $_GET['edit_poll_id']);
}

$elections = getAllElections($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Election Management</title>
    <link rel="stylesheet" href="../Assets/css/Admin_Election.css">
</head>
<body>
    <aside class="sidebar">
        <!-- sidebar content -->
    </aside>
    <main class="main-content">
        <h1>Manage Elections</h1>

        <?php if (isset($_SESSION['message'])): ?>
            <p style="color: green;"> <?= $_SESSION['message']; unset($_SESSION['message']); ?> </p>
        <?php endif; ?>

        <form action="../includes/save_election.php" method="post">
            <?php if ($editing): ?>
                <input type="hidden" name="poll_id" value="<?= htmlspecialchars($editData['poll_id']) ?>">
            <?php endif; ?>
            <label>Election Type: <input type="text" name="election_type" value="<?= $editing ? htmlspecialchars($editData['election_type']) : '' ?>"></label>
            <label>Election Name: <input type="text" name="election_name" value="<?= $editing ? htmlspecialchars($editData['election_name']) : '' ?>"></label>
            <label>Start Date/Time: <input type="datetime-local" name="start_datetime" value="<?= $editing ? htmlspecialchars($editData['start_datetime']) : '' ?>"></label>
            <label>End Date/Time: <input type="datetime-local" name="end_datetime" value="<?= $editing ? htmlspecialchars($editData['end_datetime']) : '' ?>"></label>
            <button type="submit"> <?= $editing ? 'Update Election' : 'Create Election' ?> </button>
        </form>

        <h2>Current Elections</h2>
        <?php if ($elections->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Poll ID</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $elections->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['poll_id']) ?></td>
                            <td><?= htmlspecialchars($row['election_type']) ?></td>
                            <td><?= htmlspecialchars($row['election_name']) ?></td>
                            <td><?= htmlspecialchars($row['start_datetime']) ?></td>
                            <td><?= htmlspecialchars($row['end_datetime']) ?></td>
                            <td>
                                <a href="?edit_poll_id=<?= urlencode($row['poll_id']) ?>">Edit</a>
                                <a href="?delete_poll_id=<?= urlencode($row['poll_id']) }" onclick="return confirm('Delete this election?');">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No elections available.</p>
        <?php endif; ?>
    </main>
</body>
</html>