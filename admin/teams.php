<?php
/**
 * Admin Teams Management
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Teams');

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
                $money = (float)($_POST['available_money'] ?? 0);

                if (empty($name)) {
                    $error = 'Team name is required.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO teams (name, available_money) VALUES (?, ?)");
                    try {
                        $stmt->execute([$name, $money]);
                        $success = "Team '{$name}' added successfully.";
                    } catch (PDOException $e) {
                        $error = 'Failed to add team: ' . $e->getMessage();
                    }
                }
                break;

            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $money = (float)($_POST['available_money'] ?? 0);

                if (empty($name) || $id <= 0) {
                    $error = 'Invalid team data.';
                } else {
                    $stmt = $pdo->prepare("UPDATE teams SET name = ?, available_money = ? WHERE id = ?");
                    try {
                        $stmt->execute([$name, $money, $id]);
                        $success = 'Team updated successfully.';
                    } catch (PDOException $e) {
                        $error = 'Failed to update team: ' . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Check if team has any bids
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids WHERE team_id = ?");
                    $stmt->execute([$id]);
                    if ($stmt->fetch()['count'] > 0) {
                        $error = 'Cannot delete team with existing bids.';
                    } else {
                        // Remove team association from members
                        $stmt = $pdo->prepare("UPDATE members SET team_id = NULL WHERE team_id = ?");
                        $stmt->execute([$id]);

                        $stmt = $pdo->prepare("DELETE FROM teams WHERE id = ?");
                        $stmt->execute([$id]);
                        $success = 'Team deleted successfully.';
                    }
                }
                break;
        }
    }
}

// Get all teams with member info
$teams = [];
if ($pdo) {
    $stmt = $pdo->query("
        SELECT t.*,
               m.name as member_name,
               m.email as member_email
        FROM teams t
        LEFT JOIN members m ON t.id = m.team_id AND m.is_active = 1
        ORDER BY t.name
    ");
    $teams = $stmt->fetchAll();
}

// Check for edit mode
$editTeam = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($teams as $team) {
        if ($team['id'] == $editId) {
            $editTeam = $team;
            break;
        }
    }
}

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Team Management</h2>
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

<!-- Add/Edit Team Form -->
<div class="card mb-3">
    <div class="card-header">
        <h3><?php echo $editTeam ? 'Edit Team' : 'Add New Team'; ?></h3>
        <?php if ($editTeam): ?>
            <a href="teams.php" class="btn btn-sm btn-secondary">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="<?php echo $editTeam ? 'edit' : 'add'; ?>">
            <?php if ($editTeam): ?>
                <input type="hidden" name="id" value="<?php echo $editTeam['id']; ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label for="name">Team Name</label>
                    <input type="text" id="name" name="name" class="form-control" required
                           value="<?php echo h($editTeam['name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="available_money">Available Budget ($)</label>
                    <input type="number" id="available_money" name="available_money" class="form-control"
                           step="1000000" min="0"
                           value="<?php echo h($editTeam['available_money'] ?? '100000000'); ?>">
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $editTeam ? 'Update Team' : 'Add Team'; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Teams List -->
<div class="card">
    <div class="card-header">
        <h3>All Teams (<?php echo count($teams); ?>)</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Budget</th>
                    <th>Assigned Member</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teams)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted">No teams added yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teams as $team): ?>
                        <tr>
                            <td><strong><?php echo getTeamDisplayHtml($team['name'], true); ?></strong></td>
                            <td><?php echo formatMoney($team['available_money']); ?></td>
                            <td>
                                <?php if ($team['member_name']): ?>
                                    <?php echo h($team['member_name']); ?>
                                    <span class="text-muted text-small">(<?php echo h($team['member_email']); ?>)</span>
                                <?php else: ?>
                                    <span class="text-muted">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?edit=<?php echo $team['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="POST" style="display: inline;" data-confirm="Are you sure you want to delete this team?">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $team['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
