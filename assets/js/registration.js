/**
 * Registration Form Enhancements
 *
 * Handles real-time validation, password strength indicator,
 * and email availability checking.
 */

(function() {
    'use strict';

    // Wait for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initPasswordStrength();
        initPasswordMatch();
        initEmailAvailability();
        initNameValidation();
    });

    /**
     * Password Strength Indicator
     */
    function initPasswordStrength() {
        var passwordInput = document.getElementById('password');
        var strengthMeter = document.getElementById('password-strength');

        if (!passwordInput || !strengthMeter) return;

        passwordInput.addEventListener('input', function() {
            var password = this.value;
            var strength = calculatePasswordStrength(password);
            updateStrengthMeter(strengthMeter, strength, password.length);
        });

        // Also validate on blur
        passwordInput.addEventListener('blur', function() {
            validatePasswordField(this);
        });
    }

    /**
     * Calculate password strength score
     */
    function calculatePasswordStrength(password) {
        var strength = 0;

        if (password.length === 0) return 0;
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        return strength;
    }

    /**
     * Update the strength meter display
     */
    function updateStrengthMeter(meter, strength, length) {
        var levels = ['', 'weak', 'weak', 'fair', 'good', 'strong', 'very-strong'];
        var labels = ['', 'Weak', 'Weak', 'Fair', 'Good', 'Strong', 'Very Strong'];
        var colors = ['', '#ef4444', '#ef4444', '#f59e0b', '#eab308', '#22c55e', '#16a34a'];

        var level = Math.min(strength, 6);

        // Clear previous classes
        meter.className = 'password-strength-meter';

        if (length > 0 && level > 0) {
            meter.classList.add(levels[level]);
            meter.innerHTML = '<span class="strength-label" style="color:' + colors[level] + '">' + labels[level] + '</span>';
            meter.style.setProperty('--strength-width', (level * 16.67) + '%');
        } else {
            meter.innerHTML = '';
            meter.style.setProperty('--strength-width', '0%');
        }
    }

    /**
     * Validate password field and show requirements
     */
    function validatePasswordField(input) {
        var password = input.value;
        var isValid = password.length >= 8 &&
                     /[A-Z]/.test(password) &&
                     /[a-z]/.test(password) &&
                     /[0-9]/.test(password);

        setFieldState(input, isValid, 'Password must meet all requirements');
    }

    /**
     * Password Match Validation
     */
    function initPasswordMatch() {
        var passwordInput = document.getElementById('password');
        var confirmInput = document.getElementById('password_confirm');
        var matchStatus = document.getElementById('password-match');

        if (!passwordInput || !confirmInput) return;

        function checkMatch() {
            var password = passwordInput.value;
            var confirm = confirmInput.value;

            if (confirm.length === 0) {
                if (matchStatus) matchStatus.innerHTML = '';
                confirmInput.classList.remove('is-valid', 'is-invalid');
                return;
            }

            if (password === confirm) {
                if (matchStatus) {
                    matchStatus.innerHTML = '<span class="text-success">&#10003; Passwords match</span>';
                }
                setFieldState(confirmInput, true);
            } else {
                if (matchStatus) {
                    matchStatus.innerHTML = '<span class="text-danger">&#10007; Passwords do not match</span>';
                }
                setFieldState(confirmInput, false);
            }
        }

        confirmInput.addEventListener('input', checkMatch);
        confirmInput.addEventListener('blur', checkMatch);
        passwordInput.addEventListener('input', function() {
            if (confirmInput.value.length > 0) {
                checkMatch();
            }
        });
    }

    /**
     * Email Availability Check
     */
    function initEmailAvailability() {
        var emailInput = document.getElementById('email');
        var statusElement = document.getElementById('email-status');

        if (!emailInput || !statusElement) return;

        var debounceTimer = null;

        emailInput.addEventListener('blur', function() {
            var email = this.value.trim();

            // Clear any pending checks
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            // Basic validation first
            if (!isValidEmail(email)) {
                if (email.length > 0) {
                    statusElement.innerHTML = '<span class="text-danger">&#10007; Invalid email format</span>';
                    setFieldState(emailInput, false);
                } else {
                    statusElement.innerHTML = '';
                    emailInput.classList.remove('is-valid', 'is-invalid');
                }
                return;
            }

            // Show checking status
            statusElement.innerHTML = '<span class="text-muted">Checking availability...</span>';

            // Debounce the API call
            debounceTimer = setTimeout(function() {
                checkEmailAvailability(email, statusElement, emailInput);
            }, 300);
        });
    }

    /**
     * Check email availability via API
     */
    function checkEmailAvailability(email, statusElement, emailInput) {
        fetch('../api/check-email.php?email=' + encodeURIComponent(email))
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.available) {
                    statusElement.innerHTML = '<span class="text-success">&#10003; Email is available</span>';
                    setFieldState(emailInput, true);
                } else {
                    statusElement.innerHTML = '<span class="text-danger">&#10007; Email is already registered</span>';
                    setFieldState(emailInput, false);
                }
            })
            .catch(function() {
                statusElement.innerHTML = '';
                emailInput.classList.remove('is-valid', 'is-invalid');
            });
    }

    /**
     * Name Field Validation
     */
    function initNameValidation() {
        var nameInput = document.getElementById('name');

        if (!nameInput) return;

        nameInput.addEventListener('blur', function() {
            var name = this.value.trim();
            var isValid = name.length >= 2;
            setFieldState(this, isValid, 'Name must be at least 2 characters');
        });
    }

    /**
     * Set field validation state
     */
    function setFieldState(input, isValid, errorMessage) {
        // Remove existing error messages
        var existingError = input.parentElement.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }

        if (input.value.length === 0) {
            input.classList.remove('is-valid', 'is-invalid');
            return;
        }

        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');

            if (errorMessage) {
                var error = document.createElement('small');
                error.className = 'field-error text-danger';
                error.textContent = errorMessage;
                input.parentElement.appendChild(error);
            }
        }
    }

    /**
     * Validate email format
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

})();
