<?php
// Controller for User_Result page (user-facing election results)
require_once '../includes/security.sn.php';
require '../DatabaseConnection/config.php';
require_once '../includes/userres.sn.php';

checkSessionTimeout();

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}

$selectedPollId = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : null;
$elections = getElectionsWithTally($conn);
$results = ($selectedPollId) ? getUserElectionResults($conn, $selectedPollId) : [];
$topCandidates = (!empty($results)) ? calculateTopCandidates($results, 3) : [];
?>