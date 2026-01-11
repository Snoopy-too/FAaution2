<?php
/**
 * Admin Bids Management
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Bids');

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
            case 'edit':
                $id = (int)($_POST['id'] ?? 0);
                $amountPerYear = (float)($_POST['amount_per_year'] ?? 0);
                $years = (int)($_POST['years'] ?? 0);

                if ($id <= 0 || $amountPerYear <= 0 || $years <= 0) {
                    $error = 'Invalid bid data.';
                } else {
                    $totalValue = $amountPerYear * $years;
                    $stmt = $pdo->prepare("UPDATE bids SET amount_per_year = ?, years = ?, total_value = ? WHERE id = ?");
                    try {
                        $stmt->execute([$amountPerYear, $years, $totalValue, $id]);
                        $success = 'Bid updated successfully.';
                    } catch (PDOException $e) {
                        $error = 'Failed to update bid: ' . $e->getMessage();
                    }
                }
                break;

            case 'delete':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM bids WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Bid deleted successfully.';
                }
                break;
        }
    }
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Filter
$filterTeam = $_GET['team'] ?? '';
$filterPlayer = $_GET['player'] ?? '';

// Build query
$where = [];
$params = [];

if ($filterTeam) {
    $where[] = "b.team_id = ?";
    $params[] = (int)$filterTeam;
}

if ($filterPlayer) {
    $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ?)";
    $searchTerm = "%{$filterPlayer}%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count
    FROM bids b
    JOIN players p ON b.player_id = p.id
    {$whereClause}
");
$stmt->execute($params);
$totalBids = $stmt->fetch()['count'];
$totalPages = ceil($totalBids / $perPage);

// Get bids
$stmt = $pdo->prepare("
    SELECT b.*,
           p.first_name, p.last_name, p.position,
           t.name as team_name,
           m.name as member_name
    FROM bids b
    JOIN players p ON b.player_id = p.id
    JOIN teams t ON b.team_id = t.id
    JOIN members m ON b.member_id = m.id
    {$whereClause}
    ORDER BY b.created_at DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$bids = $stmt->fetchAll();

// Get teams for filter
$teams = getAllTeams();

// Check for edit mode
$editBid = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("
        SELECT b.*, p.first_name, p.last_name, t.name as team_name
        FROM bids b
        JOIN players p ON b.player_id = p.id
        JOIN teams t ON b.team_id = t.id
        WHERE b.id = ?
    ");
    $stmt->execute([$editId]);
    $editBid = $stmt->fetch();
}

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Bid Management</h2>
    <div>
        <span class="text-muted"><?php echo number_format($totalBids); ?> bids</span>
    </div>
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

<?php if ($editBid): ?>
<!-- Edit Bid Form -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Edit Bid</h3>
        <a href="bids.php" class="btn btn-sm btn-secondary">Cancel</a>
    </div>
    <div class="card-body">
        <p class="mb-2">
            <strong><?php echo h($editBid['first_name'] . ' ' . $editBid['last_name']); ?></strong>
            - <?php echo h($editBid['team_name']); ?>
        </p>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo $editBid['id']; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="amount_per_year">Amount Per Year ($)</label>
                    <input type="number" id="amount_per_year" name="amount_per_year" class="form-control"
                           step="100000" min="0" required
                           value="<?php echo h($editBid['amount_per_year']); ?>">
                </div>
                <div class="form-group">
                    <label for="years">Years</label>
                    <input type="number" id="years" name="years" class="form-control"
                           min="1" max="15" required
                           value="<?php echo h($editBid['years']); ?>">
                </div>
                <div class="form-group" style="align-self: flex-end;">
                    <button type="submit" class="btn btn-primary">Update Bid</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <input type="text" name="player" class="form-control" placeholder="Search player..."
                   value="<?php echo h($filterPlayer); ?>">
            <select name="team" class="form-control">
                <option value="">All Teams</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?php echo $team['id']; ?>" <?php echo $filterTeam == $team['id'] ? 'selected' : ''; ?>>
                        <?php echo h($team['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($filterTeam || $filterPlayer): ?>
                <a href="bids.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Bids List -->
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>$/Year</th>
                    <th>Years</th>
                    <th>Total</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bids)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted">No bids found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($bids as $bid): ?>
                        <?php
                        // Check if this is the leading bid for this player
                        $highestBid = getHighestBid($bid['player_id']);
                        $isLeading = $highestBid && $highestBid['id'] == $bid['id'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo h($bid['first_name'] . ' ' . $bid['last_name']); ?></strong>
                                <span class="badge badge-secondary"><?php echo getPositionAbbr($bid['position']); ?></span>
                            </td>
                            <td><?php echo getTeamDisplayHtml($bid['team_name']); ?></td>
                            <td><?php echo formatMoney($bid['amount_per_year']); ?></td>
                            <td><?php echo $bid['years']; ?></td>
                            <td>
                                <strong><?php echo formatMoney($bid['total_value']); ?></strong>
                                <?php if ($isLeading): ?>
                                    <span class="badge badge-success">Leading</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($bid['is_opening_bid']): ?>
                                    <span class="badge badge-primary">Opening</span>
                                <?php else: ?>
                                    <span class="text-muted">Regular</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDateTime($bid['created_at']); ?></td>
                            <td>
                                <a href="?edit=<?php echo $bid['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                <form method="POST" style="display: inline;"
                                      onsubmit="return confirm('Delete this bid?');">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $bid['id']; ?>">
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>&team=<?php echo $filterTeam; ?>&player=<?php echo urlencode($filterPlayer); ?>">
                &laquo; Prev
            </a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?>&team=<?php echo $filterTeam; ?>&player=<?php echo urlencode($filterPlayer); ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&team=<?php echo $filterTeam; ?>&player=<?php echo urlencode($filterPlayer); ?>">
                Next &raquo;
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
