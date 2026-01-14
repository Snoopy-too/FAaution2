<?php
/**
 * Player Detail & Bidding Page
 */

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDBConnection();
$teamId = getCurrentTeamId();
$memberId = getCurrentUserId();

// Get player ID
$playerId = (int)($_GET['id'] ?? 0);
if ($playerId <= 0) {
    setFlashMessage('error', 'Invalid player.');
    redirect('free-agents.php');
}

// Get player
$stmt = $pdo->prepare("SELECT * FROM players WHERE id = ? AND archive_id IS NULL");
$stmt->execute([$playerId]);
$player = $stmt->fetch();

if (!$player) {
    setFlashMessage('error', 'Player not found.');
    redirect('free-agents.php');
}

define('PAGE_TITLE', $player['first_name'] . ' ' . $player['last_name']);

$error = '';
$success = '';

// Get settings
$minIncrementPercent = (float)getSetting('min_bid_increment_percent', 5);
$maxYears = (int)getSetting('max_contract_years', 5);
$maxBidsPerPlayer = (int)getSetting('max_bids_per_player', 3);
$auctionClosed = isAuctionClosed();

// Get current highest bid
$highestBid = getHighestBid($playerId);

// Get all bids for this player
$allBids = getPlayerBids($playerId);

// Count this team's non-opening bids on this player
$teamBidCount = countTeamBidsOnPlayer($teamId, $playerId);

// Check if this team has the opening bid
$hasOpeningBid = false;
foreach ($allBids as $bid) {
    if ($bid['team_id'] == $teamId && $bid['is_opening_bid']) {
        $hasOpeningBid = true;
        break;
    }
}

// Available money
$availableMoney = getAvailableMoney($teamId);

// Handle bid submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auctionClosed) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $amountPerYear = (float)str_replace([',', '$'], '', $_POST['amount_per_year'] ?? '0');
        $years = (int)($_POST['years'] ?? 0);

        if ($amountPerYear <= 0) {
            $error = 'Please enter a valid amount per year.';
        } elseif ($years < 1 || $years > $maxYears) {
            $error = "Contract length must be between 1 and {$maxYears} years.";
        } else {
            $totalValue = $amountPerYear * $years;

            // Determine if this is an opening bid
            $isOpeningBid = empty($allBids);

            // Check bid count limit (opening bid doesn't count)
            if (!$isOpeningBid && $teamBidCount >= $maxBidsPerPlayer) {
                $error = "You have reached the maximum of {$maxBidsPerPlayer} bids on this player.";
            }
            // Check minimum increment
            elseif ($highestBid && !$isOpeningBid) {
                $minRequired = $highestBid['total_value'] * (1 + $minIncrementPercent / 100);
                if ($totalValue < $minRequired) {
                    $error = "Your bid must be at least " . formatMoney($minRequired) .
                             " (current highest + {$minIncrementPercent}%).";
                }
            }

            // Check available budget
            if (!$error) {
                // Calculation is based on the upcoming season's budget (yearly amount)
                $moneyNeeded = $amountPerYear;
                if ($highestBid && $highestBid['team_id'] == $teamId) {
                    // We already have money committed (yearly amount), so we only need the difference
                    $moneyNeeded = $amountPerYear - $highestBid['amount_per_year'];
                }

                if ($moneyNeeded > $availableMoney) {
                    $error = "Insufficient budget. You need " . formatMoney($moneyNeeded) .
                             " (yearly contribution) but only have " . formatMoney($availableMoney) . " available.";
                }
            }

            // Place the bid
            if (!$error) {
                $stmt = $pdo->prepare("
                    INSERT INTO bids (player_id, team_id, member_id, amount_per_year, years, total_value, is_opening_bid)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                try {
                    $stmt->execute([
                        $playerId,
                        $teamId,
                        $memberId,
                        $amountPerYear,
                        $years,
                        $totalValue,
                        $isOpeningBid ? 1 : 0
                    ]);

                    setFlashMessage('success', 'Bid placed successfully! ' . formatContract($amountPerYear, $years));
                    redirect("player.php?id={$playerId}");
                } catch (PDOException $e) {
                    $error = 'Failed to place bid: ' . $e->getMessage();
                }
            }
        }
    }
}

// Refresh data after potential bid
$highestBid = getHighestBid($playerId);
$allBids = getPlayerBids($playerId);
$teamBidCount = countTeamBidsOnPlayer($teamId, $playerId);
$availableMoney = getAvailableMoney($teamId);

// Calculate minimum bid for display
$minBidRequired = 0;
if ($highestBid) {
    $minBidRequired = ceil($highestBid['total_value'] * (1 + $minIncrementPercent / 100));
}

// Check if we can bid
$isLeading = ($highestBid && $highestBid['team_id'] == $teamId);
$canBid = !$auctionClosed && !$isLeading && (empty($allBids) || $teamBidCount < $maxBidsPerPlayer);
$bidsRemaining = $maxBidsPerPlayer - $teamBidCount;

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2><a href="free-agents.php" style="text-decoration: none; color: inherit;">&larr;</a> Player Details</h2>
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

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($auctionClosed): ?>
    <div class="alert alert-warning">
        The auction is currently <strong>closed</strong>. No new bids can be placed.
    </div>
<?php endif; ?>

<!-- Player Info -->
<div class="player-detail-header">
    <div class="player-detail-image">
        <img src="<?php echo h(getPlayerImagePath($player['player_number'])); ?>"
             alt="<?php echo h($player['first_name'] . ' ' . $player['last_name']); ?>"
             onerror="this.style.display='none'">
    </div>
    <div class="player-detail-info">
        <h1><?php echo h($player['first_name'] . ' ' . $player['last_name']); ?></h1>
        <?php if ($player['nickname']): ?>
            <p class="nickname">"<?php echo h($player['nickname']); ?>"</p>
        <?php endif; ?>
        <?php
        $playerAge = calculatePlayerAge(
            $player['day_of_birth'] ?? null,
            $player['month_of_birth'] ?? null,
            $player['year_of_birth'] ?? null
        );
        if ($playerAge !== null): ?>
            <p class="player-age" style="font-size: 18px; color: var(--gray-600); margin: 4px 0;">
                Age: <strong><?php echo $playerAge; ?></strong>
            </p>
        <?php endif; ?>
        <p class="position">
            <span class="badge badge-secondary" style="font-size: 16px;">
                <?php echo getPositionAbbr($player['position']); ?>
            </span>
            <?php echo h(getPositionName($player['position'])); ?>
        </p>
        <div class="mt-3">
            <a href="<?php echo getPlayerHtmlUrl($player['player_number']); ?>"
               target="_blank" class="btn btn-outline btn-sm">
               View OOTP Player Page &rarr;
            </a>
        </div>
    </div>
</div>

<!-- <?php echo $auctionClosed ? 'Winning Bid' : 'Current Highest Bid'; ?> -->
<?php if ($highestBid): ?>
    <div class="current-bid-box">
        <h3><?php echo $auctionClosed ? 'Winning Bid' : 'Current Highest Bid'; ?></h3>
        <div class="bid-amount"><?php echo formatMoney($highestBid['total_value']); ?></div>
        <div class="bid-details">
            <?php echo formatMoney($highestBid['amount_per_year']); ?>/year for <?php echo $highestBid['years']; ?> year<?php echo $highestBid['years'] > 1 ? 's' : ''; ?>
            by <strong><?php echo h($highestBid['team_name']); ?></strong>
            <?php if ($highestBid['team_id'] == $teamId): ?>
                <span class="badge badge-success" style="margin-left: 8px;">Your bid!</span>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="current-bid-box" style="background: linear-gradient(135deg, #22c55e, #16a34a);">
        <h3>No Bids Yet</h3>
        <div class="bid-amount">Be the first to bid!</div>
        <div class="bid-details">Place an opening bid to start the action</div>
    </div>
<?php endif; ?>

<!-- Bid Form -->
<?php if ($canBid): ?>
    <div class="bid-form-container mb-3">
        <h3 style="margin-bottom: 16px;">Place a Bid</h3>

        <?php if ($highestBid): ?>
            <p class="text-muted mb-2">
                Minimum bid required: <strong><?php echo formatMoney($minBidRequired); ?></strong> total
                (<?php echo $minIncrementPercent; ?>% above current highest)
            </p>
        <?php endif; ?>

        <p class="text-muted mb-2">
            Bids remaining on this player: <strong><?php echo $bidsRemaining; ?></strong>
            <?php if ($hasOpeningBid): ?>
                (opening bid doesn't count)
            <?php endif; ?>
        </p>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

            <div class="bid-form-row">
                <div class="form-group">
                    <label for="amount_per_year">Amount Per Year ($)</label>
                    <input type="number" id="amount_per_year" name="amount_per_year" class="form-control"
                           min="0" step="100000" required
                           placeholder="e.g., 10000000">
                </div>
                <div class="form-group">
                    <label for="years">Contract Years</label>
                    <select id="years" name="years" class="form-control" required>
                        <?php for ($y = 1; $y <= $maxYears; $y++): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?> year<?php echo $y > 1 ? 's' : ''; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group flex-buttons" style="display: flex; gap: 10px; align-items: flex-end;">
                    <button type="submit" class="btn btn-success btn-lg" style="flex: 1;">Submit Bid</button>
                    <a href="free-agents.php" class="btn btn-outline btn-lg" style="flex: 1; text-align: center;">Cancel</a>
                </div>
            </div>

            <div id="bid-preview" class="mt-2" style="display: none;">
                <p>Total contract value: <strong id="total-preview">$0</strong></p>
            </div>
        </form>
    </div>
<?php elseif ($isLeading): ?>
    <div class="alert alert-success">
        <strong>You currently have the leading bid!</strong> You cannot bid against yourself.
    </div>
<?php elseif (!$auctionClosed): ?>
    <div class="alert alert-info">
        You have used all <?php echo $maxBidsPerPlayer; ?> of your bids on this player.
    </div>
<?php endif; ?>

<!-- Bid History -->
<div class="card">
    <div class="card-header">
        <h3>Bid History (<?php echo count($allBids); ?>)</h3>
    </div>
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Team</th>
                    <th>$/Year</th>
                    <th>Years</th>
                    <th>Total Value</th>
                    <th>Type</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($allBids)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No bids yet</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allBids as $index => $bid): ?>
                        <tr style="<?php echo $index === 0 ? 'background: #f0fdf4;' : ''; ?>">
                            <td>
                                <strong><?php echo getTeamDisplayHtml($bid['team_name']); ?></strong>
                                <?php if ($bid['team_id'] == $teamId): ?>
                                    <span class="badge badge-primary">You</span>
                                <?php endif; ?>
                                <?php if ($index === 0): ?>
                                    <span class="badge badge-success">Leading</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatMoney($bid['amount_per_year']); ?></td>
                            <td><?php echo $bid['years']; ?></td>
                            <td><strong><?php echo formatMoney($bid['total_value']); ?></strong></td>
                            <td>
                                <?php if ($bid['is_opening_bid']): ?>
                                    <span class="badge badge-secondary">Opening</span>
                                <?php else: ?>
                                    <span class="text-muted">Regular</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDateTime($bid['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// Real-time bid preview
document.addEventListener('DOMContentLoaded', function() {
    const amountInput = document.getElementById('amount_per_year');
    const yearsSelect = document.getElementById('years');
    const preview = document.getElementById('bid-preview');
    const totalPreview = document.getElementById('total-preview');

    function updatePreview() {
        const amount = parseFloat(amountInput.value) || 0;
        const years = parseInt(yearsSelect.value) || 1;
        const total = amount * years;

        if (amount > 0) {
            preview.style.display = 'block';
            totalPreview.textContent = '$' + total.toLocaleString();
        } else {
            preview.style.display = 'none';
        }
    }

    if (amountInput && yearsSelect) {
        amountInput.addEventListener('input', updatePreview);
        yearsSelect.addEventListener('change', updatePreview);
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
