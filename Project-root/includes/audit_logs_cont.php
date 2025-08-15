<?php
require_once '../DatabaseConnection/config.php';
require_once '../includes/functions.sn.php';
require_once '../includes/admin_logs.sn.php';

// Get filters from GET
$filterEventType = $_GET['event_type'] ?? '';
$filterAdminId = $_GET['admin_id'] ?? '';

// --- CODE FOR PAGINATION ---
// 1. Define pagination variables
$limit = 5; // Number of logs per page
$currentPage = $_GET['page'] ?? 1;
// 2. Get the total number of logs
$totalLogs = getTotalAdminAuditLogsCount($conn, $filterEventType, $filterAdminId);
// 3. Calculate the total number of pages
$totalPages = ceil($totalLogs / $limit);
// 4. Calculate the offset for the SQL query
$offset = ($currentPage - 1) * $limit;
// --- END OF PAGINATION CODE ---

// Fetch logs (encrypted names & IV)
$auditLogs = getAdminAuditLogs($conn, $filterEventType, $filterAdminId, $limit, $offset);

// Fetch event types
$eventTypes = getAuditLogEventTypes($conn);

// Fetch admins (encrypted names & IV)
$adminsRaw = getAuditLogAdmins($conn);
// Decrypt names for dropdown
$admins = [];
foreach ($adminsRaw as $admin) {
    $admin['display_name'] = decryptAdminNameFromRow($admin);
    $admins[] = $admin;
}

// For the logs table, decrypt names now so the view stays simple
foreach ($auditLogs as &$log) {
    $log['display_name'] = decryptAdminNameForLog($log['first_name'], $log['last_name'], $log['admin_iv']);
}
?>

<div class="admin-section">
    <h2>Admin Audit Logs</h2>
    <form method="get" style="margin-bottom: 18px;">
        <select name="event_type" class="styled-select">
            <option value="">All Event Types</option>
            <?php foreach ($eventTypes as $type): ?>
                <option value="<?= htmlspecialchars($type['event_type']) ?>" <?= ($filterEventType === $type['event_type']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($type['event_type']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="admin_id" class="styled-select">
            <option value="">All Admins</option>
            <?php foreach ($admins as $admin): ?>
                <option value="<?= $admin['admin_id'] ?>" <?= ($filterAdminId == $admin['admin_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($admin['display_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn-filter">Filter</button>
    </form>
    <div class="table-scroll-container">
        <table class="styled-table log-table">
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Admin Name</th>
                    <th>Event Type</th>
                    <th>Details</th>
                    <th>Time</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($auditLogs): foreach ($auditLogs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['log_id']) ?></td>
                    <td><?= htmlspecialchars($log['display_name']) ?></td>
                    <td><?= htmlspecialchars($log['event_type']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                    <td><?= htmlspecialchars($log['event_time']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="6" style="text-align:center;">No audit logs found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination-buttons">
            <?php if ($currentPage > 1): ?>
                <a href="?page=<?= $currentPage - 1; ?>&event_type=<?= urlencode($filterEventType) ?>&admin_id=<?= urlencode($filterAdminId) ?>" class="pagination-btn prev-btn">Previous</a>
            <?php endif; ?>

            <span class="pagination-info">Page <?= $currentPage; ?> of <?= $totalPages; ?></span>

            <?php if ($currentPage < $totalPages): ?>
                <a href="?page=<?= $currentPage + 1; ?>&event_type=<?= urlencode($filterEventType) ?>&admin_id=<?= urlencode($filterAdminId) ?>" class="pagination-btn next-btn">Next</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<style>
/* Your existing styles are here. The new styles for pagination are added below. */
.styled-select {
    width: 20%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background-color: #f8f9fa;
    font-size: 1rem;
    transition: border-color 0.3s ease-in-out;
    margin-top: 0.25rem;
    margin-bottom: 1rem;
}

.styled-select:focus {
    border-color: #5542ab;
    outline: none;
    box-shadow: 0 0 0 3px rgba(85, 66, 171, 0.1);
}

.btn-filter { 
    background: #4CAF50; 
    color: #fff; 
    border: none; 
    padding: 8px 18px; 
    border-radius: 4px; 
    margin-left: 10px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: 
    background 0.2s;
}

.table-scroll-container {
    max-height: 420px;
    overflow-y: auto;
    min-width: 100%;
    width: 100%;
    margin-bottom: 30px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.styled-table.log-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
    font-size: 0.99rem;
    background: #fff;
}
.styled-table.log-table th,
.styled-table.log-table td {
    padding: 10px 12px;
    text-align: left;
}
.styled-table.log-table thead tr {
    background: #5dbb3b;
    color: #fff;
}
.styled-table.log-table tbody tr {
    background-color: #f9f9f9;
}
.styled-table.log-table tbody tr:nth-of-type(even) {
    background-color: #ececec;
}
.styled-table.log-table tbody tr:hover {
    background-color: #a495c7; /* Highlight color for hovered rows */
}
@media (max-width: 1000px) {
    .styled-table.log-table {
        font-size: 0.91rem;
        min-width: 650px;
    }
}
/*  CSS for pagination */
.pagination-buttons {
    margin-top: 20px;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}

.pagination-btn {
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 500;
    font-size: 0.85rem;
    cursor: pointer;
    border: none;
    transition: background-color 0.2s ease;
}

/* Style for the 'Previous' button */
.pagination-btn.prev-btn {
    background-color: #e5e7eb;
    color: #6b7280;
}

/* Style for the 'Next' button */
.pagination-btn.next-btn {
    background-color: #8b5cf6;
    color: white;
}

/* Page info text */
.pagination-info {
    font-size: 0.9rem;
    font-weight: 500;
    color: #4b5563;
}
</style>