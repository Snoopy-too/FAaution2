<?php
/**
 * Auto-Update System
 * Downloads and installs updates from GitHub
 */

require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

define('PAGE_TITLE', 'Update FA Auction');

$pdo = getDBConnection();
$updateInfo = checkForUpdates();

include __DIR__ . '/../includes/header.php';
?>

<div class="top-bar">
    <h2>
        <a href="index.php" style="text-decoration: none; color: inherit;">← Dashboard</a>
    </h2>
</div>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div class="card-header">
        <h3>System Update</h3>
    </div>
    <div class="card-body">
        
        <?php if (!$updateInfo || !$updateInfo['available']): ?>
            <div class="text-center" style="padding: 40px;">
                <div style="font-size: 48px; margin-bottom: 20px;">✓</div>
                <h3>You are up to date!</h3>
                <p class="text-muted">Current Version: v<?php echo getCurrentVersion(); ?></p>
                <form method="POST" action="update.php" style="margin-top: 20px;">
                     <input type="hidden" name="action" value="force_check">
                     <button type="submit" class="btn btn-secondary">Check Again</button>
                </form>
            </div>
        <?php else: ?>
            
            <div id="update-intro">
                <div class="alert alert-info" style="border-left: 4px solid var(--primary);">
                    <h4 style="margin-top: 0;">New Version Available: v<?php echo h($updateInfo['latest_version']); ?></h4>
                    <p>Current Version: v<?php echo h($updateInfo['current_version']); ?></p>
                </div>

                <?php if (!empty($updateInfo['changelog'])): ?>
                    <div class="changelog-box" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; max-height: 200px; overflow-y: auto;">
                        <strong>What's New:</strong><br>
                        <?php echo nl2br(h($updateInfo['changelog'])); ?>
                    </div>
                <?php endif; ?>

                <div class="warning-box" style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <strong>⚠️ Important:</strong>
                    <ul style="margin-bottom: 0;">
                        <li>A full backup will be created automatically.</li>
                        <li>Do not close this window during the update.</li>
                        <li>The site may be briefly unavailable.</li>
                    </ul>
                </div>

                <div class="text-right">
                    <button id="start-btn" class="btn btn-primary btn-lg" onclick="startUpdate()">
                        🚀 Start Update
                    </button>
                </div>
            </div>

            <div id="update-progress" style="display: none;">
                <h4 id="status-text">Initializing...</h4>
                
                <div class="progress-container" style="background: #e9ecef; height: 20px; border-radius: 10px; margin: 20px 0; overflow: hidden;">
                    <div id="progress-bar" style="width: 0%; height: 100%; background: var(--primary); transition: width 0.3s ease;"></div>
                </div>

                <ul id="log-list" style="list-style: none; padding: 0; font-family: monospace; font-size: 12px; color: #666; max-height: 150px; overflow-y: auto; border: 1px solid #eee; padding: 10px;">
                    <li>Ready to start...</li>
                </ul>
            </div>

            <div id="update-complete" style="display: none; text-center">
                <div class="text-center" style="padding: 40px;">
                    <div style="font-size: 48px; color: var(--success); margin-bottom: 20px;">🎉</div>
                    <h3>Update Complete!</h3>
                    <p>You are now running version v<?php echo h($updateInfo['latest_version']); ?></p>
                    <a href="index.php" class="btn btn-primary">Return to Dashboard</a>
                </div>
            </div>

            <div id="update-error" style="display: none;">
                <div class="alert alert-danger">
                    <h4 style="margin-top: 0;">Update Failed</h4>
                    <p id="error-message">An unexpected error occurred.</p>
                </div>
                <button class="btn btn-secondary" onclick="location.reload()">Try Again</button>
            </div>

            <script>
                const steps = [
                    { id: 'backup', label: 'Creating Backup...', progress: 20 },
                    { id: 'download', label: 'Downloading Update...', progress: 50, data: { url: '<?php echo $updateInfo['download_url'] ?? ''; ?>' } },
                    { id: 'install', label: 'Installing Files...', progress: 80 },
                    { id: 'migrate', label: 'Updating Database...', progress: 100 }
                ];

                async function startUpdate() {
                    document.getElementById('update-intro').style.display = 'none';
                    document.getElementById('update-progress').style.display = 'block';
                    
                    for (const step of steps) {
                        if (!await runStep(step)) return;
                    }

                    setTimeout(() => {
                        document.getElementById('update-progress').style.display = 'none';
                        document.getElementById('update-complete').style.display = 'block';
                    }, 1000);
                }

                async function runStep(step) {
                    updateStatus(step.label, step.progress - 10);
                    log(`Starting: ${step.id}...`);

                    try {
                        const formData = new FormData();
                        formData.append('step', step.id);
                        if (step.data) {
                            for (const [key, val] of Object.entries(step.data)) {
                                formData.append(key, val);
                            }
                        }

                        const response = await fetch('process_update.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            log(`✓ ${result.message}`);
                            updateStatus(step.label, step.progress);
                            return true;
                        } else {
                            showError(result.message || 'Unknown error');
                            return false;
                        }
                    } catch (e) {
                        showError(e.message);
                        return false;
                    }
                }

                function updateStatus(text, percent) {
                    document.getElementById('status-text').innerText = text;
                    document.getElementById('progress-bar').style.width = percent + '%';
                }

                function log(message) {
                    const list = document.getElementById('log-list');
                    const li = document.createElement('li');
                    li.innerText = new Date().toLocaleTimeString() + ' - ' + message;
                    list.appendChild(li);
                    list.scrollTop = list.scrollHeight;
                }

                function showError(msg) {
                    document.getElementById('update-progress').style.display = 'none';
                    document.getElementById('update-error').style.display = 'block';
                    document.getElementById('error-message').innerText = msg;
                }
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
