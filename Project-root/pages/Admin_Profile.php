<?php
require_once '../controllers/admin_profile-cont.php'; // Controller sets $admin, $lastLogin, etc.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Votify - Admin Profile</title>
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="../Assets/css/Admin_Profile.css">
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle menu">
        <ion-icon name="menu-outline"></ion-icon>
    </button>
    <aside class="sidebar">
        <div class="sidebar-top-bar">
            <h3>Votify</h3>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="Admin_Home.php"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
                <li><a href="Admin_Profile.php" class="active"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
                <li><a href="Admin_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
                <li><a href="Admin_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
                <li><a href="Admin_FAQ.php"><span class="icon"><ion-icon name="help-outline"></ion-icon></span><span class="text">Manage FAQ</span></a></li>
                <li><a href="Admin_Documentation.php"><span class="icon"><ion-icon name="document-text"></ion-icon></span><span class="text">Manage Documentation</span></a></li>
                <li><a href="Admin_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../controllers/Logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
        </div>
    </aside>

    <main class="main-content">
        <section class="profile-card" aria-label="Admin Profile Information">
            <div class="profile-avatar" role="img" aria-label="Profile Avatar"></div>
            <div class="profile-info">
                <h1 id="admin-display-name"><?= htmlspecialchars(trim($admin['first_name'] . ' ' . $admin['last_name'])) ?></h1>
                <div class="admin-id">Admin ID: <span id="admin-display-id"><?= htmlspecialchars($admin['id'] ?? '') ?></span></div>
                <div class="admin-email" id="admin-display-email"><?= htmlspecialchars($admin['email']) ?></div>
                <div class="last-login">Last login: <?= htmlspecialchars($lastLogin) ?></div>
                <div class="action-buttons">
                    <button class="red-btn" id="open-password-modal">Change Password</button>
                </div>
            </div>
        </section>

        <section class="employee-info" aria-label="Employee Information">
            <div class="section-title">
                <ion-icon name="person-outline"></ion-icon>
                <span>Employee Information</span>
                <button class="edit-btn employee-edit" id="edit-employee-info" title="Edit employee info">
                    <ion-icon name="create-outline"></ion-icon>
                </button>
            </div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Admin ID</td>
                    <td id="emp-admin-id"><?= htmlspecialchars($admin['id'] ?? '') ?></td>
                </tr>
                <tr>
                    <td class="info-label">First Name</td>
                    <td id="emp-first-name"><?= htmlspecialchars($admin['first_name']) ?></td>
                </tr>
                <tr>
                    <td class="info-label">Last Name</td>
                    <td id="emp-last-name"><?= htmlspecialchars($admin['last_name']) ?></td>
                </tr>
                <tr>
                    <td class="info-label">D.O.B</td>
                    <td id="emp-dob"><?= htmlspecialchars($admin['dob'] ?: 'N/A') ?></td>
                </tr>
                <tr>
                    <td class="info-label">Work Email</td>
                    <td id="emp-email"><?= htmlspecialchars($admin['email']) ?></td>
                </tr>
            </table>
        </section>

        <section class="election-overview" aria-label="Election Overview">
        <div class="section-title">
            <ion-icon name="document-text-outline"></ion-icon>
            <span>Election Overview</span>
        </div>
        <?php if (count($elections) > 0): ?>
            <ul class="election-list">
                <li><strong>Total Elections:</strong> <?= count($elections) ?></li>
                <?php
                $now = new DateTime();
                foreach ($elections as $election):
                    $start = $election['start_datetime'] ? new DateTime($election['start_datetime']) : null;
                    $timeUntilStart = 'Schedule TBA';
                    if ($start) {
                        $interval = $now->diff($start);
                        $timeUntilStart = ($start > $now) ? $interval->format('%a days, %h hours') : 'Already started';
                    }
                ?>
                <li>
                    <strong><?= htmlspecialchars($election['election_name']) ?></strong>
                    <span class="start-info">Starts in: <?= htmlspecialchars($timeUntilStart) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <div class="election-actions">
                <div class="action-buttons">
                    <button class="green-btn" onclick="window.location.href='../pages/Admin_Election.php'">Election Details</button>
                </div>
            </div>
        <?php else: ?>
            <p>No elections found.</p>
            <div class="action-buttons">
                <button class="green-btn" onclick="window.location.href='../pages/Admin_Election.php'">Election Details</button>
            </div>
        <?php endif; ?>
    </section>

    </main>

    <!-- Change Password Modal -->
    <div id="passwordModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Change Password</h3>
            </div>
            <form id="passwordForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="change_admin_password" />
                <div class="form-group">
                    <label for="currentPassword">Current Password</label>
                    <input type="password" id="currentPassword" name="currentPassword" required autocomplete="current-password" inputmode="text">
                </div>
                <div class="form-group">
                    <label for="newPassword">New Password</label>
                    <input type="password" id="newPassword" name="newPassword" required autocomplete="new-password" inputmode="text">
                </div>
                <div class="form-group">
                    <label for="verifyPassword">Verify New Password</label>
                    <input type="password" id="verifyPassword" name="verifyPassword" required autocomplete="new-password" inputmode="text">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn btn-cancel">Cancel</button>
                    <button type="submit" class="green-btn btn-confirm">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Employee Info Modal -->
    <div id="employeeModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Edit Employee Information</h3>
            </div>
            <form id="employeeForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_admin_profile" />
                <div class="form-group">
                    <label for="modal-admin-id">Admin ID</label>
                    <input type="text" id="modal-admin-id" value="<?= htmlspecialchars($admin['id'] ?? '') ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="modal-first-name">First Name</label>
                    <input type="text" id="modal-first-name" name="first_name" value="<?= htmlspecialchars($admin['first_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="modal-last-name">Last Name</label>
                    <input type="text" id="modal-last-name" name="last_name" value="<?= htmlspecialchars($admin['last_name']) ?>">
                </div>
                <div class="form-group">
                    <label for="modal-dob">D.O.B</label>
                    <input type="date" id="modal-dob" name="dob" placeholder="YYYY-MM-DD" value="<?= htmlspecialchars($admin['dob']) ?>">
                </div>
                <div class="form-group">
                    <label for="modal-email">Work Email</label>
                    <input type="email" id="modal-email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" readonly>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="cancel-btn btn-cancel">Cancel</button>
                    <button type="submit" class="green-btn btn-confirm" id="save-employee">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu toggle functionality
        document.querySelector('.mobile-menu-toggle')?.addEventListener('click', () => {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Helper functions to open and close modals
        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'block';
                el.setAttribute('aria-hidden', 'false');
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
                el.setAttribute('aria-hidden', 'true');
            }
        }

        // Inline confirmation dialog for form submissions
        function inlineConfirm(modalId, message = 'Apply changes?') {
            return new Promise(resolve => {
                const modal = document.getElementById(modalId);
                const content = modal?.querySelector('.modal-content');
                if (!content) {
                    resolve(true); // If content not found, proceed without confirmation
                    return;
                }

                // Remove any existing confirmation overlay
                const existing = content.querySelector('.inline-confirm');
                if (existing) existing.remove();
                content.style.position = 'relative'; // Ensure relative positioning for overlay

                // Create overlay elements
                const overlay = document.createElement('div');
                overlay.className = 'inline-confirm';
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');

                const box = document.createElement('div');
                box.className = 'inline-confirm-box';

                const icon = document.createElement('ion-icon');
                icon.setAttribute('name', 'alert-circle-outline');
                icon.className = 'inline-confirm-icon';

                const text = document.createElement('div');
                text.className = 'inline-confirm-text';
                text.textContent = message;

                const btns = document.createElement('div');
                btns.className = 'inline-confirm-actions';

                // Create confirmation buttons
                const noBtn = document.createElement('button');
                noBtn.type = 'button';
                noBtn.className = 'cancel-btn';
                noBtn.textContent = 'Discard';
                const yesBtn = document.createElement('button');
                yesBtn.type = 'button';
                yesBtn.className = 'green-btn';
                yesBtn.textContent = 'Apply';

                // Append elements to construct the overlay
                btns.append(noBtn, yesBtn);
                box.append(icon, text, btns);
                overlay.append(box);
                content.append(overlay);

                // Cleanup function to remove overlay and resolve promise
                const cleanup = (v) => {
                    overlay.remove();
                    resolve(v);
                };
                noBtn.addEventListener('click', () => cleanup(false), {
                    once: true
                });
                yesBtn.addEventListener('click', () => cleanup(true), {
                    once: true
                });
            });
        }

        // Event listener to open the change password modal
        document.getElementById('open-password-modal')?.addEventListener('click', () => {
            const f = document.getElementById('passwordForm');
            if (f) f.reset(); // Reset form fields
            openModal('passwordModal');
        });

        // Event listener to open the edit employee info modal
        document.getElementById('edit-employee-info')?.addEventListener('click', () => {
            // Refresh modal input fields with current displayed data from the table
            document.getElementById('modal-first-name').value = document.getElementById('emp-first-name').textContent.trim();
            document.getElementById('modal-last-name').value = document.getElementById('emp-last-name').textContent.trim();
            document.getElementById('modal-email').value = document.getElementById('emp-email').textContent.trim();
            // Handle D.O.B special case: if 'N/A', set to empty string for the date input
            document.getElementById('modal-dob').value = document.getElementById('emp-dob').textContent.trim() === 'N/A' ? '' : document.getElementById('emp-dob').textContent.trim();
            openModal('employeeModal');
        });

        // Event listeners for "Cancel" buttons to close their respective modals
        document.querySelectorAll('.btn-cancel').forEach(btn => {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal');
                if (modal) closeModal(modal.id);
            });
        });

        // Event listener to close modals when clicking outside the modal content
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) closeModal(e.target.id);
        });

        // Handle password change form submission via AJAX
        document.getElementById('passwordForm')?.addEventListener('submit', async function(e) {
            e.preventDefault(); // Prevent default form submission
            const ok = await inlineConfirm('passwordModal', 'Apply password change?'); // Show confirmation
            if (!ok) return; // If cancelled, stop

            const fd = new FormData(this);
            fd.set('action', 'change_admin_password'); // Set action for PHP script

            fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest', // Indicate AJAX request
                        'Accept': 'application/json' // Expect JSON response
                    }
                })
                .then(async r => {
                    const t = await r.text(); // Get raw response text
                    let d = null;
                    try {
                        d = JSON.parse(t); // Attempt to parse as JSON
                    } catch (_) {
                        // If parsing fails, d remains null. This means the server response was not valid JSON.
                        console.error('JSON Parse Error: Server response was not valid JSON:', t);
                        // Log the raw response text for debugging
                        console.error('Raw server response:', t);
                    }
                    if (!r.ok) { // Check if HTTP response was successful (2xx status)
                        // If not OK, throw an error, prioritizing JSON message if available
                        throw new Error(d?.message || t || 'Request failed');
                    }
                    return d; // Return parsed JSON data
                })
                .then(d => {
                    if (d && d.success) { // Check if parsed data exists and 'success' is true
                        window.location.replace(d.redirect || '../pages/stafflogin.php'); // Redirect on success
                    } else {
                        // Show error message from server or a generic one
                        alert('Error: ' + (d?.message || 'Update failed'));
                    }
                })
                .catch(err => alert('An error occurred while updating password. ' + (err?.message || ''))); // Catch network or other errors
        });

        // Handle employee profile update form submission via AJAX
        document.getElementById('employeeForm')?.addEventListener('submit', async function(e) {
            e.preventDefault(); // Prevent default form submission
            const ok = await inlineConfirm('employeeModal', 'Apply profile changes?'); // Show confirmation
            if (!ok) return; // If cancelled, stop

            const fd = new FormData(this);
            fd.set('action', 'update_admin_profile'); // Set action for PHP script

            fetch(window.location.href, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest', // Indicate AJAX request
                        'Accept': 'application/json' // Expect JSON response
                    }
                })
                .then(async r => {
                    const t = await r.text(); // Get raw response text
                    let d = null;
                    try {
                        d = JSON.parse(t); // Attempt to parse as JSON
                    } catch (_) {
                        // If parsing fails, d remains null. This means the server response was not valid JSON.
                        console.error('JSON Parse Error: Server response was not valid JSON:', t);
                        // Log the raw response text for debugging
                        console.error('Raw server response:', t);
                    }
                    if (!r.ok) { // Check if HTTP response was successful (2xx status)
                        // If not OK, throw an error, prioritizing JSON message if available
                        throw new Error(d?.message || t || 'Request failed');
                    }
                    return d; // Return parsed JSON data
                })
                .then(d => {
                    if (d && d.success) { // Check if parsed data exists and 'success' is true
                        // Update UI elements with new data
                        document.getElementById('admin-display-name').textContent = `${d.first_name} ${d.last_name}`;
                        document.getElementById('admin-display-email').textContent = d.email;

                        document.getElementById('emp-first-name').textContent = d.first_name;
                        document.getElementById('emp-last-name').textContent = d.last_name;
                        document.getElementById('emp-email').textContent = d.email;
                        // Update D.O.B, using 'N/A' if value is empty/null
                        document.getElementById('emp-dob').textContent = d.dob || 'N/A';

                        closeModal('employeeModal'); // Close modal on success
                    } else {
                        // Show error message from server or a generic one
                        alert('Error: ' + (d?.message || 'Update failed'));
                    }
                })
                .catch(err => alert('An error occurred while updating profile. ' + (err?.message || ''))); // Catch network or other errors
        });
    </script>
</body>

</html>