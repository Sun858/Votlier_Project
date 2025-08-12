<?php
// Using the environment encryption key and AES-256-CBC from functions.sn.php
require_once 'functions.sn.php';

// Checks if the user has already voted in this poll
function hasUserVoted($conn, $userId, $pollId) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ballot WHERE user_id = ? AND poll_id = ?");
    $stmt->bind_param("ii", $userId, $pollId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count > 0;
}

// Encrypts the ballot using same method as user/admin info
function encryptBallot($ballotData) {
    $encryptionKey = TRUE_MASTER_EMAIL_ENCRYPTION_KEY;
    $cipher = "aes-256-cbc";
    $iv = random_bytes(openssl_cipher_iv_length($cipher));

    $plaintext = json_encode($ballotData);
    $encryptedBallot = openssl_encrypt($plaintext, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv);

    return ['encrypted_ballot' => $encryptedBallot, 'iv' => $iv];
}

// Get polls the user can vote in: active, and user hasn't voted
function getActivePollsForUser($conn, $userId) {
    $now = date("Y-m-d H:i:s");
    $sql = "SELECT poll_id, election_name FROM election
            WHERE start_datetime <= ? AND (end_datetime IS NULL OR end_datetime > ?)
            AND poll_id NOT IN (SELECT poll_id FROM ballot WHERE user_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $now, $now, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $polls = [];
    while ($row = $result->fetch_assoc()) {
        $polls[] = $row;
    }
    $stmt->close();
    return $polls;
}

// Submits a user's ranked vote (up to any amoutn of preferences)
function submitUserVote($conn, $userId, $pollId, $votes) {
    if (hasUserVoted($conn, $userId, $pollId)) {
        return ['error' => 'You have already voted in this election.'];
    }
    // Check for duplicate candidate_ids in preferences
    $candidateIds = array_column($votes, 'candidate_id');
    if (count($candidateIds) !== count(array_unique($candidateIds))) {
        return ['error' => 'You cannot select the same candidate for more than one preference.'];
    }
    
    // Check election is active
    $stmt = $conn->prepare("SELECT start_datetime, end_datetime FROM election WHERE poll_id = ?");
    $stmt->bind_param("i", $pollId);
    $stmt->execute();
    $stmt->bind_result($start, $end);
    $stmt->fetch();
    $stmt->close();

    $now = date("Y-m-d H:i:s");
    if ($now < $start || ($end && $now > $end)) {
        return ['error' => 'This election is not currently active.'];
    }

    $sql = "INSERT INTO ballot (user_id, poll_id, candidate_id, preference_rank, encrypted_ballot, iv) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    foreach ($votes as $vote) {
        $enc = encryptBallot([
            'user_id' => $userId,
            'poll_id' => $pollId,
            'candidate_id' => $vote['candidate_id'],
            'preference_rank' => $vote['preference_rank']
        ]);

        $stmt->bind_param("iiiiss", $userId, $pollId, $vote['candidate_id'], $vote['preference_rank'], $enc['encrypted_ballot'], $enc['iv']);

        if (!$stmt->execute()) {
            $stmt->close();
            return ['error' => 'Failed to record vote.'];
        }
    }
    $stmt->close();
    return ['success' => true];
}
?>