<?php
require_once '../includes/user_profile-cont.php'; // Controller sets $user, $lastLogin, etc.
$avatarFs = '../Assets/img/avatar.jpg';
$avatarUrl = (is_file($avatarFs)) ? '../Assets/img/avatar.jpg?v=' . (@filemtime($avatarFs) ?: time()) : 'https://www.svgrepo.com/show/510930/user-circle.svg';
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Votify - User Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <script type="module" src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@7.1.0/dist/ionicons/ionicons.js"></script>
    <link rel="stylesheet" href="../Assets/css/User_Profile.css" />
    <link rel="preload" as="image" href="../Assets/img/avatar.jpg" />
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
                <li><a href="User_Home.php"><span class="icon"><ion-icon name="home-outline"></ion-icon></span><span class="text">Home</span></a></li>
                <li><a href="User_Profile.php" class="active"><span class="icon"><ion-icon name="people-outline"></ion-icon></span><span class="text">Profile</span></a></li>
                <li><a href="User_Election.php"><span class="icon"><ion-icon name="checkmark-done-circle-outline"></ion-icon></span><span class="text">Election</span></a></li>
                <li><a href="User_Result.php"><span class="icon"><ion-icon name="eye-outline"></ion-icon></span><span class="text">Result</span></a></li>
                <li><a href="User_Settings.php"><span class="icon"><ion-icon name="settings-outline"></ion-icon></span><span class="text">Settings</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="../includes/Logout.php" class="footer-link signout-link"><span class="icon"><ion-icon name="log-out-outline"></ion-icon></span><span class="text">Sign Out</span></a>
        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar" role="img" aria-label="User profile picture"
                    style="background-image:url('<?= htmlspecialchars($avatarUrl, ENT_QUOTES) ?>');"></div>
                <div class="profile-header-content">
                    <h1 class="profile-name" id="profile-name">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </h1>
                    <div class="last-login">Last login: <?= htmlspecialchars($lastLogin) ?></div>
                    <button class="change-password-btn" onclick="showPasswordModal()">Change Password</button>
                </div>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="person-outline"></ion-icon>
                    <span>Personal Information</span>
                    <button class="edit-btn" id="edit-personal-info" title="Edit personal info">
                        <ion-icon name="create-outline"></ion-icon>
                    </button>
                </div>
                <table class="personal-info-table">
                    <tr>
                        <td class="info-label">Voter ID</td>
                        <td id="voter-id-value"><?= htmlspecialchars($user['id'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">First Name</td>
                        <td id="first-name-value"><?= htmlspecialchars($user['first_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Last Name</td>
                        <td id="last-name-value"><?= htmlspecialchars($user['last_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Date of Birth</td>
                        <td id="dob-value"><?= htmlspecialchars($user['dob'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <td class="info-label">Email</td>
                        <td id="email-value"><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    </tr>
                </table>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="document-text-outline"></ion-icon>
                    <span>Election Overview</span>
                </div>
                <?php if (empty($user['elections'])): ?>
                    <p>There are no current elections available.</p>
                <?php else: ?>
                    <table class="elections-table">
                        <thead>
                            <tr>
                                <th>Election</th>
                                <th>Enrolment Status</th>
                                <th>Vote Status</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user['elections'] as $election): ?>
                                <tr>
                                    <td><?= htmlspecialchars($election['name']) ?></td>
                                    <td><span class="status-badge enrolled">Enrolled</span></td>
                                    <td>
                                        <span class="status-badge <?= $election['voted'] ? 'voted' : 'not-voted' ?>">
                                            <?= $election['voted'] ? 'Voted' : 'Not Voted' ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($election['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <div class="section-title">
                    <ion-icon name="home-outline"></ion-icon>
                    <span>Addresses</span>
                </div>
                <div class="address-section">
                    <div class="address-line">
                        <ion-icon name="location-outline"></ion-icon>
                        <span>Residential: <?= htmlspecialchars($user['address'] ?? '') ?></span>
                    </div>
                    <br />
                    <button class="manage-address-btn" id="manage-address-btn">Manage Addresses</button>
                </div>
            </div>

            <div class="action-section">
                <a href="../pages/FAQs.php" class="action-link" id="help-info-link" target="_blank">
                    <ion-icon name="help-circle-outline"></ion-icon>
                    Help &amp; Info
                </a>
            </div>

            <div class="action-section">
                <a href="../pages/contact.html" class="action-link" id="provide-feedback" target="_blank">
                    <ion-icon name="chatbubble-ellipses-outline"></ion-icon>
                    Provide Feedback
                </a>
            </div>
        </div>
    </main>

    <!-- Password Modal -->
    <div id="passwordModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Change Password</h3>
            </div>
            <form id="passwordForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="change_password" />
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
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="apply-password">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Personal Info Modal -->
    <div id="personalInfoModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Edit Personal Information</h3>
            </div>
            <form id="personalInfoForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_profile" />
                <div class="form-group">
                    <label for="modal-voter-id">Voter ID</label>
                    <input type="text" id="modal-voter-id" value="<?= htmlspecialchars($user['id'] ?? '') ?>" readonly>
                </div>
                <div class="form-group">
                    <label for="modal-first-name">First Name</label>
                    <input type="text" id="modal-first-name" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-last-name">Last Name</label>
                    <input type="text" id="modal-last-name" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-dob">D.O.B</label>
                    <input type="date" id="modal-dob" name="date_of_birth" value="<?= htmlspecialchars($user['dob'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="modal-email">Email</label>
                    <input type="email" id="modal-email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="save-personal-info">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Address Modal -->
    <div id="addressModal" class="modal" aria-hidden="true">
        <div class="modal-content fancy">
            <div class="modal-header">
                <h3 class="modal-title">Manage Addresses</h3>
            </div>
            <form id="addressForm" method="POST" autocomplete="off">
                <input type="hidden" name="action" value="update_address" />
                <div class="form-group">
                    <label for="modal-address">Residential Address</label>
                    <textarea id="modal-address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
                <div class="modal-actions modal-buttons">
                    <button type="button" class="btn btn-cancel cancel-btn">Cancel</button>
                    <button type="submit" class="btn btn-confirm green-btn" id="save-address">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal helpers
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

        // Inline confirm
        function inlineConfirm(modalId, message = 'Apply changes?') {
            return new Promise(resolve => {
                const modal = document.getElementById(modalId);
                const content = modal?.querySelector('.modal-content');
                if (!content) {
                    resolve(true);
                    return;
                }

                const existing = content.querySelector('.inline-confirm');
                if (existing) existing.remove();
                content.style.position = 'relative';

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

                const noBtn = document.createElement('button');
                noBtn.type = 'button';
                noBtn.className = 'cancel-btn';
                noBtn.textContent = 'Discard';
                const yesBtn = document.createElement('button');
                yesBtn.type = 'button';
                yesBtn.className = 'green-btn';
                yesBtn.textContent = 'Apply';

                btns.append(noBtn, yesBtn);
                box.append(icon, text, btns);
                overlay.append(box);
                content.append(overlay);

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

        function showPasswordModal() {
            const f = document.getElementById('passwordForm');
            if (f) {
                f.reset();
                ['currentPassword', 'newPassword', 'verifyPassword'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = '';
                        el.setAttribute('autocomplete', id === 'currentPassword' ? 'current-password' : 'new-password');
                    }
                });
            }
            openModal('passwordModal');
        }

        // Improved AJAX handler
        async function handleAjaxRequest(formElement, successCallback) {
            try {
                const formData = new FormData(formElement);
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });

                const text = await response.text();
                let data = null;
                
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid server response');
                }

                if (!response.ok) {
                    throw new Error(data?.message || 'Request failed');
                }

                if (!data || typeof data.success === 'undefined') {
                    throw new Error('Invalid response format');
                }

                if (!data.success) {
                    throw new Error(data.message || 'Operation failed');
                }

                if (successCallback) {
                    successCallback(data);
                }

                return data;
            } catch (error) {
                console.error('AJAX error:', error);
                alert('Error: ' + (error.message || 'Operation failed'));
                throw error;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Mobile menu toggle
            document.querySelector('.mobile-menu-toggle')?.addEventListener('click', () => {
                document.querySelector('.sidebar').classList.toggle('active');
            });

            // Modal open buttons
            document.getElementById('edit-personal-info')?.addEventListener('click', () => {
                openModal('personalInfoModal');
            });

            document.getElementById('manage-address-btn')?.addEventListener('click', () => {
                openModal('addressModal');
            });

            // Cancel buttons
            document.querySelectorAll('.cancel-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) {
                        if (modal.id === 'passwordModal') {
                            document.getElementById('passwordForm')?.reset();
                        }
                        closeModal(modal.id);
                    }
                });
            });

            // Close when clicking outside modal
            window.addEventListener('click', (e) => {
                if (e.target.classList.contains('modal')) {
                    closeModal(e.target.id);
                }
            });

            // Password form submission
            const pwForm = document.getElementById('passwordForm');
            pwForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('passwordModal', 'Apply password change?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        }
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });

            // Personal info form submission
            const piForm = document.getElementById('personalInfoForm');
            piForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('personalInfoModal', 'Apply profile changes?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        document.getElementById('profile-name').textContent = `${data.first_name} ${data.last_name}`;
                        document.getElementById('first-name-value').textContent = data.first_name;
                        document.getElementById('last-name-value').textContent = data.last_name;
                        document.getElementById('email-value').textContent = data.email;
                        document.getElementById('dob-value').textContent = data.dob;
                        closeModal('personalInfoModal');
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });

            // Address form submission
            const addrForm = document.getElementById('addressForm');
            addrForm?.addEventListener('submit', async function(e) {
                e.preventDefault();
                const ok = await inlineConfirm('addressModal', 'Apply address changes?');
                if (!ok) return;

                try {
                    await handleAjaxRequest(this, (data) => {
                        const firstLine = document.querySelector('.address-line span');
                        if (firstLine) firstLine.textContent = `Residential: ${data.address}`;
                        closeModal('addressModal');
                    });
                } catch (error) {
                    // Error already handled by handleAjaxRequest
                }
            });
        });
    </script>
</body>

</html>