<?php
session_start();

// Check if user has pending 2FA verification
if (!isset($_SESSION['2fa_pending']) || !$_SESSION['2fa_pending']) {
    header('Location: login.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tupdb";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$errorMessage = '';
$successMessage = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $enteredCode = trim($_POST['verification_code']);
    $email = $_SESSION['2fa_email'];
    
    // Validate code format (6 digits)
    if (!preg_match('/^\d{6}$/', $enteredCode)) {
        $errorMessage = 'Invalid code format. Please enter a 6-digit code.';
    } else {
        try {
            // Fetch the latest valid code for this user
            $stmt = $conn->prepare("
                SELECT code, expires_at, is_used 
                FROM two_factor_codes 
                WHERE user_email = :email 
                AND is_used = 0 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([':email' => $email]);
            $codeData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$codeData) {
                $errorMessage = 'No valid verification code found. Please request a new one.';
            } elseif (strtotime($codeData['expires_at']) < time()) {
                $errorMessage = 'Verification code has expired. Please request a new one.';
                
                // Mark code as used
                $updateStmt = $conn->prepare("UPDATE two_factor_codes SET is_used = 1 WHERE user_email = :email AND code = :code");
                $updateStmt->execute([':email' => $email, ':code' => $codeData['code']]);
            } elseif ($codeData['code'] !== $enteredCode) {
                $errorMessage = 'Incorrect verification code. Please try again.';
            } else {
                // ✅ Code is valid - Mark as used
                $updateStmt = $conn->prepare("UPDATE two_factor_codes SET is_used = 1 WHERE user_email = :email AND code = :code");
                $updateStmt->execute([':email' => $email, ':code' => $enteredCode]);
                
                // Complete login based on user type
                $userType = $_SESSION['2fa_user_type'];
                
                if ($userType === 'admin') {
                    // Complete admin login
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $email;
                    $_SESSION['login_time'] = time();
                    
                    // Clear 2FA session data
                    unset($_SESSION['2fa_pending']);
                    unset($_SESSION['2fa_email']);
                    unset($_SESSION['2fa_user_type']);
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
                    
                    header('Location: admin/admin_dboard.html');
                    exit();
                    
                } elseif ($userType === 'cashier') {
                    // Complete cashier login
                    $_SESSION['cashier_logged_in'] = true;
                    $_SESSION['cashier_username'] = $email;
                    $_SESSION['login_time'] = time();
                    
                    // Clear 2FA session data
                    unset($_SESSION['2fa_pending']);
                    unset($_SESSION['2fa_email']);
                    unset($_SESSION['2fa_user_type']);
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
                    
                    header('Location: admin/ad_cashier.php');
                    exit();
                    
                } else {
                    // Complete student login
                    $userData = $_SESSION['2fa_user_data'];
                    
                    $_SESSION['reg_id'] = $userData['reg_id'];
                    $_SESSION['user_name'] = $userData['firstName'];
                    $_SESSION['user_email'] = $email;
                    
                    // Get the most recent req_id
                    $getReqId = $conn->prepare("SELECT req_id FROM request_student_tbl WHERE reg_id = :reg_id ORDER BY req_id DESC LIMIT 1");
                    $getReqId->execute([':reg_id' => $userData['reg_id']]);
                    $reqRow = $getReqId->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reqRow) {
                        $_SESSION['req_id'] = $reqRow['req_id'];
                    } else {
                        $_SESSION['req_id'] = null;
                    }
                    
                    // Clear 2FA session data
                    unset($_SESSION['2fa_pending']);
                    unset($_SESSION['2fa_email']);
                    unset($_SESSION['2fa_user_type']);
                    unset($_SESSION['2fa_user_data']);
                    
                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
                    
                    header('Location: Main.php');
                    exit();
                }
            }
        } catch(PDOException $e) {
            $errorMessage = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle resend code request
if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    require_once 'email-config.php';
    
    $email = $_SESSION['2fa_email'];
    $userType = $_SESSION['2fa_user_type'];
    
    // Generate new code
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    try {
        // Delete old codes
        $deleteStmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_email = :email AND is_used = 0");
        $deleteStmt->execute([':email' => $email]);
        
        // Save new code
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . CODE_EXPIRY_MINUTES . ' minutes'));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        $insertStmt = $conn->prepare("
            INSERT INTO two_factor_codes (user_email, user_type, code, expires_at, ip_address) 
            VALUES (:email, :user_type, :code, :expires_at, :ip_address)
        ");
        
        $insertStmt->execute([
            ':email' => $email,
            ':user_type' => $userType,
            ':code' => $code,
            ':expires_at' => $expiresAt,
            ':ip_address' => $ipAddress
        ]);
        
        // Get user name based on type
        if ($userType === 'admin') {
            $userName = 'Admin';
        } elseif ($userType === 'cashier') {
            $userName = 'Cashier';
        } else {
            $userName = $_SESSION['2fa_user_data']['firstName'];
        }
        
        // Send email
        if (send2FAEmail($email, $userName, $code)) {
            $successMessage = 'A new verification code has been sent to your email.';
        } else {
            $errorMessage = 'Failed to send verification code. Please try again.';
        }
    } catch(PDOException $e) {
        $errorMessage = 'Error generating new code: ' . $e->getMessage();
    }
}

$conn = null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - TUP</title>
    <link rel="icon" type="image/png" href="assets/logo.png?v=2">
    <link rel="stylesheet" href="verify_2fa.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="verification-container">
        <div class="logo-container">
            <img src="./assets/logo.png" alt="TUP Logo" class="logo">
        </div>

        <div class="verification-icon">🔐</div>

        <h1>Enter Verification Code</h1>
        <p class="subtitle">
            We've sent a 6-digit verification code to your email address. 
            Please enter it below to complete your login.
        </p>

        <div class="email-display">
            <?php echo htmlspecialchars($_SESSION['2fa_email']); ?>
        </div>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="code-input-container">
                <input 
                    type="text" 
                    name="verification_code" 
                    class="code-input" 
                    maxlength="6" 
                    placeholder="000000"
                    pattern="\d{6}"
                    required
                    autofocus
                    autocomplete="off"
                    inputmode="numeric"
                >
            </div>

            <button type="submit" class="verify-btn">Verify Code</button>
        </form>

        <div class="resend-container">
            <p class="resend-text">Didn't receive the code?</p>
            <a href="?resend=1" class="resend-btn">Resend Code</a>
        </div>

        <a href="login.php" class="back-to-login">← Back to Login</a>

        <div class="timer" id="timer"></div>
    </div>

    <script>
        // Auto-format code input (only allow numbers)
        const codeInput = document.querySelector('.code-input');
        
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Auto-submit when 6 digits are entered
        codeInput.addEventListener('input', function(e) {
            if (this.value.length === 6) {
                setTimeout(() => {
                    this.form.submit();
                }, 500);
            }
        });

        // Countdown timer (10 minutes)
        let timeLeft = 600; // 10 minutes in seconds
        const timerElement = document.getElementById('timer');

        function updateTimer() {
            if (timeLeft > 0) {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerElement.textContent = `Code expires in ${minutes}:${seconds.toString().padStart(2, '0')}`;
                timeLeft--;
                setTimeout(updateTimer, 1000);
            } else {
                timerElement.textContent = 'Code expired. Please request a new one.';
                timerElement.style.color = '#c33';
            }
        }

        updateTimer();
    </script>
</body>
</html>