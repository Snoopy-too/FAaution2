<?php
/**
 * Common Header Include
 *
 * Sets up the page structure and navigation.
 */

if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'FA Auction');
}

$appName = getSetting('app_name', 'FA Auction');
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Auction Deadline for Countdown
$deadlineType = getSetting('deadline_type', 'manual');
$deadlineDatetime = getSetting('deadline_datetime');
$isDeadlineActive = $deadlineType === 'datetime' && $deadlineDatetime;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#111827">
    <title><?php echo h(PAGE_TITLE); ?> - <?php echo h($appName); ?></title>
    <link rel="stylesheet" href="<?php echo getBaseUrl(); ?>/assets/css/style.css">
</head>
<body>
    <!-- Mobile Header -->
    <header class="mobile-header">
        <h1><?php echo h($appName); ?></h1>
        <?php if ($isDeadlineActive): ?>
            <div class="countdown-clock header-countdown" 
                 data-deadline="<?php echo strtotime($deadlineDatetime); ?>"
                 data-now="<?php echo time(); ?>">
                <div class="countdown-timer">--:--:--:--</div>
            </div>
        <?php endif; ?>
        <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu">
            <span></span>
            <span></span>
            <span></span>
        </button>
    </header>

    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="app-wrapper">
        <?php if (isAdmin()): ?>
        <!-- Admin Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1><?php echo h($appName); ?></h1>
                <p>Admin Panel</p>
                <?php if ($isDeadlineActive): ?>
                    <div id="countdown-clock" class="countdown-clock" 
                         data-deadline="<?php echo strtotime($deadlineDatetime); ?>"
                         data-now="<?php echo time(); ?>">
                        <div class="countdown-label">Auction Ends In:</div>
                        <div class="countdown-timer">--:--:--:--</div>
                    </div>
                <?php endif; ?>
            </div>
            <ul class="sidebar-nav">
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/index.php"
                       class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9632;</span> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/settings.php"
                       class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9881;</span> Settings
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/teams.php"
                       class="<?php echo $currentPage === 'teams' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9873;</span> Teams
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/members.php"
                       class="<?php echo $currentPage === 'members' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9787;</span> Members
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/players.php"
                       class="<?php echo $currentPage === 'players' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9918;</span> Players
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/bids.php"
                       class="<?php echo $currentPage === 'bids' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#36;</span> Bids
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/admin/archives.php"
                       class="<?php echo in_array($currentPage, ['archives', 'archive_view']) ? 'active' : ''; ?>">
                        <span class="nav-icon">📦</span> Archives
                    </a>
                </li>
                <li style="margin-top: 30px; border-top: 1px solid #374151; padding-top: 10px;">
                    <a href="<?php echo getBaseUrl(); ?>/auth/logout.php">
                        <span class="nav-icon">&#10132;</span> Logout
                    </a>
                </li>
            </ul>
        </nav>
        <?php else: ?>
        <!-- Member Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h1><?php echo h($appName); ?></h1>
                <p><?php echo h(getCurrentTeamName()); ?></p>
                <?php if ($isDeadlineActive): ?>
                    <div id="countdown-clock" class="countdown-clock" 
                         data-deadline="<?php echo strtotime($deadlineDatetime); ?>"
                         data-now="<?php echo time(); ?>">
                        <div class="countdown-label">Auction Ends In:</div>
                        <div class="countdown-timer">--:--:--:--</div>
                    </div>
                <?php endif; ?>
            </div>
            <ul class="sidebar-nav">
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/member/index.php"
                       class="<?php echo $currentPage === 'index' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9632;</span> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo getBaseUrl(); ?>/member/free-agents.php"
                       class="<?php echo $currentPage === 'free-agents' ? 'active' : ''; ?>">
                        <span class="nav-icon">&#9918;</span> Free Agents
                    </a>
                </li>
                <li style="margin-top: 30px; border-top: 1px solid #374151; padding-top: 10px;">
                    <a href="<?php echo getBaseUrl(); ?>/auth/logout.php">
                        <span class="nav-icon">&#10132;</span> Logout
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>

        <main class="main-content">
