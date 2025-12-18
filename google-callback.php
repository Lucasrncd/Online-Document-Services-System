<?php
session_start();
require_once 'google-config.php';

// Check if authorization code is present
if (!isset($_GET['code'])) {
    echo "<script>
        alert('Google authentication failed. Please try again.');
        window.location.href = 'login.php';
    </script>";
    exit();
}

// ✅ Check if this is an alumni registration flow
$isAlumniRegistration = isset($_GET['state']) && $_GET['state'] === 'alumni_registration';

$authCode = $_GET['code'];

try {
    // Exchange authorization code for access token
    $tokenData = [
        'code' => $authCode,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init(GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get access token from Google');
    }

    $tokenInfo = json_decode($response, true);
    
    if (!isset($tokenInfo['access_token'])) {
        throw new Exception('Access token not found in response');
    }

    $accessToken = $tokenInfo['access_token'];

    // Get user information from Google
    $ch = curl_init(GOOGLE_USERINFO_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $userInfoResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to get user information from Google');
    }

    $userInfo = json_decode($userInfoResponse, true);

    if (!isset($userInfo['email']) || !isset($userInfo['verified_email'])) {
        throw new Exception('Email not found or not verified');
    }

    // Verify that the email is verified by Google
    if (!$userInfo['verified_email']) {
        echo "<script>
            alert('Your Google email is not verified. Please verify your email with Google first.');
            window.location.href = 'login.php';
        </script>";
        exit();
    }

    $verifiedEmail = $userInfo['email'];
    $isTupEmail = str_ends_with(strtolower($verifiedEmail), '@tup.edu.ph');
    
    // ✅ Email validation based on registration type
    if ($isAlumniRegistration) {
        // Alumni registration: Should NOT use @tup.edu.ph emails
        if ($isTupEmail) {
            echo "<script>
                alert('TUP email addresses (@tup.edu.ph) should use \\'Register as Student\\' instead.\\n\\nPlease use the \\'Register as Student\\' button if you are a current TUP student, or use a personal email address if you are an alumni.');
                window.location.href = 'login.php';
            </script>";
            exit();
        }
    } else {
        // Student registration: MUST use @tup.edu.ph email
        if (!$isTupEmail) {
            echo "<script>
                alert('Only TUP email addresses (@tup.edu.ph) are allowed for student registration.\\n\\nYour email: " . addslashes($verifiedEmail) . "\\n\\nIf you are an alumni, please use the \\'Register as Alumni\\' button instead.');
                window.location.href = 'login.php';
            </script>";
            exit();
        }
    }
    
    $googleId = $userInfo['id'];
    $firstName = isset($userInfo['given_name']) ? $userInfo['given_name'] : '';
    $lastName = isset($userInfo['family_name']) ? $userInfo['family_name'] : '';

    // Database connection
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "tupdb";

    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ✅ Check registration_tbl for both alumni and students
    if ($isAlumniRegistration) {
        // ALUMNI REGISTRATION FLOW
        
        // Check if email already exists in registration_tbl (unified table)
        $stmt = $conn->prepare("SELECT reg_id, email, account_status, is_alumni FROM registration_tbl WHERE email = :email");
        $stmt->execute([':email' => $verifiedEmail]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            // Check if it's already an alumni
            if ($existingUser['is_alumni'] == 1) {
                $status = $existingUser['account_status'];
                if ($status === 'Pending') {
                    echo "<script>
                        alert('This email is already registered as Alumni with Pending status.\\nPlease wait for admin approval.');
                        window.location.href = 'login.php';
                    </script>";
                } elseif ($status === 'Approved') {
                    echo "<script>
                        alert('This email is already registered and approved.\\nPlease login using your password.');
                        window.location.href = 'login.php';
                    </script>";
                } else {
                    echo "<script>
                        alert('This email registration has been denied.\\nPlease contact the administrator.');
                        window.location.href = 'login.php';
                    </script>";
                }
            } else {
                // Registered as student
                echo "<script>
                    alert('This email is already registered as a student.\\nPlease login using your password or contact admin.');
                    window.location.href = 'login.php';
                </script>";
            }
            exit();
        }
        
        // ✅ FIXED: Clear student flag before setting alumni flag
        unset($_SESSION['is_student_registration']);
        
        // New alumni - store verified email in session and redirect to alumni registration
        $_SESSION['google_verified_email'] = $verifiedEmail;
        $_SESSION['google_first_name'] = $firstName;
        $_SESSION['google_last_name'] = $lastName;
        $_SESSION['google_id'] = $googleId;
        $_SESSION['is_alumni_registration'] = true;
        
        header('Location: registration_alumni.php');
        exit();
        
    } else {
        // STUDENT REGISTRATION FLOW
        
        // Check if email already exists in registration_tbl
        $stmt = $conn->prepare("SELECT reg_id, email, is_alumni FROM registration_tbl WHERE email = :email");
        $stmt->execute([':email' => $verifiedEmail]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingUser) {
            if ($existingUser['is_alumni'] == 1) {
                echo "<script>
                    alert('This email is already registered as Alumni.\\nPlease contact admin if you need assistance.');
                    window.location.href = 'login.php';
                </script>";
            } else {
                echo "<script>
                    alert('This TUP email address is already registered. Please login using your password.');
                    window.location.href = 'login.php';
                </script>";
            }
            exit();
        }
        
        // ✅ FIXED: Clear alumni flag before setting student flag
        unset($_SESSION['is_alumni_registration']);
        
        // New student - store verified email in session and redirect to student registration
        $_SESSION['google_verified_email'] = $verifiedEmail;
        $_SESSION['google_first_name'] = $firstName;
        $_SESSION['google_last_name'] = $lastName;
        $_SESSION['google_id'] = $googleId;
        $_SESSION['is_student_registration'] = true;
        
        header('Location: registration.php');
        exit();
    }

} catch (Exception $e) {
    error_log('Google OAuth Error: ' . $e->getMessage());
    echo "<script>
        alert('An error occurred during Google authentication. Please try again or use regular registration.');
        window.location.href = 'login.php';
    </script>";
    exit();
}
?>