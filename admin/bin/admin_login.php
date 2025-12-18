<?php
session_start();

// Define constants (must match validate_adminlog.php)
$maxAttempts = 3;
$lockoutTime = 300; // 5 minutes in seconds

// Get the current state from the session
$isLockedOut = false;
$remainingTime = 0;

if (isset($_SESSION['admin_login_attempts']) && isset($_SESSION['admin_last_attempt_time'])) {
    $currentAttempts = $_SESSION['admin_login_attempts'];
    $lastAttempt = $_SESSION['admin_last_attempt_time'];

    if ($currentAttempts >= $maxAttempts) {
        $timeElapsed = time() - $lastAttempt;
        
        if ($timeElapsed < $lockoutTime) {
            $isLockedOut = true;
            $remainingTime = $lockoutTime - $timeElapsed;
        } else {
            // Lockout period expired, reset attempts
            $_SESSION['admin_login_attempts'] = 0;
            unset($_SESSION['admin_last_attempt_time']);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="adminlog_des2.css">
    <link rel="icon" type="image/png" href="../assets/logo.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>

<body>
    <div class="login-container">
        <div class="login-left">
            <img src="../assets/logo.png" alt="CAS_Logo" class="login-logo">
            <h1 class="academy-name">TECHNOLOGICAL UNIVERSITY <br> OF THE PHILIPPINES</h1>
            <p class="academy-location">Ayala Blvd., corner San Marcelino St., Ermita, Manila, 1000</p>
        </div>
        <div class="login-right">
            <div class="login-card">
                <h2 class="login-heading">Admin Login</h2>
                <!--FORM-->
                <form action="validate_adminlog.php" method="POST">
                    <div class="input-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter Username" required>
                    </div>
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter Password" required>
                    </div>
                    
                    <!-- Show Password Checkbox -->
                    <div class="password-options">
                        <label class="show-password-label">
                            <input type="checkbox" id="showPassword" onclick="togglePassword()">
                            Show Password
                        </label>
                    </div>
                    
                    <button type="submit" class="login-btn">Login</button>
                </form>

                <!--DELETE
                <div class="login-options">
                    <a href="registration.php" class="create-account-link">Create a New Account</a>
                </div>
                
                <div class="login-footer">
                    <p>For concerns and inquiries, email us at:</p>
                    <p class="footer-email">uitc@tup.edu.ph</p>
                </div>
                -->
            </div>
        </div>
    </div>

    <script>
        // --- START: Login Lockout Logic ---
        const isLockedOut = <?php echo json_encode($isLockedOut); ?>;
        let remainingLockoutTime = <?php echo json_encode($remainingTime); ?>;

        const usernameInput = document.getElementById('username');
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
                usernameInput.removeAttribute('readonly');
                passwordInput.removeAttribute('readonly');
                loginButton.disabled = false;
            }
        }

        if (isLockedOut) {
            usernameInput.setAttribute('readonly', 'readonly');
            passwordInput.setAttribute('readonly', 'readonly');
            loginButton.disabled = true;
            firstInputGroup.before(countdownElement);
            updateCountdown();
        }
        // --- END: Login Lockout Logic ---

        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const checkbox = document.getElementById('showPassword');
            
            if (checkbox.checked) {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</body>
</html>