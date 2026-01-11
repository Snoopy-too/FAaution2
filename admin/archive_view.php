<?php
/**
 * Admin Archive Detail View
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pdo = getDBConnection();
$archiveId = (int)($_GET['id'] ?? 0);

if (!$archiveId) {
    setFlashMessage('error', 'Invalid archive.');
    redirect('archives.php');
}

$archive = getArchiveById($archiveId);
if (!$archive) {
    setFlashMessage('error', 'Archive not found.');
    redirect('archives.php');
}

define('PAGE_TITLE', $archive['name']);

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Tab selection
$tab = $_GET['tab'] ?? 'players';

// Get data based on tab
if ($tab === 'bids') {
    $bids = getArchiveBids($archiveId, $perPage, $offset);
    $totalItems = $archive['bid_count'];
} else {
    $players = getArchivePlayers($archiveId, $perPage, $offset);
    $totalItems = $archive['player_count'];
}

$totalPages = ceil($totalItems / $perPage);

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>
        <a href="archives.php" style="text-decoration: none; color: inherit;">← Archives</a>
    </h2>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h2 style="margin: 0;">📦 <?php echo h($archive['name']); ?></h2>
    </div>
    <div class="card-body">
        <?php if ($archive['description']): ?>
            <p style="margin-bottom: 15px;"><?php echo h($archive['description']); ?></p>
        <?php endif; ?>
        
        <div style="display: flex; gap: 30px; flex-wrap: wrap;">
            <div>
                <strong>Created:</strong> <?php echo formatDateTime($archive['created_at']); ?>
            </div>
            <div>
                <strong>Players:</strong> <?php echo number_format($archive['player_count']); ?>
            </div>
            <div>
                <strong>Bids:</strong> <?php echo number_format($archive['bid_count']); ?>
            </div>
            <?php if ($archive['creator_name']): ?>
                <div>
                    <strong>Created By:</strong> <?php echo h($archive['creator_name']); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--gray-200);">
        <div style="display: flex; gap: 20px;">
            <a href="?id=<?php echo $archiveId; ?>&tab=players" 
               class="<?php echo $tab === 'players' ? 'active' : ''; ?>"
               style="padding: 10px 0; border-bottom: 2px solid <?php echo $tab === 'players' ? 'var(--primary)' : 'transparent'; ?>; text-decoration: none; color: <?php echo $tab === 'players' ? 'var(--primary)' : 'var(--gray-600)'; ?>; font-weight: <?php echo $tab === 'players' ? '600' : '400'; ?>;">
                Players (<?php echo number_format($archive['player_count']); ?>)
            </a>
            <a href="?id=<?php echo $archiveId; ?>&tab=bids" 
               class="<?php echo $tab === 'bids' ? 'active' : ''; ?>"
               style="padding: 10px 0; border-bottom: 2px solid <?php echo $tab === 'bids' ? 'var(--primary)' : 'transparent'; ?>; text-decoration: none; color: <?php echo $tab === 'bids' ? 'var(--primary)' : 'var(--gray-600)'; ?>; font-weight: <?php echo $tab === 'bids' ? '600' : '400'; ?>;">
                Bids (<?php echo number_format($archive['bid_count']); ?>)
            </a>
        </div>
    </div>

    <?php if ($tab === 'players'): ?>
        <!-- Players Tab -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Bids</th>
                        <th>Final Bid</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($players)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No players in this archive</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($players as $player): ?>
                            <tr>
                                <td><?php echo $player['player_number']; ?></td>
                                <td>
                                    <strong><?php echo h($player['first_name'] . ' ' . $player['last_name']); ?></strong>
                                    <?php if ($player['nickname']): ?>
                                        <span class="text-muted">"<?php echo h($player['nickname']); ?>"</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?php echo getPositionAbbr($player['position']); ?>
                                    </span>
                                    <?php echo h(getPositionName($player['position'])); ?>
                                </td>
                                <td>
                                    <?php if ($player['bid_count'] > 0): ?>
                                        <span class="badge badge-primary"><?php echo $player['bid_count']; ?> bids</span>
                                    <?php else: ?>
                                        <span class="text-muted">No bids</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($player['highest_bid']): ?>
                                        <strong><?php echo formatMoney($player['highest_bid']); ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <!-- Bids Tab -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Player</th>
                        <th>Team</th>
                        <th>$/Year</th>
                        <th>Years</th>
                        <th>Total</th>
                        <th>Member</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bids)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted">No bids in this archive</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bids as $bid): ?>
                            <tr>
                                <td>
                                    <strong><?php echo h($bid['first_name'] . ' ' . $bid['last_name']); ?></strong>
                                </td>
                                <td><?php echo getTeamDisplayHtml($bid['team_name']); ?></td>
                                <td><?php echo formatMoney($bid['amount_per_year']); ?></td>
                                <td><?php echo $bid['years']; ?></td>
                                <td><strong><?php echo formatMoney($bid['total_value']); ?></strong></td>
                                <td><?php echo h($bid['member_name']); ?></td>
                                <td><?php echo formatDateTime($bid['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?id=<?php echo $archiveId; ?>&tab=<?php echo $tab; ?>&page=<?php echo $page - 1; ?>">
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
                <a href="?id=<?php echo $archiveId; ?>&tab=<?php echo $tab; ?>&page=<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?id=<?php echo $archiveId; ?>&tab=<?php echo $tab; ?>&page=<?php echo $page + 1; ?>">
                Next &raquo;
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
