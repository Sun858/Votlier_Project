<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    die("Access denied. Admins only.");
}

if (!isset($_GET['poll_id'])) {
    die("No poll ID provided.");
}

$poll_id = (int)$_GET['poll_id'];

// Docker DB Connection
$conn = new mysqli('db', 'admin', 'adminpassword', 'voting_system');
if ($conn->connect_error) die("DB Error: Check 1) Docker containers 2) .env credentials");

// Verify if the poll_id exists in the election table
$stmt = $conn->prepare("SELECT * FROM election WHERE poll_id = ?");
$stmt->bind_param("i", $poll_id);
$stmt->execute();
$election_result = $stmt->get_result();

if ($election_result->num_rows == 0) {
    die("Invalid election ID or no such election exists.");
}
$election = $election_result->fetch_assoc();
$stmt->close();

// Fetch candidates
$stmt = $conn->prepare("SELECT * FROM candidates WHERE poll_id = ? ORDER BY candidate_name");
$stmt->bind_param("i", $poll_id);
$stmt->execute();
$candidates_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidates for <?= htmlspecialchars($election['election_name']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .election-info {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .candidates-list {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .candidate {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            border-radius: 5px;
            background-color: #fefefe;
        }
        .candidate-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 20px;
            border: 2px solid #3498db;
        }
        .candidate-details {
            flex-grow: 1;
        }
        .candidate-name {
            font-weight: bold;
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .candidate-meta {
            color: #7f8c8d;
            font-size: 14px;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .no-candidates {
            color: #7f8c8d;
            font-style: italic;
            padding: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>🗳️ <?= htmlspecialchars($election['election_name']) ?></h1>
    
    <div class="election-info">
        <p><strong>Election Type:</strong> <?= htmlspecialchars($election['election_type']) ?></p>
        <p><strong>Poll ID:</strong> <?= $poll_id ?></p>
        <p><strong>Voting Period:</strong> 
            <?= date('F j, Y g:i a', strtotime($election['start_datetime'])) ?> to
            <?= date('F j, Y g:i a', strtotime($election['end_datetime'])) ?>
        </p>
    </div>

    <div class="candidates-list">
        <h2>👥 Candidates</h2>
        
        <?php if ($candidates_result->num_rows > 0): ?>
            <?php while ($candidate = $candidates_result->fetch_assoc()): ?>
                <div class="candidate">
                    <div class="candidate-photo" style="background-color: #eee; display: flex; align-items: center; justify-content: center;">
                    <span style="color: #999;">No Photo</span>
                    </div>
                    
                    <div class="candidate-details">
                        <div class="candidate-name"><?= htmlspecialchars($candidate['candidate_name']) ?></div>
                        <div class="candidate-meta">
                            <strong>ID:</strong> <?= htmlspecialchars($candidate['candidate_id']) ?> | 
                            <strong>Party:</strong> <?= !empty($candidate['party']) ? htmlspecialchars($candidate['party']) : 'Independent' ?>
                            <?php if (!empty($candidate['candidate_symbol'])): ?>
                                | <strong>Symbol:</strong> <?= htmlspecialchars($candidate['candidate_symbol']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-candidates">No candidates found for this election.</div>
        <?php endif; ?>
    </div>

    <a href="dashboard.php" class="back-link">⬅ Back to Dashboard</a>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>