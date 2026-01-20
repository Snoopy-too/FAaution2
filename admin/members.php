<?php
/**
 * Admin Members Management
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Members');

$pdo = getDBConnection();
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add':
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;

                if (empty($name) || empty($email) || empty($password)) {
                    $error = 'Name, email and password are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters.';
                } else {
                    // Check if email exists
                    $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $error = 'Email already registered.';
                    } else {
                        // Check if team is taken
                        if ($teamId) {
                            $stmt = $pdo->prepare("SELECT id FROM members WHERE team_id = ? AND is_active = 1");
                            $stmt->execute([$teamId]);
                            if ($stmt->fetch()) {
                                $error = 'This team is already assigned to another active member.';
                            }
                        }

                        if (!$error) {
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("INSERT INTO members (name, email, password, team_id, is_admin, is_active) VALUES (?, ?, ?, ?, 0, 1)");
                            try {
                                $stmt->execute([$name, $email, $hashedPassword, $teamId]);
                                $success = "Member '{$name}' created successfully.";
                            } catch (PDOException $e) {
                                $error = 'Failed to create member: ' . $e->getMessage();
                            }
                        }
                    }
                }
                break;

            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $teamId = !empty($_POST['team_id']) ? (int)$_POST['team_id'] : null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if (empty($name) || empty($email) || $id <= 0) {
                    $error = 'Invalid member data.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Invalid email address.';
                } else {
                    // Check if email exists for another user
                    $stmt = $pdo->prepare("SELECT id FROM members WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Email already registered to another user.';
                    } else {
                        // Check if team is taken by another user
                        if ($teamId) {
                            $stmt = $pdo->prepare("SELECT id FROM members WHERE team_id = ? AND id != ? AND is_active = 1");
                            $stmt->execute([$teamId, $id]);
                            if ($stmt->fetch()) {
                                $error = 'This team is already assigned to another active member.';
                            }
                        }

                        if (!$error) {
                            // If setting inactive, remove team association
                            if (!$isActive) {
                                $teamId = null;
                            }

                            if (!empty($password)) {
                                if (strlen($password) < 6) {
                                    $error = 'Password must be at least 6 characters.';
                                } else {
                                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                                    $stmt = $pdo->prepare("UPDATE members SET name = ?, email = ?, password = ?, team_id = ?, is_active = ? WHERE id = ?");
                                    $stmt->execute([$name, $email, $hashedPassword, $teamId, $isActive, $id]);
                                }
                            } else {
                                $stmt = $pdo->prepare("UPDATE members SET name = ?, email = ?, team_id = ?, is_active = ? WHERE id = ?");
                                $stmt->execute([$name, $email, $teamId, $isActive, $id]);
                            }

                            if (!$error) {
                                $success = 'Member updated successfully.';
                            }
                        }
                    }
                }
                break;

            case 'toggle_active':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $member = getMemberById($id);
                    if ($member && !$member['is_admin']) {
                        $newStatus = $member['is_active'] ? 0 : 1;
                        // If deactivating, remove team association
                        if (!$newStatus) {
                            $stmt = $pdo->prepare("UPDATE members SET is_active = ?, team_id = NULL WHERE id = ?");
                            $stmt->execute([$newStatus, $id]);
                        } else {
                            $stmt = $pdo->prepare("UPDATE members SET is_active = ? WHERE id = ?");
                            $stmt->execute([$newStatus, $id]);
                        }
                        $success = 'Member status updated.';
                    }
                }
                break;
        }
    }
}

// Get all members
$members = getAllMembers();
$allTeams = getAllTeams();

// Check for edit mode
$editMember = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $editMember = getMemberById($editId);
}

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Member Management</h2>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>

<!-- Add/Edit Member Form -->
<div class="card mb-3">
    <div class="card-header">
        <h3><?php echo $editMember ? 'Edit Member' : 'Add New Member'; ?></h3>
        <?php if ($editMember): ?>
            <a href="members.php" class="btn btn-sm btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $editMember ? 'edit' : 'add'; ?>">
            <?php if ($editMember): ?>
                <input type="hidden" name="id" value="<?php echo $editMember['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Name</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?php echo h($editMember['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" required
                           value="<?php echo h($editMember['email'] ?? ''); ?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password <?php echo $editMember ? '(leave blank to keep current)' : ''; ?></label>
                    <input type="password" id="password" name="password" class="form-control"
                           <?php echo !$editMember ? 'required' : ''; ?> minlength="6">
                </div>
                <div class="form-group">
                    <label for="team_id">Team</label>
                    <select id="team_id" name="team_id" class="form-control">
                        <option value="">-- No Team --</option>
                        <?php foreach ($allTeams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"
                                <?php echo (($editMember['team_id'] ?? '') == $team['id']) ? 'selected' : ''; ?>>
                                <?php echo h($team['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($editMember && !$editMember['is_admin']): ?>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" value="1"
                               <?php echo $editMember['is_active'] ? 'checked' : ''; ?>>
                        Active Account
                    </label>
                    <small class="text-muted" style="display: block;">Deactivating will also remove team association</small>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">
                <?php echo $editMember ? 'Update Member' : 'Add Member'; ?>
            </button>
        </form>
    </div>
</div>

<!-- Members List -->
<div class="card">
    <div class="card-header">
        <h3>All Members (<?php echo count($members); ?>)</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Team</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><strong><?php echo h($member['name']); ?></strong></td>
                        <td><?php echo h($member['email']); ?></td>
                        <td>
                            <?php if ($member['team_name']): ?>
                                <?php echo getTeamDisplayHtml($member['team_name']); ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($member['is_admin']): ?>
                                <span class="badge badge-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Member</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($member['is_active']): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?edit=<?php echo $member['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            <?php if (!$member['is_admin']): ?>
                                <form method="POST" style="display: inline;" data-confirm="<?php echo $member['is_active'] ? 'Deactivate this member? This will also remove their team association.' : 'Activate this member?'; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="id" value="<?php echo $member['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $member['is_active'] ? 'btn-danger' : 'btn-success'; ?>">
                                        <?php echo $member['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
