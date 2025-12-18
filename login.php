<?php
session_start();

// Define constants (must match validate_login.php)
$maxAttempts = 3;
$lockoutTime = 300; // 5 minutes in seconds

// Get the current state from the session
$isLockedOut = false;
$remainingTime = 0;

if (isset($_SESSION['login_attempts']) && isset($_SESSION['last_attempt_time'])) {
    $currentAttempts = $_SESSION['login_attempts'];
    $lastAttempt = $_SESSION['last_attempt_time'];

    if ($currentAttempts >= $maxAttempts) {
        $timeElapsed = time() - $lastAttempt;
        
        if ($timeElapsed < $lockoutTime) {
            $isLockedOut = true;
            $remainingTime = $lockoutTime - $timeElapsed;
        } else {
            // Lockout period expired, reset attempts
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['last_attempt_time']);
        }
    }
}

// Include Google OAuth configuration
require_once 'google-config.php';

// Generate Google OAuth URL for regular registration
$googleAuthUrl = GOOGLE_OAUTH_URL . '?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account'
]);

// Generate Google OAuth URL for alumni registration
$googleAlumniAuthUrl = GOOGLE_OAUTH_URL . '?' . http_build_query([
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'email profile',
    'access_type' => 'online',
    'prompt' => 'select_account',
    'state' => 'alumni_registration'
]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technological University of the Philippines</title>
    <link rel="stylesheet" href="login_des6.css">
    <link rel="icon" type="image/png" href="assets/logo.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>
    <!-- Alumni Success Modal -->
    <div class="modal-overlay" id="alumniSuccessModal">
        <div class="modal-container">
            <div class="success-icon">
                <svg viewBox="0 0 52 52">
                    <polyline points="14,27 22,35 38,19"/>
                </svg>
            </div>

            <h2 class="modal-title">Registration Submitted</h2>
            <p class="modal-subtitle">Successfully!</p>

            <div class="info-box">
                <div class="info-header">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                    </svg>
                    Important Reminders:
                </div>
                <ul>
                    <li><strong>Your account is pending approval</strong></li>
                    <li>Please <span class="email-highlight">wait for an email notification</span> regarding your registration status</li>
                    <li>The administrator will review your information and <strong>approve or deny</strong> your application</li>
                    <li>You will be able to login once your account is <span class="highlight">approved</span></li>
                </ul>
            </div>

            <p class="redirect-message">
                Thank you for registering! You may now close this window.
            </p>

            <button class="ok-button" onclick="closeModal()">OK, Got it!</button>
        </div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-left">
            <img src="./assets/logo.png" alt="CAS_Logo" class="login-logo">
            <h1 class="odss">ONLINE DOCUMENT SERVICES SYSTEM</h1>
            <h1 class="academy-name">TECHNOLOGICAL UNIVERSITY <br> OF THE PHILIPPINES</h1>
            <p class="academy-location">Ayala Blvd., corner San Marcelino St., Ermita, Manila, 1000</p>
        </div>
        <div class="login-right">
            <div class="login-card">
                <h2 class="login-heading">Login</h2>
                
                <form action="validate_login.php" method="POST">
                    <div class="input-group">
                        <label for="studentEmail">Email</label>
                        <input type="text" id="studentEmail" name="studentEmail" placeholder="Enter your email" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="password-options">
                        <label class="show-password-label">
                            <input type="checkbox" id="showPassword" onclick="togglePassword()">
                            Show Password
                        </label>
                    </div>
                    
                    <button type="submit" class="login-btn">Login</button>
                </form>

                <div class="forgot-password-section">
                    <a href="#" class="forgot-password-link" onclick="showForgotPassword(event)">Forgot Password?</a>
                </div>

                <div class="google-login-section">
                    <a href="<?php echo htmlspecialchars($googleAuthUrl); ?>" class="google-login-btn student-btn">
                        <svg class="google-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Register as Student
                    </a>
                    
                    <a href="<?php echo htmlspecialchars($googleAlumniAuthUrl); ?>" class="google-login-btn alumni-btn">
                        <svg class="google-icon" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Register as Alumni
                    </a>
                </div>
                
                <div class="login-footer">
                    <p>For concerns and inquiries, email us at:</p>
                    <p class="footer-email">uitc@tup.edu.ph</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Alumni Registration Success Popup
        window.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('alumni_registered') === '1') {
                document.getElementById('alumniSuccessModal').classList.add('active');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function closeModal() {
            document.getElementById('alumniSuccessModal').classList.remove('active');
        }

        document.getElementById('alumniSuccessModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Login Lockout Logic
        const isLockedOut = <?php echo json_encode($isLockedOut); ?>;
        let remainingLockoutTime = <?php echo json_encode($remainingTime); ?>;

        const emailInput = document.getElementById('studentEmail');
        const passwordInput = document.getElementById('password');
        const loginButton = document.querySelector('.login-btn');
        const firstInputGroup = document.querySelector('.input-group');
        
        const countdownElement = document.createElement('p');
        countdownElement.id = 'lockout-timer';
        countdownElement.style.color = 'red';
        countdownElement.style.textAlign = 'center';
        countdownElement.style.marginBottom = '10px';

        function updateCountdown() {
            if (remainingLockoutTime > 0) {
                const minutes = Math.floor(remainingLockoutTime / 60);
                const seconds = remainingLockoutTime % 60;
                countdownElement.textContent = `Locked out. Try again in ${minutes}m ${seconds}s`;
                remainingLockoutTime--;
                setTimeout(updateCountdown, 1000);
            } else {
                countdownElement.textContent = 'Lockout finished. Please refresh the page.';
                emailInput.removeAttribute('readonly');
                passwordInput.removeAttribute('readonly');
                loginButton.disabled = false;
            }
        }

        if (isLockedOut) {
            emailInput.setAttribute('readonly', 'readonly');
            passwordInput.setAttribute('readonly', 'readonly');
            loginButton.disabled = true;
            firstInputGroup.before(countdownElement);
            updateCountdown();
        }

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const checkbox = document.getElementById('showPassword');
            passwordInput.type = checkbox.checked ? 'text' : 'password';
        }

        function showForgotPassword(event) {
            event.preventDefault();
            alert('Please contact the admin.\nEmail: uitc@tup.edu.ph');
        }
    </script>
</body>
</html>