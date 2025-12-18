<?php
session_start();

// --- START: Login Limit Code ---
define('MAX_ATTEMPTS', 3);
define('LOCKOUT_TIME', 300); // 5 minutes in seconds

// Initialize session variables if they don't exist
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_attempt_time'])) {
    $_SESSION['last_attempt_time'] = time();
}

// Check if the user is currently locked out
if ($_SESSION['login_attempts'] >= MAX_ATTEMPTS && (time() - $_SESSION['last_attempt_time'] < LOCKOUT_TIME)) {
    $remaining_lockout = LOCKOUT_TIME - (time() - $_SESSION['last_attempt_time']);
    
    // Calculate minutes and seconds
    $minutes = floor($remaining_lockout / 60);
    $seconds = $remaining_lockout % 60;
    
    echo "
    <script>
        alert('Login limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
        window.location.href = 'login.php';
    </script>
    ";
    exit();
}

// If the time since the last attempt exceeds the lockout time, reset attempts
if (time() - $_SESSION['last_attempt_time'] >= LOCKOUT_TIME) {
    $_SESSION['login_attempts'] = 0;
}
// --- END: Login Limit Code ---

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

// Include 2FA email configuration
require_once 'email-config.php';

/**
 * Generate 6-digit verification code
 */
function generate2FACode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Save 2FA code to database
 */
function save2FACode($conn, $email, $userType, $code) {
    try {
        // Delete any existing unused codes for this user
        $deleteStmt = $conn->prepare("DELETE FROM two_factor_codes WHERE user_email = :email AND is_used = 0");
        $deleteStmt->execute([':email' => $email]);
        
        // Calculate expiration time
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . CODE_EXPIRY_MINUTES . ' minutes'));
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Insert new code
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
        
        return true;
    } catch(PDOException $e) {
        error_log("2FA Code Save Error: " . $e->getMessage());
        return false;
    }
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['studentEmail']);
    $pass = trim($_POST['password']);

    // SQL Injection Prevention - Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "
        <script>
            alert('Invalid email format!');
            window.location.href = 'login.php';
        </script>
        ";
        exit();
    }

    try {
        // ✅ STEP 1: Check if this is an ADMIN login (check adminlog_tbl first)
        $adminStmt = $conn->prepare("SELECT username, password, two_factor_enabled FROM adminlog_tbl WHERE username = :username LIMIT 1");
        $adminStmt->execute([':username' => $email]);
        $adminRow = $adminStmt->fetch(PDO::FETCH_ASSOC);

        if ($adminRow) {
            // This is an ADMIN login attempt
            $storedPassword = $adminRow['password'];
            $isPasswordCorrect = false;

            // Try password_verify first (for hashed passwords)
            if (password_verify($pass, $storedPassword)) {
                $isPasswordCorrect = true;
            } 
            // Fall back to plain text comparison (for old passwords)
            elseif ($pass === $storedPassword) {
                $isPasswordCorrect = true;
                
                // Hash the password now for better security
                $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE adminlog_tbl SET password = :password WHERE username = :username");
                $updateStmt->execute([
                    ':password' => $hashedPassword,
                    ':username' => $email
                ]);
            }

            if (!$isPasswordCorrect) {
                // Failed admin login
                if ($_SESSION['login_attempts'] + 1 >= MAX_ATTEMPTS) {
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    $remaining_lockout = LOCKOUT_TIME; 
                    $minutes = floor($remaining_lockout / 60);
                    $seconds = $remaining_lockout % 60;

                    echo "
                    <script>
                        alert('Login limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                } else {
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    echo "
                    <script>
                        alert('Incorrect password! Please try again.');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                }
            } else {
                // ✅ Password correct - Now check if 2FA is enabled
                $twoFactorEnabled = isset($adminRow['two_factor_enabled']) ? $adminRow['two_factor_enabled'] : 1;
                
                if ($twoFactorEnabled) {
                    // Generate and send 2FA code
                    $code = generate2FACode();
                    
                    if (save2FACode($conn, $email, 'admin', $code)) {
                        // Send email
                        $emailSent = send2FAEmail($email, 'Admin', $code);
                        
                        if ($emailSent) {
                            // Store temporary session data for 2FA verification
                            $_SESSION['2fa_email'] = $email;
                            $_SESSION['2fa_user_type'] = 'admin';
                            $_SESSION['2fa_pending'] = true;
                            
                            // Redirect to 2FA verification page
                            header('Location: verify_2fa.php');
                            exit();
                        } else {
                            echo "
                            <script>
                                alert('Failed to send verification code. Please try again.');
                                window.location.href = 'login.php';
                            </script>
                            ";
                            exit();
                        }
                    } else {
                        echo "
                        <script>
                            alert('System error. Please try again.');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    }
                } else {
                    // 2FA disabled - Direct login
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
                    
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $adminRow['username'];
                    $_SESSION['login_time'] = time();

                    header('Location: admin/admin_dboard.html');
                    exit();
                }
            }
        }

        // ✅ STEP 2: Check if this is a CASHIER login (check cashierlog_tbl)
        $cashierStmt = $conn->prepare("SELECT username, password, two_factor_enabled FROM cashierlog_tbl WHERE username = :username LIMIT 1");
        $cashierStmt->execute([':username' => $email]);
        $cashierRow = $cashierStmt->fetch(PDO::FETCH_ASSOC);

        if ($cashierRow) {
            // This is a CASHIER login attempt
            $storedPassword = $cashierRow['password'];
            $isPasswordCorrect = false;

            // Try password_verify first (for hashed passwords)
            if (password_verify($pass, $storedPassword)) {
                $isPasswordCorrect = true;
            } 
            // Fall back to plain text comparison (for old passwords)
            elseif ($pass === $storedPassword) {
                $isPasswordCorrect = true;
                
                // Hash the password now for better security
                $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE cashierlog_tbl SET password = :password WHERE username = :username");
                $updateStmt->execute([
                    ':password' => $hashedPassword,
                    ':username' => $email
                ]);
            }

            if (!$isPasswordCorrect) {
                // Failed cashier login
                if ($_SESSION['login_attempts'] + 1 >= MAX_ATTEMPTS) {
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    $remaining_lockout = LOCKOUT_TIME; 
                    $minutes = floor($remaining_lockout / 60);
                    $seconds = $remaining_lockout % 60;

                    echo "
                    <script>
                        alert('Request limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                } else {
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    echo "
                    <script>
                        alert('Incorrect password! Please try again.');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                }
            } else {
                // ✅ Password correct - Now check if 2FA is enabled
                $twoFactorEnabled = isset($cashierRow['two_factor_enabled']) ? $cashierRow['two_factor_enabled'] : 1;
                
                if ($twoFactorEnabled) {
                    // Generate and send 2FA code
                    $code = generate2FACode();
                    
                    if (save2FACode($conn, $email, 'cashier', $code)) {
                        // Send email
                        $emailSent = send2FAEmail($email, 'Cashier', $code);
                        
                        if ($emailSent) {
                            // Store temporary session data for 2FA verification
                            $_SESSION['2fa_email'] = $email;
                            $_SESSION['2fa_user_type'] = 'cashier';
                            $_SESSION['2fa_pending'] = true;
                            
                            // Redirect to 2FA verification page
                            header('Location: verify_2fa.php');
                            exit();
                        } else {
                            echo "
                            <script>
                                alert('Failed to send verification code. Please try again.');
                                window.location.href = 'login.php';
                            </script>
                            ";
                            exit();
                        }
                    } else {
                        echo "
                        <script>
                            alert('System error. Please try again.');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    }
                } else {
                    // 2FA disabled - Direct login
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
                    
                    $_SESSION['cashier_logged_in'] = true;
                    $_SESSION['cashier_username'] = $cashierRow['username'];
                    $_SESSION['login_time'] = time();

                    header('Location: admin/ad_cashier.php');
                    exit();
                }
            }
        }

        // ✅ STEP 3: Not an admin or cashier, check STUDENT/ALUMNI login (registration_tbl)
        $stmt = $conn->prepare("SELECT * FROM registration_tbl WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            // Email not found in all tables
            echo "
            <script>
                alert('Email not found! Please register or check your email.');
                window.location.href = 'login.php';
            </script>
            ";
            exit();
        } else {
            // Email exists — now check password
            $isPasswordCorrect = false;
            
            // Try password_verify first (for hashed passwords)
            if (password_verify($pass, $row['password'])) {
                $isPasswordCorrect = true;
            } 
            // Fall back to plain text comparison (for old passwords)
            elseif ($row['password'] === $pass) {
                $isPasswordCorrect = true;
                
                // Optional: Update to hashed password for better security
                $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE registration_tbl SET password = :password WHERE reg_id = :reg_id");
                $updateStmt->execute([
                    ':password' => $hashedPassword,
                    ':reg_id' => $row['reg_id']
                ]);
            }
            
            if (!$isPasswordCorrect) {
                // Check if this attempt will be the 3rd failure (which triggers the lockout)
                if ($_SESSION['login_attempts'] + 1 >= MAX_ATTEMPTS) {
                    // This is the 3rd failed attempt - Lockout Triggered
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    $remaining_lockout = LOCKOUT_TIME; 
                    $minutes = floor($remaining_lockout / 60);
                    $seconds = $remaining_lockout % 60;

                    echo "
                    <script>
                        alert('Request limit exceeded. You have reached the maximum of " . MAX_ATTEMPTS . " login attempts within " . (LOCKOUT_TIME / 60) . " minutes.\\n\\nPlease try again in approximately " . $minutes . " minute(s) and " . $seconds . " second(s).');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                } else {
                    // This is the 1st or 2nd failed attempt
                    $_SESSION['login_attempts'] = $_SESSION['login_attempts'] + 1;
                    $_SESSION['last_attempt_time'] = time();

                    echo "
                    <script>
                        alert('Incorrect password! Please try again.');
                        window.location.href = 'login.php';
                    </script>
                    ";
                    exit();
                }
            } else {
                // ✅ Password correct - Now check if this is an ALUMNI and their account status
                $isAlumni = isset($row['is_alumni']) && $row['is_alumni'] == 1;
                
                if ($isAlumni) {
                    $accountStatus = $row['account_status'];
                    
                    // Check account status
                    if ($accountStatus === 'Pending') {
                        echo "
                        <script>
                            alert('Account Pending Approval\\n\\nYour alumni registration is currently pending administrator approval.\\n\\nPlease wait for an email notification regarding your account status. You will be able to login once your account has been approved.\\n\\nThank you for your patience!');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    } elseif ($accountStatus === 'Denied') {
                        echo "
                        <script>
                            alert('Account Registration Denied\\n\\nYour alumni registration has been denied by the administrator.\\n\\nIf you believe this is an error or have any concerns, please contact the registrar office at:\\n\\nregistrar@tup.edu.ph\\n\\nThank you.');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    } elseif ($accountStatus !== 'Approved') {
                        // Any other status (should not happen, but just in case)
                        echo "
                        <script>
                            alert('Account Status Error\\n\\nYour account status is unclear. Please contact the registrar office at:\\n\\nregistrar@tup.edu.ph\\n\\nThank you.');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    }
                    
                    // If account_status is 'Approved', continue with login process below
                }
                
                // ✅ Password correct and account approved (or is a student) - Now check if 2FA is enabled
                $twoFactorEnabled = isset($row['two_factor_enabled']) ? $row['two_factor_enabled'] : 1;
                
                if ($twoFactorEnabled) {
                    // Generate and send 2FA code
                    $code = generate2FACode();
                    
                    if (save2FACode($conn, $email, 'student', $code)) {
                        // Send email
                        $emailSent = send2FAEmail($email, $row['firstName'], $code);
                        
                        if ($emailSent) {
                            // Store temporary session data for 2FA verification
                            $_SESSION['2fa_email'] = $email;
                            $_SESSION['2fa_user_type'] = 'student';
                            $_SESSION['2fa_user_data'] = [
                                'reg_id' => $row['reg_id'],
                                'firstName' => $row['firstName']
                            ];
                            $_SESSION['2fa_pending'] = true;
                            
                            // Redirect to 2FA verification page
                            header('Location: verify_2fa.php');
                            exit();
                        } else {
                            echo "
                            <script>
                                alert('Failed to send verification code. Please try again.');
                                window.location.href = 'login.php';
                            </script>
                            ";
                            exit();
                        }
                    } else {
                        echo "
                        <script>
                            alert('System error. Please try again.');
                            window.location.href = 'login.php';
                        </script>
                        ";
                        exit();
                    }
                } else {
                    // 2FA disabled - Direct login
                    $_SESSION['login_attempts'] = 0;
                    unset($_SESSION['last_attempt_time']);
            
                    // Set session variables
                    $_SESSION['reg_id'] = $row['reg_id'];
                    $_SESSION['user_name'] = $row['firstName'];
                    $_SESSION['user_email'] = $email;

                    // Get the most recent req_id for this user from request_student_tbl
                    $getReqId = $conn->prepare("SELECT req_id FROM request_student_tbl WHERE reg_id = :reg_id ORDER BY req_id DESC LIMIT 1");
                    $getReqId->execute([':reg_id' => $row['reg_id']]);
                    $reqRow = $getReqId->fetch(PDO::FETCH_ASSOC);
                    
                    if ($reqRow) {
                        $_SESSION['req_id'] = $reqRow['req_id'];
                    } else {
                        $_SESSION['req_id'] = null;
                    }

                    header('Location: Main.php');
                    exit();
                }
            }
        }
    } catch(PDOException $e) {
        echo "
        <script>
            alert('Database error: " . addslashes($e->getMessage()) . "');
            window.location.href = 'login.php';
        </script>
        ";
        exit();
    }
}

$conn = null;
?>