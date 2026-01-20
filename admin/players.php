<?php
/**
 * Admin Players Management & CSV Import
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Players');

$pdo = getDBConnection();
$error = '';
$success = '';
$importResults = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'import':
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Please select a valid CSV file to upload.';
                } else {
                    $file = $_FILES['csv_file']['tmp_name'];
                    $clearExisting = isset($_POST['clear_existing']);

                    $importResults = importPlayersFromCSV($file, $pdo, $clearExisting);

                    if ($importResults['success']) {
                        $success = "Import completed: {$importResults['imported']} players imported.";
                        if ($importResults['skipped'] > 0) {
                            $success .= " {$importResults['skipped']} skipped (duplicates or invalid).";
                        }
                    } else {
                        $error = 'Import failed: ' . $importResults['message'];
                    }
                }
                break;

            case 'delete_player':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    // Delete associated bids first
                    $stmt = $pdo->prepare("DELETE FROM bids WHERE player_id = ?");
                    $stmt->execute([$id]);

                    $stmt = $pdo->prepare("DELETE FROM players WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = 'Player deleted successfully.';
                }
                break;

            case 'clear_all':
                // Delete bids only for active players
                $pdo->exec("
                    DELETE b FROM bids b 
                    INNER JOIN players p ON b.player_id = p.id 
                    WHERE p.archive_id IS NULL
                ");
                
                // Delete only active players
                $pdo->exec("DELETE FROM players WHERE archive_id IS NULL");
                $success = 'All players and bids have been cleared.';
                break;

            case 'create_archive':
                $archiveName = trim($_POST['archive_name'] ?? '');
                $archiveDescription = trim($_POST['archive_description'] ?? '');
                
                if (empty($archiveName)) {
                    $error = 'Archive name is required.';
                } else {
                    $result = createArchive($archiveName, $archiveDescription, getCurrentUserId());
                    if ($result['success']) {
                        setFlashMessage('success', "Archive '{$archiveName}' created with {$result['player_count']} players and {$result['bid_count']} bids!");
                        redirect('players.php');
                    } else {
                        $error = 'Failed to create archive: ' . $result['message'];
                    }
                }
                break;
        }
    }
}

/**
 * Import players from CSV file
 */
function importPlayersFromCSV($filePath, $pdo, $clearExisting = false) {
    $result = [
        'success' => false,
        'imported' => 0,
        'skipped' => 0,
        'message' => ''
    ];

    if (!file_exists($filePath)) {
        $result['message'] = 'File not found.';
        return $result;
    }

    if ($clearExisting) {
        $pdo->exec("DELETE FROM bids");
        $pdo->exec("DELETE FROM players");
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        $result['message'] = 'Could not open file.';
        return $result;
    }

    $stmt = $pdo->prepare("
        INSERT INTO players (player_number, first_name, last_name, nickname, position, day_of_birth, month_of_birth, year_of_birth)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            first_name = VALUES(first_name),
            last_name = VALUES(last_name),
            nickname = VALUES(nickname),
            position = VALUES(position),
            day_of_birth = VALUES(day_of_birth),
            month_of_birth = VALUES(month_of_birth),
            year_of_birth = VALUES(year_of_birth)
    ");

    $lineNumber = 0;
    while (($row = fgetcsv($handle)) !== false) {
        $lineNumber++;

        // Skip empty rows
        if (empty($row) || count($row) < 22) {
            continue;
        }

        // Skip comment lines (start with //)
        if (isset($row[0]) && strpos(trim($row[0]), '//') === 0) {
            continue;
        }

        // Extract data from CSV columns
        // Column 0: id (player_number)
        // Column 5: LastName
        // Column 6: FirstName
        // Column 7: NickName
        // Column 9: DayOB
        // Column 10: MonthOB
        // Column 11: YearOB
        // Column 21: Position

        $playerNumber = trim($row[0]);
        $lastName = trim($row[5] ?? '');
        $firstName = trim($row[6] ?? '');
        $nickname = trim($row[7] ?? '');
        $dayOB = !empty($row[9]) && is_numeric($row[9]) ? (int)$row[9] : null;
        $monthOB = !empty($row[10]) && is_numeric($row[10]) ? (int)$row[10] : null;
        $yearOB = !empty($row[11]) && is_numeric($row[11]) ? (int)$row[11] : null;
        $position = (int)($row[21] ?? 0);

        // Validate required fields
        if (empty($playerNumber) || !is_numeric($playerNumber)) {
            $result['skipped']++;
            continue;
        }

        if (empty($lastName) || empty($firstName)) {
            $result['skipped']++;
            continue;
        }

        // Valid positions: 2-9, 11-13
        if (!in_array($position, [2, 3, 4, 5, 6, 7, 8, 9, 11, 12, 13])) {
            $result['skipped']++;
            continue;
        }

        try {
            $stmt->execute([
                (int)$playerNumber,
                $firstName,
                $lastName,
                $nickname ?: null,
                $position,
                $dayOB,
                $monthOB,
                $yearOB
            ]);
            $result['imported']++;
        } catch (PDOException $e) {
            $result['skipped']++;
        }
    }

    fclose($handle);

    $result['success'] = true;
    return $result;
}

// Pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Search/Filter
$search = trim($_GET['search'] ?? '');
$filterPosition = $_GET['position'] ?? '';

// Build query
$where = [];
$params = [];

if ($search) {
    $where[] = "(first_name LIKE ? OR last_name LIKE ? OR nickname LIKE ?)";
    $searchTerm = "%{$search}%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
}

if ($filterPosition !== '') {
    $where[] = "position = ?";
    $params[] = (int)$filterPosition;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM players WHERE archive_id IS NULL " . ($whereClause ? " AND " . substr($whereClause, 6) : ""));
$stmt->execute($params);
$totalPlayers = $stmt->fetch()['count'];
$totalPages = ceil($totalPlayers / $perPage);

// Get players
$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT COUNT(*) FROM bids b WHERE b.player_id = p.id) as bid_count
    FROM players p
    WHERE p.archive_id IS NULL
    " . ($whereClause ? " AND " . substr($whereClause, 6) : "") . "
    ORDER BY p.last_name, p.first_name
    LIMIT {$perPage} OFFSET {$offset}
");
$stmt->execute($params);
$players = $stmt->fetchAll();

$flash = getFlashMessage();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>Player Management</h2>
    <div>
        <span class="text-muted"><?php echo number_format($totalPlayers); ?> players</span>
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

<!-- CSV Import -->
<div class="card mb-3">
    <div class="card-header">
        <h3>Import Players from CSV</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="action" value="import">

            <div class="form-group mb-3">
                <div class="upload-zone" id="uploadZone">
                    <span class="upload-icon">&#8682;</span>
                    <div class="upload-text">Click or drag OOTP CSV file here</div>
                    <div class="upload-hint">Supported format: .csv</div>
                    <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                    <div class="file-name-display" id="fileName">No file selected</div>
                </div>
            </div>

            <div class="d-flex justify-between align-center">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="clear_existing" value="1">
                    Clear existing players before import
                </label>
                <button type="submit" class="btn btn-primary">Import Players</button>
            </div>
        </form>

        <?php if ($totalPlayers > 0): ?>
            <div class="mt-3 pt-3 border-top" style="display: flex; gap: 10px;">
                <form id="clearAllForm" method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="clear_all">
                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmClearAll()">Clear All Players & Bids</button>
                </form>
                <button type="button" class="btn btn-secondary btn-sm" onclick="showArchiveModal()">
                    📦 Archive Current Class
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const zone = document.getElementById('uploadZone');
    const input = document.getElementById('csv_file');
    const fileNameDisplay = document.getElementById('fileName');

    if (zone && input) {
        // Highlight zone on drag
        ['dragenter', 'dragover'].forEach(eventName => {
            zone.addEventListener(eventName, e => {
                e.preventDefault();
                zone.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            zone.addEventListener(eventName, e => {
                e.preventDefault();
                zone.classList.remove('dragover');
            }, false);
        });

        // Update file name on change
        input.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                fileNameDisplay.textContent = 'Selected: ' + this.files[0].name;
            } else {
                fileNameDisplay.textContent = 'No file selected';
            }
        });

        // Handle drop (if not using native hidden input behavior)
        zone.addEventListener('drop', e => {
            const dt = e.dataTransfer;
            const files = dt.files;
            input.files = files; // Standard browser behavior
            // Trigger change manually to update text
            const event = new Event('change', { bubbles: true });
            input.dispatchEvent(event);
        });
    }
});
</script>

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
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if ($search || $filterPosition): ?>
                <a href="players.php" class="btn btn-secondary">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Players List -->
<div class="card">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Bids</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($players)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No players found</td>
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
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;" data-confirm="Delete this player and all their bids?">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="action" value="delete_player">
                                    <input type="hidden" name="id" value="<?php echo $player['id']; ?>">
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
            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo $filterPosition; ?>">
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
                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo $filterPosition; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&position=<?php echo $filterPosition; ?>">
                Next &raquo;
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- Archive Modal -->
<div id="archiveModal" class="modal-backdrop">
    <div class="modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Archive Current Free Agent Class</h3>
        </div>
        <div class="modal-body">
            <div style="background: var(--gray-50); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>Current Statistics:</strong><br>
                <?php echo number_format($totalPlayers); ?> players •
                <?php
                $bidStmt = $pdo->query("SELECT COUNT(*) as count FROM bids b JOIN players p ON b.player_id = p.id WHERE p.archive_id IS NULL");
                $bidCount = $bidStmt->fetch()['count'];
                echo number_format($bidCount);
                ?> bids
            </div>

            <form id="archiveForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_archive">

                <div class="form-group">
                    <label for="archive_name">Archive Name *</label>
                    <input type="text" id="archive_name" name="archive_name" class="form-control"
                           placeholder="e.g., 2024 Season FA Class" required>
                </div>

                <div class="form-group">
                    <label for="archive_description">Description (Optional)</label>
                    <textarea id="archive_description" name="archive_description" class="form-control"
                              rows="3" placeholder="Add notes about this free agent class..."></textarea>
                </div>

                <div style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a; padding: 12px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #f59e0b;">
                    <strong>⚠️ Warning:</strong> This will move all current players and their bids to the archive.
                    This action cannot be undone, but archived data will be preserved and viewable.
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="hideArchiveModal()">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="confirmArchive()">Create Archive</button>
        </div>
    </div>
</div>

<script>
function showArchiveModal() {
    const modal = document.getElementById('archiveModal');
    modal.classList.add('active');
    document.getElementById('archive_name').focus();
}

function hideArchiveModal() {
    document.getElementById('archiveModal').classList.remove('active');
}

async function confirmArchive() {
    const name = document.getElementById('archive_name').value.trim();
    if (!name) {
        alert('Please enter an archive name.');
        return;
    }
    
    const confirmed = await showConfirm(
        'Are you sure you want to archive all current players? This action cannot be undone.',
        'Confirm Archive'
    );
    
    if (confirmed) {
        document.getElementById('archiveForm').submit();
    }
}

async function confirmClearAll() {
    const confirmed = await showConfirm(
        'This will delete ALL active players and their bids. Are you sure you want to proceed?',
        'Clear All Players'
    );
    
    if (confirmed) {
        document.getElementById('clearAllForm').submit();
    }
}

// Close archive modal on background click
document.getElementById('archiveModal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideArchiveModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
