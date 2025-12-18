<?php
session_start();

// --- START: Login Limit Code ---
define('MAX_ATTEMPTS', 3);
define('LOCKOUT_TIME', 300); // 5 minutes in seconds

// Initialize session variables if they don't exist
if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
}
if (!isset($_SESSION['admin_last_attempt_time'])) {
    $_SESSION['admin_last_attempt_time'] = time();
}

// Check if the admin is currently locked out
if ($_SESSION['admin_login_attempts'] >= MAX_ATTEMPTS && (time() - $_SESSION['admin_last_attempt_time'] < LOCKOUT_TIME)) {
    $remaining_lockout = LOCKOUT_TIME - (time() - $_SESSION['admin_last_attempt_time']);
    
    // Calculate minutes and seconds
    $minutes = floor($remaining_lockout / 60);
    $seconds = $remaining_lockout % 60;
    
    echo "
    <script>
        alert('Request limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
        window.location.href = 'admin_login.php';
    </script>
    ";
    exit();
}

// If the time since the last attempt exceeds the lockout time, reset attempts
if (time() - $_SESSION['admin_last_attempt_time'] >= LOCKOUT_TIME) {
    $_SESSION['admin_login_attempts'] = 0;
}
// --- END: Login Limit Code ---

// Database configuration
$host = 'localhost';
$dbname = 'tupdb';
$db_username = 'root';
$db_password = '';

// Block direct access if not POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_login.php');
    exit();
}

// Sanitize inputs
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validate inputs
if (empty($username) || empty($password)) {
    echo "<script>alert('Please fill in both fields.'); window.location.href='admin_login.php';</script>";
    exit();
}

try {
    // PDO secure connection
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Safe query (prevents SQL Injection)
    $stmt = $pdo->prepare("SELECT username, password FROM adminlog_tbl WHERE username = :username LIMIT 1");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // If username exists
    if ($admin) {
        $storedPassword = $admin['password'];
        $isPasswordCorrect = false;

        // If stored password is HASHED, verify normally
        if (password_verify($password, $storedPassword)) {
            $isPasswordCorrect = true;
        }
        // If password is plain text (first time only)
        elseif ($password === $storedPassword) {
            $isPasswordCorrect = true;
            
            // Hash the password now
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE adminlog_tbl SET password = :hash WHERE username = :username");
            $update->bindParam(':hash', $newHash, PDO::PARAM_STR);
            $update->bindParam(':username', $username, PDO::PARAM_STR);
            $update->execute();
        }

        if (!$isPasswordCorrect) {
            // Check if this attempt will be the 3rd failure (which triggers the lockout)
            if ($_SESSION['admin_login_attempts'] + 1 >= MAX_ATTEMPTS) {
                // This is the 3rd failed attempt - Lockout Triggered
                $_SESSION['admin_login_attempts'] = $_SESSION['admin_login_attempts'] + 1;
                $_SESSION['admin_last_attempt_time'] = time();

                $remaining_lockout = LOCKOUT_TIME; 
                $minutes = floor($remaining_lockout / 60);
                $seconds = $remaining_lockout % 60;

                echo "
                <script>
                    alert('Request limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
                    window.location.href = 'admin_login.php';
                </script>
                ";
                exit();
            } else {
                // This is the 1st or 2nd failed attempt
                $_SESSION['admin_login_attempts'] = $_SESSION['admin_login_attempts'] + 1;
                $_SESSION['admin_last_attempt_time'] = time();

                echo "
                <script>
                    alert('Incorrect password!');
                    window.location.href = 'admin_login.php';
                </script>
                ";
                exit();
            }
        } else {
            // ✅ Successful login: Reset attempt counter
            $_SESSION['admin_login_attempts'] = 0;
            unset($_SESSION['admin_last_attempt_time']);

            // Set session securely
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['login_time'] = time();

            header('Location: admin_dboard.html');
            exit();
        }
    }

    // Username not found - increment attempts
    if ($_SESSION['admin_login_attempts'] + 1 >= MAX_ATTEMPTS) {
        $_SESSION['admin_login_attempts'] = $_SESSION['admin_login_attempts'] + 1;
        $_SESSION['admin_last_attempt_time'] = time();

        $remaining_lockout = LOCKOUT_TIME; 
        $minutes = floor($remaining_lockout / 60);
        $seconds = $remaining_lockout % 60;

        echo "
        <script>
            alert('Request limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
            window.location.href = 'admin_login.php';
        </script>
        ";
        exit();
    } else {
        $_SESSION['admin_login_attempts'] = $_SESSION['admin_login_attempts'] + 1;
        $_SESSION['admin_last_attempt_time'] = time();
        
        echo "<script>alert('Username not found!'); window.location.href='admin_login.php';</script>";
        exit();
    }

} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
    echo "<script>alert('Database error occurred. Try again later.'); window.location.href='admin_login.php';</script>";
    exit();
}
?>