/**
 * FA Auction - Client-side JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // ========================================
    // Mobile Menu Toggle
    // ========================================
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function openMenu() {
        sidebar.classList.add('open');
        sidebarOverlay.classList.add('active');
        menuToggle.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('active');
        menuToggle.classList.remove('active');
        document.body.style.overflow = '';
    }

    function toggleMenu() {
        if (sidebar.classList.contains('open')) {
            closeMenu();
        } else {
            openMenu();
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', toggleMenu);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeMenu);
    }

    // Close menu when clicking a nav link (mobile)
    const navLinks = document.querySelectorAll('.sidebar-nav a');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) {
                closeMenu();
            }
        });
    });

    // Close menu on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('open')) {
            closeMenu();
        }
    });

    // Close menu on window resize if going to desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768 && sidebar && sidebar.classList.contains('open')) {
            closeMenu();
        }
    });

    // ========================================
    // Auto-hide flash messages after 5 seconds
    // ========================================
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function () {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // ========================================
    // Confirm dialogs for delete actions
    // ========================================
    const confirmForms = document.querySelectorAll('form[data-confirm], button[data-confirm]');
    confirmForms.forEach(function (element) {
        if (element.tagName === 'FORM') {
            element.addEventListener('submit', async function (e) {
                if (!element.dataset.confirmed) {
                    e.preventDefault();
                    const confirmed = await showConfirm(element.dataset.confirm);
                    if (confirmed) {
                        element.dataset.confirmed = 'true';
                        element.submit();
                    }
                }
            });
        }
    });

    // ========================================
    // Format money inputs
    // ========================================
    const moneyInputs = document.querySelectorAll('input[type="number"][step="1000000"], input[type="number"][step="100000"]');
    moneyInputs.forEach(function (input) {
        input.addEventListener('blur', function () {
            const step = parseFloat(input.step) || 100000;
            const value = parseFloat(input.value) || 0;
            input.value = Math.round(value / step) * step;
        });
    });

    // ========================================
    // Smooth scroll for anchor links
    // ========================================
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // ========================================
    // Table horizontal scroll indicator
    // ========================================
    const tableContainers = document.querySelectorAll('.table-container');
    tableContainers.forEach(function (container) {
        const table = container.querySelector('table');
        if (table && table.scrollWidth > container.clientWidth) {
            container.classList.add('scrollable');
        }
    });

    // ========================================
    // Touch-friendly number inputs
    // ========================================
    const numberInputs = document.querySelectorAll('input[type="number"]');
    numberInputs.forEach(function (input) {
        // Prevent scroll from changing value
        input.addEventListener('wheel', function (e) {
            if (document.activeElement === this) {
                e.preventDefault();
            }
        }, { passive: false });
    });

    // ========================================
    // Auction Countdown Clock
    // ========================================
    const countdownElements = document.querySelectorAll('.countdown-clock');
    if (countdownElements.length > 0) {
        // Find the most prominent countdown to get server time reference
        const refEl = countdownElements[0];
        const serverStart = parseInt(refEl.dataset.now);
        const browserStart = Math.floor(Date.now() / 1000);
        const timeOffset = serverStart - browserStart;

        function updateCountdowns() {
            const browserNow = Math.floor(Date.now() / 1000);
            const serverNow = browserNow + timeOffset;

            countdownElements.forEach(function (el) {
                const deadline = parseInt(el.dataset.deadline);
                const timerDisplay = el.querySelector('.countdown-timer');

                if (isNaN(deadline) || !timerDisplay) return;

                const timeLeft = deadline - serverNow;

                if (timeLeft <= 0) {
                    timerDisplay.textContent = "Auction Closed";
                    timerDisplay.style.color = "var(--danger)";
                    return;
                }

                const days = Math.floor(timeLeft / 86400);
                const hours = Math.floor((timeLeft % 86400) / 3600);
                const minutes = Math.floor((timeLeft % 3600) / 60);
                const seconds = timeLeft % 60;

                let display = "";
                if (days > 0) display += days + "d ";
                display += hours.toString().padStart(2, '0') + "h ";
                display += minutes.toString().padStart(2, '0') + "m ";
                display += seconds.toString().padStart(2, '0') + "s";

                timerDisplay.textContent = display;
            });
        }

        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    }

});

// ========================================
// Utility Functions
// ========================================

// Format currency
function formatMoney(amount) {
    return '$' + parseInt(amount).toLocaleString();
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
// showConfirm: Styled replacement for window.confirm
function showConfirm(message, title = 'Confirm Action') {
    return new Promise((resolve) => {
        // Create modal elements
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';

        const modal = document.createElement('div');
        modal.className = 'modal';

        modal.innerHTML = `
            <div class="modal-header">
                <h3>${title}</h3>
            </div>
            <div class="modal-body">
                <p>${message}</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="modal-cancel">Cancel</button>
                <button class="btn btn-primary" id="modal-confirm">Confirm</button>
            </div>
        `;

        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        // Show modal
        setTimeout(() => backdrop.classList.add('active'), 10);

        const cleanup = (result) => {
            backdrop.classList.remove('active');
            setTimeout(() => {
                document.body.removeChild(backdrop);
                resolve(result);
            }, 300);
        };

        // Event listeners
        document.getElementById('modal-cancel').onclick = () => cleanup(false);
        document.getElementById('modal-confirm').onclick = () => cleanup(true);
        backdrop.onclick = (e) => {
            if (e.target === backdrop) cleanup(false);
        };

        // Handle enter/escape
        const handleKeys = (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                cleanup(true);
                document.removeEventListener('keydown', handleKeys);
            } else if (e.key === 'Escape') {
                cleanup(false);
                document.removeEventListener('keydown', handleKeys);
            }
        };
        document.addEventListener('keydown', handleKeys);
    });
}
