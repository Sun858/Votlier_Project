<?php
// Controller for User_Result page (user-facing election results)
require_once '../includes/security.sn.php'; // Security functions
require '../DatabaseConnection/config.php'; // This is the database connection including the authentication details
require_once '../includes/userres.sn.php'; // User results viewing functions

checkSessionTimeout();

if (!isset($_SESSION["user_id"])) {
    header("location: ../pages/login.php");
    exit();
}

$selectedPollId = isset($_POST['poll_id']) ? intval($_POST['poll_id']) : null;
$elections = getElectionsWithTally($conn);
$results = ($selectedPollId) ? getUserElectionResults($conn, $selectedPollId) : [];
?>