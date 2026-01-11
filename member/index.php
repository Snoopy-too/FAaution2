<?php
/**
 * Member Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';
requireMember();

define('PAGE_TITLE', 'Dashboard');

$pdo = getDBConnection();
$teamId = getCurrentTeamId();

// Get available money
$availableMoney = getAvailableMoney($teamId);

// Get team's original budget
$stmt = $pdo->prepare("SELECT available_money FROM teams WHERE id = ?");
$stmt->execute([$teamId]);
$team = $stmt->fetch();
$originalBudget = $team ? (float)$team['available_money'] : 0;

// Get all players where this team has placed a bid
$stmt = $pdo->prepare("
    SELECT DISTINCT p.id, p.player_number, p.first_name, p.last_name, p.nickname, p.position,
           (SELECT b.id FROM bids b WHERE b.player_id = p.id ORDER BY b.total_value DESC, b.created_at ASC LIMIT 1) as leading_bid_id,
           (SELECT b.team_id FROM bids b WHERE b.player_id = p.id ORDER BY b.total_value DESC, b.created_at ASC LIMIT 1) as leading_team_id,
           (SELECT b.total_value FROM bids b WHERE b.player_id = p.id ORDER BY b.total_value DESC, b.created_at ASC LIMIT 1) as leading_bid_amount,
           (SELECT MAX(b2.total_value) FROM bids b2 WHERE b2.player_id = p.id AND b2.team_id = ?) as our_best_bid
    FROM players p
    JOIN bids b ON b.player_id = p.id AND b.team_id = ?
    WHERE p.archive_id IS NULL
    ORDER BY p.last_name, p.first_name
");
$stmt->execute([$teamId, $teamId]);
$myBidPlayers = $stmt->fetchAll();

// Separate leading and outbid
$leadingBids = [];
$outbidPlayers = [];
foreach ($myBidPlayers as $player) {
    if ($player['leading_team_id'] == $teamId) {
        $leadingBids[] = $player;
    } else {
        $outbidPlayers[] = $player;
    }
}

// Check auction status
$auctionClosed = isAuctionClosed();

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>My Dashboard</h2>
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
        <span class="money-available"><?php echo formatMoney($availableMoney); ?> available</span>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if ($auctionClosed): ?>
    <div class="alert alert-warning">
        The auction is currently <strong>closed</strong>. No new bids can be placed.
    </div>
<?php endif; ?>

<!-- Budget Summary -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-label">Total Budget</div>
        <div class="stat-value"><?php echo formatMoney($originalBudget); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Committed (Leading Bids - This Season)</div>
        <div class="stat-value text-primary"><?php echo formatMoney($originalBudget - $availableMoney); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Available</div>
        <div class="stat-value text-success"><?php echo formatMoney($availableMoney); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Active Bids</div>
        <div class="stat-value"><?php echo count($myBidPlayers); ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-3">
    <div class="card-body">
        <a href="free-agents.php" class="btn btn-primary btn-lg">View Free Agents</a>
    </div>
</div>

<!-- Leading Bids -->
<?php if (!empty($leadingBids)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3>Your Leading Bids (<?php echo count($leadingBids); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="player-cards-grid">
            <?php foreach ($leadingBids as $player): ?>
                <a href="player.php?id=<?php echo $player['id']; ?>" class="player-card">
                    <div class="player-card-image">
                        <img src="<?php echo h(getPlayerImagePath($player['player_number'])); ?>"
                             alt="<?php echo h($player['first_name'] . ' ' . $player['last_name']); ?>"
                             onerror="this.style.display='none'">
                        <span class="position-badge"><?php echo getPositionAbbr($player['position']); ?></span>
                    </div>
                    <div class="player-card-body">
                        <h4><?php echo h($player['first_name'] . ' ' . $player['last_name']); ?></h4>
                        <?php if ($player['nickname']): ?>
                            <p class="text-muted text-small">"<?php echo h($player['nickname']); ?>"</p>
                        <?php endif; ?>
                        <div class="player-card-bid">
                            <span class="bid-amount"><?php echo formatMoney($player['our_best_bid']); ?></span>
                            <span class="bid-status leading" title="Leading bid">&#10003;</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Outbid Players -->
<?php if (!empty($outbidPlayers)): ?>
<div class="card mb-3">
    <div class="card-header">
        <h3>Outbid (<?php echo count($outbidPlayers); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="player-cards-grid">
            <?php foreach ($outbidPlayers as $player): ?>
                <a href="player.php?id=<?php echo $player['id']; ?>" class="player-card">
                    <div class="player-card-image">
                        <img src="<?php echo h(getPlayerImagePath($player['player_number'])); ?>"
                             alt="<?php echo h($player['first_name'] . ' ' . $player['last_name']); ?>"
                             onerror="this.style.display='none'">
                        <span class="position-badge"><?php echo getPositionAbbr($player['position']); ?></span>
                    </div>
                    <div class="player-card-body">
                        <h4><?php echo h($player['first_name'] . ' ' . $player['last_name']); ?></h4>
                        <?php if ($player['nickname']): ?>
                            <p class="text-muted text-small">"<?php echo h($player['nickname']); ?>"</p>
                        <?php endif; ?>
                        <div class="player-card-bid">
                            <span class="bid-amount">
                                Your: <?php echo formatMoney($player['our_best_bid']); ?>
                                <br>
                                <span class="text-danger">Top: <?php echo formatMoney($player['leading_bid_amount']); ?></span>
                            </span>
                            <span class="bid-status outbid" title="Outbid">&#10007;</span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($myBidPlayers)): ?>
<div class="card">
    <div class="card-body text-center">
        <p class="text-muted">You haven't placed any bids yet.</p>
        <a href="free-agents.php" class="btn btn-primary">Browse Free Agents</a>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
