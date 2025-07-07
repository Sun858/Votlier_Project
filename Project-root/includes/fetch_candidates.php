<?php
require_once '../DatabaseConnection/config.php';
require_once 'election.sn.php';

header('Content-Type: application/json');

if (!isset($_GET['poll_id']) || !is_numeric($_GET['poll_id'])) {
    echo json_encode(["success" => false, "message" => "Invalid poll ID"]);
    exit();
}

$pollId = (int) $_GET['poll_id'];
$candidates = getElectionCandidatesForImport($conn, $pollId);

if ($candidates) {
    echo json_encode(["success" => true, "candidates" => $candidates]);
} else {
    echo json_encode(["success" => false, "message" => "No candidates found."]);
}
exit();