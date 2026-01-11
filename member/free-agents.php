<?php
/**
 * Free Agents List
 */

require_once __DIR__ . '/../includes/auth.php';
requireMember();

define('PAGE_TITLE', 'Free Agents');

$pdo = getDBConnection();
$teamId = getCurrentTeamId();
$auctionClosed = isAuctionClosed();

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Search/Filter/Sort
$search = trim($_GET['search'] ?? '');
$filterPosition = $_GET['position'] ?? '';
$sort = $_GET['sort'] ?? 'name';
$sortDir = ($_GET['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.nickname LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($filterPosition !== '') {
    $where[] = "p.position = ?";
    $params[] = (int)$filterPosition;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Determine sort column
switch ($sort) {
    case 'position':
        $orderBy = "p.position {$sortDir}, p.last_name ASC";
        break;
    case 'bid':
        $orderBy = "highest_bid {$sortDir} NULLS LAST, p.last_name ASC";
        break;
    default:
        $orderBy = "p.last_name {$sortDir}, p.first_name {$sortDir}";
}

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM players p WHERE p.archive_id IS NULL " . ($whereClause ? " AND " . substr($whereClause, 6) : ""));
$stmt->execute($params);
$totalPlayers = $stmt->fetch()['count'];
$totalPages = ceil($totalPlayers / $perPage);

// Get players with bid info
$sql = "
    SELECT p.*,
           hb.id as highest_bid_id,
           hb.total_value as highest_bid,
           hb.amount_per_year as highest_bid_per_year,
           hb.years as highest_bid_years,
           hb.created_at as highest_bid_date,
           ht.id as highest_team_id,
           ht.name as highest_team_name
    FROM players p
    LEFT JOIN (
        SELECT b1.*
        FROM bids b1
        INNER JOIN (
            SELECT player_id, MAX(total_value) as max_total
            FROM bids
            GROUP BY player_id
        ) b2 ON b1.player_id = b2.player_id AND b1.total_value = b2.max_total
    ) hb ON p.id = hb.player_id
    LEFT JOIN teams ht ON hb.team_id = ht.id
    WHERE p.archive_id IS NULL
    " . ($whereClause ? " AND " . substr($whereClause, 6) : "") . "
    ORDER BY {$orderBy}
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$players = $stmt->fetchAll();

// Helper for sort links
function sortLink($column, $label, $currentSort, $currentDir) {
    $newDir = ($currentSort === $column && $currentDir === 'ASC') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $column) {
        $arrow = $currentDir === 'ASC' ? ' &#9650;' : ' &#9660;';
    }
    $params = $_GET;
    $params['sort'] = $column;
    $params['dir'] = $newDir;
    return '<a href="?' . http_build_query($params) . '">' . $label . $arrow . '</a>';
}

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Free Agents</h2>
    <div class="user-info">
        <?php 
        $currentTeamName = getCurrentTeamName();
        $logoUrl = getTeamLogoUrl($currentTeamName);
        if ($logoUrl) {
            echo '<img src="' . h($logoUrl) . '" alt="' . h($currentTeamName) . '" title="' . h($currentTeamName) . '" style="height: 40px; vertical-align: middle;">';
        } else {
            echo '<span class="team-badge">' . h($currentTeamName) . '</span>';
        }
        ?>
        <span class="money-available"><?php echo formatMoney(getAvailableMoney($teamId)); ?> available</span>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<!-- Search/Filter -->
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="filter-bar">
            <input type="text" name="search" class="form-control" placeholder="Search players..."
                   value="<?php echo h($search); ?>">
            <select name="position" class="form-control">
                <option value="">All Positions</option>
                <?php foreach (getAllPositions() as $code => $name): ?>
                    <option value="<?php echo $code; ?>" <?php echo $filterPosition == $code ? 'selected' : ''; ?>>
                        <?php echo h($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="sort" class="form-control show-mobile">
                <option value="name" <?php echo $sort == 'name' ? 'selected' : ''; ?>>Sort by Player</option>
                <option value="position" <?php echo $sort == 'position' ? 'selected' : ''; ?>>Sort by Position</option>
                <option value="bid" <?php echo $sort == 'bid' ? 'selected' : ''; ?>>Sort by Highest Bid</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search || $filterPosition || $sort !== 'name'): ?>
                <a href="free-agents.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Players List -->
<div class="card">
    <div class="card-header">
        <h3><?php echo number_format($totalPlayers); ?> Free Agents</h3>
    </div>
    <div class="table-container hide-mobile">
        <table>
            <thead>
                <tr>
                    <th><?php echo sortLink('name', 'Player', $sort, $sortDir === 'DESC' ? 'DESC' : 'ASC'); ?></th>
                    <th><?php echo sortLink('position', 'Position', $sort, $sortDir === 'DESC' ? 'DESC' : 'ASC'); ?></th>
                    <th><?php echo sortLink('bid', ($auctionClosed ? 'Winning Bid' : 'Current Highest Bid'), $sort, $sortDir === 'DESC' ? 'DESC' : 'ASC'); ?></th>
                    <th>Leading Team</th>
                    <th>Bid Date</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($players)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No players found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($players as $player): ?>
                        <tr>
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
                                <?php if ($player['highest_bid']): ?>
                                    <strong><?php echo formatMoney($player['highest_bid']); ?></strong>
                                    <span class="text-muted text-small">
                                        (<?php echo formatMoney($player['highest_bid_per_year']); ?>/yr x <?php echo $player['highest_bid_years']; ?>)
                                    </span>
                                    <?php if ($player['highest_team_id'] == $teamId): ?>
                                        <span class="badge badge-success">Your bid</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">No bids</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($player['highest_team_name']): ?>
                                    <?php echo getTeamDisplayHtml($player['highest_team_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($player['highest_bid_date']): ?>
                                    <?php echo formatDateTime($player['highest_bid_date']); ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="player.php?id=<?php echo $player['id']; ?>" class="btn btn-sm btn-primary">
                                    View / Bid
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Card List -->
    <div class="mobile-card-list show-mobile">
        <?php if (empty($players)): ?>
            <div class="text-center text-muted p-4">No players found</div>
        <?php else: ?>
            <?php foreach ($players as $player): ?>
                <a href="player.php?id=<?php echo $player['id']; ?>" class="mobile-card-item" style="display: block; text-decoration: none; color: inherit;">
                    <div class="d-flex justify-between align-center mb-2">
                        <div>
                            <h4 class="mb-0"><?php echo h($player['first_name'] . ' ' . $player['last_name']); ?></h4>
                            <?php if ($player['nickname']): ?>
                                <div class="text-small text-muted">"<?php echo h($player['nickname']); ?>"</div>
                            <?php endif; ?>
                        </div>
                        <span class="badge badge-secondary">
                            <?php echo getPositionAbbr($player['position']); ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="text-small text-muted mb-1"><?php echo $auctionClosed ? 'Winning Bid' : 'Current Highest Bid'; ?></div>
                        <?php if ($player['highest_bid']): ?>
                            <div class="d-flex align-center gap-2">
                                <strong class="text-primary"><?php echo formatMoney($player['highest_bid']); ?></strong>
                                <?php if ($player['highest_team_id'] == $teamId): ?>
                                    <span class="badge badge-success">Your bid</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-small text-muted">
                                <?php echo formatMoney($player['highest_bid_per_year']); ?>/yr x <?php echo $player['highest_bid_years']; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted">No bids</div>
                        <?php endif; ?>
                    </div>

                    <div class="meta mb-0">
                        <div style="flex: 1; min-width: 120px;">
                            <div class="text-small text-muted">Leading Team</div>
                            <div class="text-small"><?php echo getTeamDisplayHtml($player['highest_team_name']); ?></div>
                        </div>
                        <div style="flex: 1; min-width: 120px;">
                            <div class="text-small text-muted">Bid Date</div>
                            <div class="text-small"><?php echo $player['highest_bid_date'] ? formatDateTime($player['highest_bid_date']) : '-'; ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php
        $queryParams = $_GET;
        unset($queryParams['page']);
        $baseQuery = http_build_query($queryParams);
        ?>

        <?php if ($page > 1): ?>
            <a href="?<?php echo $baseQuery; ?>&page=<?php echo $page - 1; ?>">
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
                <a href="?<?php echo $baseQuery; ?>&page=<?php echo $i; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?<?php echo $baseQuery; ?>&page=<?php echo $page + 1; ?>">
                Next &raquo;
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
