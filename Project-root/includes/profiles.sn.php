<?php
require_once '../includes/functions.sn.php';
// We use the above file to use the same crypto functions and keep everything symmetrical. 


function getProfile($conn, int $id, string $type = 'user') {
    if ($type === 'admin') {
        $sql = "SELECT admin_id AS id, first_name, last_name, email, iv, date_of_birth FROM administration WHERE admin_id = ?";
    } else {
        $sql = "SELECT user_id AS id, first_name, middle_name, last_name, email, iv, date_of_birth, address FROM users WHERE user_id = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) return null;
    $row = $result->fetch_assoc();
    $iv = $row['iv'];
    $profile = [
        'id'         => $row['id'],
        'first_name' => dec_cbc($row['first_name'], $iv),
        'last_name'  => dec_cbc($row['last_name'], $iv),
        'email'      => dec_cbc($row['email'], $iv),
        'dob'        => $row['date_of_birth'] ?? '',
        'iv'         => $iv
    ];
    if ($type !== 'admin') {
        $profile['middle_name'] = dec_cbc($row['middle_name'], $iv);
        $profile['address'] = $row['address'] ?? '';
    }
    return $profile;
}

// Update profile function for two different types : User and Admin
function updateProfile($conn, int $id, $first, $last, $email, $dob, string $type = 'user') {
    if ($type === 'admin') {
        $sql = "SELECT iv FROM administration WHERE admin_id = ?";
    } else {
        $sql = "SELECT iv FROM users WHERE user_id = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ivRes = $stmt->get_result();
    if ($ivRes->num_rows === 0) throw new Exception(ucfirst($type) . ' not found');
    $iv = ($ivRes->fetch_assoc())['iv'];
    $encFirst = enc_cbc($first, $iv);
    $encLast  = enc_cbc($last, $iv);
    $encEmail = enc_cbc($email, $iv);
    $emailBlindIndex = hash_hmac('sha256', $email, TRUE_BLIND_INDEX_SECRET_KEY, true);

    if ($type === 'admin') {
        $upd = $conn->prepare("UPDATE administration SET first_name = ?, last_name = ?, email = ?, email_blind_index = ?, date_of_birth = ? WHERE admin_id = ?");
        $upd->bind_param("sssssi", $encFirst, $encLast, $encEmail, $emailBlindIndex, $dob, $id);
    } else {
        $upd = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, email_blind_index = ?, date_of_birth = ? WHERE user_id = ?");
        $upd->bind_param("sssssi", $encFirst, $encLast, $encEmail, $emailBlindIndex, $dob, $id);
    }
    $upd->execute();
    return true;
}

// Users only: update address 
function updateUserAddress($conn, int $userId, $address) {
    $upd = $conn->prepare("UPDATE users SET address = ? WHERE user_id = ?");
    $upd->bind_param("si", $address, $userId);
    return $upd->execute();
}

// Users only: get elections overview 
function getUserElectionsOverview($conn, int $userId) {
    $overview = [];
    $now = new DateTime();
    $sql = "
        SELECT e.poll_id, e.election_name, e.start_datetime, e.end_datetime,
               EXISTS(SELECT 1 FROM ballot b WHERE b.user_id = ? AND b.poll_id = e.poll_id) AS has_voted
        FROM election e
        WHERE (e.start_datetime IS NOT NULL OR e.end_datetime IS NOT NULL)
        ORDER BY e.start_datetime ASC
    ";
    $est = $conn->prepare($sql);
    $est->bind_param("i", $userId);
    $est->execute();
    $res = $est->get_result();

    while ($e = $res->fetch_assoc()) {
        $start = $e['start_datetime'] ? new DateTime($e['start_datetime']) : null;
        $end   = $e['end_datetime']   ? new DateTime($e['end_datetime'])   : null;

        if ($start && $now < $start) {
            $status = 'Starts in ' . $now->diff($start)->format('%a days %h hours');
        } elseif ($end && $now > $end) {
            $status = 'Election Expired';
        } elseif ($start && $end && $now >= $start && $now <= $end) {
            $status = 'Ends in ' . $now->diff($end)->format('%a days %h hours');
        } else {
            $status = 'Schedule TBA';
        }

        $overview[] = [
            'name'       => $e['election_name'],
            'enrolled'   => true,
            'voted'      => (bool)$e['has_voted'],
            'status'     => $status,
            'start_time' => $e['start_datetime'],
            'end_time'   => $e['end_datetime']
        ];
    }
    return $overview;
}
?>