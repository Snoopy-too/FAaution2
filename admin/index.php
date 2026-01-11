<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Dashboard');

$pdo = getDBConnection();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid security token.');
        redirect('index.php');
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'dismiss_update') {
        $version = $_POST['version'] ?? '';
        if ($version) {
            dismissUpdate($version);
            setFlashMessage('success', 'Update notification dismissed.');
        }
        redirect('index.php');
    }
}

// Get stats
$stats = [
    'teams' => 0,
    'members' => 0,
    'players' => 0,
    'bids' => 0
];

if ($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM teams");
    $stats['teams'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM members WHERE is_admin = 0");
    $stats['members'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM players WHERE archive_id IS NULL");
    $stats['players'] = $stmt->fetch()['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bids");
    $stats['bids'] = $stmt->fetch()['count'];
}

// Get auction status
$auctionClosed = isAuctionClosed();
$deadlineType = getSetting('deadline_type', 'manual');
$deadlineDatetime = getSetting('deadline_datetime');

// Get recent bids
$recentBids = [];
if ($pdo) {
    $stmt = $pdo->query("
        SELECT b.*, p.first_name, p.last_name, t.name as team_name
        FROM bids b
        JOIN players p ON b.player_id = p.id
        JOIN teams t ON b.team_id = t.id
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    $recentBids = $stmt->fetchAll();
}

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Admin Dashboard</h2>
    <div class="user-info">
        <span>Welcome, <?php echo h(getCurrentUserName()); ?></span>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<?php
// Check for updates
$updateInfo = checkForUpdates();
if ($updateInfo && $updateInfo['available'] && !isUpdateDismissed($updateInfo['latest_version'])):
?>
    <div class="card mb-3" style="border-left: 4px solid var(--primary); background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);">
        <div class="card-body">
            <div style="display: flex; align-items: center; gap: 15px;">
                <div style="font-size: 32px;">🎉</div>
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 5px 0; color: var(--primary);">
                        Update Available: v<?php echo h($updateInfo['latest_version']); ?>
                    </h3>
                    <p style="margin: 0; color: var(--gray-700);">
                        You're currently on v<?php echo h($updateInfo['current_version']); ?>. 
                        A new version is ready to install.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="update.php" class="btn btn-primary">Update Now</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="dismiss_update">
                        <input type="hidden" name="version" value="<?php echo h($updateInfo['latest_version']); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <button type="submit" class="btn btn-secondary btn-sm">Dismiss</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Auction Status -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Auction Status</h3>
        <?php if ($deadlineType === 'manual'): ?>
            <form method="POST" action="settings.php" style="display: inline;">
                <input type="hidden" name="action" value="toggle_auction">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <?php if ($auctionClosed): ?>
                    <button type="submit" class="btn btn-success btn-sm">Open Auction</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-danger btn-sm">Close Auction</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if ($auctionClosed): ?>
            <span class="badge badge-danger">CLOSED</span>
            <span class="text-muted ml-2">Bidding is currently closed</span>
        <?php else: ?>
            <span class="badge badge-success">OPEN</span>
            <?php if ($deadlineType === 'datetime' && $deadlineDatetime): ?>
                <span class="text-muted ml-2">Closes: <?php echo formatDateTime($deadlineDatetime); ?></span>
            <?php else: ?>
                <span class="text-muted ml-2">Manual mode - click button to close</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Teams</div>
        <div class="stat-value"><?php echo number_format($stats['teams']); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Members</div>
        <div class="stat-value"><?php echo number_format($stats['members']); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Players</div>
        <div class="stat-value"><?php echo number_format($stats['players']); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Bids</div>
        <div class="stat-value"><?php echo number_format($stats['bids']); ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Quick Actions</h3>
    </div>
    <div class="card-body">
        <div class="d-flex gap-2">
            <a href="teams.php" class="btn btn-primary">Add Team</a>
            <a href="players.php" class="btn btn-primary">Import Players</a>
            <a href="members.php" class="btn btn-secondary">Manage Members</a>
            <a href="settings.php" class="btn btn-secondary">Settings</a>
        </div>
    </div>
</div>

<!-- Recent Bids -->
<div class="card">
    <div class="card-header">
        <h3>Recent Bids</h3>
        <a href="bids.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Player</th>
                    <th>Team</th>
                    <th>Amount</th>
                    <th>Years</th>
                    <th>Total</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentBids)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No bids yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentBids as $bid): ?>
                        <tr>
                            <td><?php echo h($bid['first_name'] . ' ' . $bid['last_name']); ?></td>
                            <td><?php echo getTeamDisplayHtml($bid['team_name']); ?></td>
                            <td><?php echo formatMoney($bid['amount_per_year']); ?></td>
                            <td><?php echo $bid['years']; ?></td>
                            <td><strong><?php echo formatMoney($bid['total_value']); ?></strong></td>
                            <td><?php echo formatDateTime($bid['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
