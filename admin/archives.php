<?php
/**
 * Admin Archives List
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Archives');

$pdo = getDBConnection();
$archives = getAllArchives();

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Archive History</h2>
    <div>
        <span class="text-muted"><?php echo count($archives); ?> archive(s)</span>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']; ?>">
        <?php echo h($flash['message']); ?>
    </div>
<?php endif; ?>

<?php if (empty($archives)): ?>
    <div class="card">
        <div class="card-body text-center" style="padding: 60px 20px;">
            <div style="font-size: 48px; margin-bottom: 20px;">📦</div>
            <h3 style="margin-bottom: 10px;">No Archives Yet</h3>
            <p class="text-muted">
                When you archive a free agent class, it will appear here.<br>
                Go to <a href="players.php">Player Management</a> to create your first archive.
            </p>
        </div>
    </div>
<?php else: ?>
    <div style="display: grid; gap: 20px;">
        <?php foreach ($archives as $archive): ?>
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-between align-center mb-2">
                        <div>
                            <h3 style="margin: 0 0 8px 0;">
                                📦<?php echo h($archive['name']); ?>
                            </h3>
                            <?php if ($archive['description']): ?>
                                <p class="text-muted" style="margin-bottom: 10px;">
                                    <?php echo h($archive['description']); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 15px;">
                        <div style="color: var(--gray-600); font-size: 14px;">
                            <strong>Created:</strong> <?php echo formatDateTime($archive['created_at']); ?>
                        </div>
                        <div style="color: var(--gray-600); font-size: 14px;">
                            <strong><?php echo number_format($archive['player_count']); ?></strong> players
                        </div>
                        <div style="color: var(--gray-600); font-size: 14px;">
                            <strong><?php echo number_format($archive['bid_count']); ?></strong> bids
                        </div>
                        <?php if ($archive['creator_name']): ?>
                            <div style="color: var(--gray-600); font-size: 14px;">
                                <strong>By:</strong> <?php echo h($archive['creator_name']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="archive_view.php?id=<?php echo $archive['id']; ?>" class="btn btn-primary btn-sm">
                        View Details →
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
