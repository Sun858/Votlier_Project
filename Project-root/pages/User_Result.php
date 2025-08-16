<?php
session_start();
require_once '../includes/security.sn.php';
require_once '../controllers/view_results_user.php';
checkSessionTimeout();

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ionicon Sidebar Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../Assets/css/User_Result.css">
    <style>
        /* --- PATCH: Top Candidates styling to match table --- */
        .top-candidates-section {
            margin-top: 30px;
            margin-bottom: 15px;
            background: #f8fafa;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(60,60,60,.08);
            padding: 18px 18px 0 18px;
            border: 1px solid #e0e0e0;
        }
        .top-candidates-section h2 {
            font-size: 2rem;
            margin-top: 0;
            margin-bottom: 14px;
            font-weight: 600;
            color: #333;
        }
        .top-candidates-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0.5rem;
            box-shadow: none;
        }
        .top-candidates-table th {
            background-color: #5cb030;
            color: #fff;
            font-weight: 600;
            padding: 10px 14px;
            border: none;
            text-align: left;
        }
        .top-candidates-table td {
            background: #fafbfb;
            padding: 10px 14px;
            border-bottom: 1px solid #e0e0e0;
            color: #222;
        }
        .top-candidates-table tr:last-child td {
            border-bottom: none;
        }
        .points-badge {
            color: #169c3f;
            font-weight: 600;
        }
        @media (max-width: 700px) {
            .top-candidates-section h2 {
                font-size: 1.25rem;
            }
            .top-candidates-table th, .top-candidates-table td {
                padding: 6px 6px;
            }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="User_Home.php">
                    <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                    <span class="text">Home</span>
                </a></li>
                <li><a href="User_Profile.php">
                    <span class="icon"><ion-icon name="people-outline"></ion-icon></span>
                    <span class="text">Profile</span>
                </a></li>
                <li><a href="User_Election.php">
                    <span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span>
                    <span class="text">Election</span>
                </a></li>
                <li><a href="User_Result.php">
                    <span class="icon"><ion-icon name="eye-outline"></ion-icon></span>
                    <span class="text">Result</span>
                </a></li>
                <li><a href="User_Settings.php">
                    <span class="icon"><ion-icon name="settings-outline"></ion-icon></span>
                    <span class="text">Settings</span>
                </a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../controllers/Logout.php" class="footer-link signout-link">
                <span class="icon"><ion-icon name="log-out-outline"></ion-icon></span>
                <span class="text">Sign Out</span>
            </a>
        </div>
    </aside>

    <main class="main-content">
        <header class="main-header">
            <h1>Welcome to Election Results</h1>
            <p>Select an election to view results.</p>
        </header>

        <section class="result-section">
            <form method="post" class="election-select-form">
                <label for="poll_id">Choose Election:</label>
                <select name="poll_id" id="poll_id" class="styled-select" required>
                    <option value="">-- Select --</option>
                    <?php foreach ($elections as $election): ?>
                        <option value="<?= htmlspecialchars($election['poll_id']) ?>" <?= ($selectedPollId == $election['poll_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($election['election_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="View-Results-button">View Results</button>
            </form>

            <?php if ($selectedPollId && empty($results)): ?>
                <div class="no-results-msg">No results found for this election.</div>
            <?php endif; ?>

            <?php if (!empty($topCandidates)): ?>
            <div class="top-candidates-section">
                <h2>Top 3 Candidates (Weighted by Rank)</h2>
                <table class="top-candidates-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th>Candidate</th>
                            <th>Party</th>
                            <th style="width: 15%;">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCandidates as $index => $row): ?>
                        <tr>
                            <td><?= $index+1 ?></td>
                            <td><strong><?= htmlspecialchars($row['candidate_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['party']) ?></td>
                            <td><span class="points-badge"><?= intval($row['points']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (!empty($results)): ?>
            <div class="results-table-wrapper">
                <table class="results-table">
                    <thead>
                        <tr class="results-table-header-row">
                            <th>Candidate</th>
                            <th>Party</th>
                            <th>Total Votes</th>
                            <th>Rank 1</th>
                            <th>Rank 2</th>
                            <th>Rank 3</th>
                            <th>Rank 4</th>
                            <th>Rank 5</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['candidate_name']) ?></td>
                            <td><?= htmlspecialchars($row['party']) ?></td>
                            <td><?= intval($row['total_votes']) ?></td>
                            <td><?= intval($row['r1_votes']) ?></td>
                            <td><?= intval($row['r2_votes']) ?></td>
                            <td><?= intval($row['r3_votes']) ?></td>
                            <td><?= intval($row['r4_votes']) ?></td>
                            <td><?= intval($row['r5_votes']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Ionicon scripts -->
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
</body>
</html>