<?php
session_start();

// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'tupdb'; 

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form values and sanitize input
$lastName = trim($_POST['lastName']);
$firstName = trim($_POST['firstName']);
$middleName = trim($_POST['middleName']);
$extName = trim($_POST['extName']);
$contactNumber = trim($_POST['contactNumber']);
$address = trim($_POST['address']);
$email = trim($_POST['email']);
$password = trim($_POST['password']);
$confirmPassword = trim($_POST['confirmPassword']);
$studentNumber = trim($_POST['studentNumber']);
$yearGraduated = trim($_POST['yearGraduated']);
$college = trim($_POST['college']);
$course = trim($_POST['course']);
$isGoogleRegistration = isset($_POST['is_google_registration']) && $_POST['is_google_registration'] === '1';

// Validate password match
if ($password !== $confirmPassword) {
    echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
    exit();
}

// Comprehensive password validation
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = "Password must be at least 12 characters long";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter (A-Z)";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter (a-z)";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number (0-9)";
    }
    
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $errors[] = "Password must contain at least one special character (!@#$%^&*...)";
    }
    
    return $errors;
}

// Validate password strength
$passwordErrors = validatePasswordStrength($password);
if (!empty($passwordErrors)) {
    $errorMessage = "Password does not meet requirements:\\n" . implode("\\n", $passwordErrors);
    echo "<script>alert('$errorMessage'); window.history.back();</script>";
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "<script>alert('Invalid email format!'); window.history.back();</script>";
    exit();
}

// Validate email length (minimum 6 characters)
if (strlen($email) < 6) {
    echo "<script>alert('Email must be at least 6 characters long!'); window.history.back();</script>";
    exit();
}

// Validate year graduated (4 digits)
if (!preg_match('/^[0-9]{4}$/', $yearGraduated)) {
    echo "<script>alert('Year graduated must be a 4-digit year!'); window.history.back();</script>";
    exit();
}

// If this is a Google registration, verify the session email matches
if ($isGoogleRegistration) {
    if (!isset($_SESSION['google_verified_email']) || $_SESSION['google_verified_email'] !== $email) {
        echo "<script>
            alert('Email verification failed. Please try again.');
            window.location.href = 'login.php';
        </script>";
        exit();
    }
}

// Check if email already exists
$stmt = $conn->prepare("SELECT email FROM registration_tbl WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "<script>alert('Email already registered!'); window.history.back();</script>";
    exit();
}
$stmt->close();

// Check if student number already exists
$stmt = $conn->prepare("SELECT studentNumber FROM registration_tbl WHERE studentNumber = ?");
$stmt->bind_param("s", $studentNumber);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "<script>alert('Student number already registered!'); window.history.back();</script>";
    exit();
}
$stmt->close();

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert STUDENT data with academic information
$stmt = $conn->prepare("INSERT INTO registration_tbl 
    (lastName, firstName, middleName, extName, contactNumber, address, email, password, 
     two_factor_enabled, confirmPassword, yearGraduated, studentNumber, college, course, account_status, is_alumni) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NULL, ?, ?, ?, ?, NULL, 0)");

$stmt->bind_param("ssssssssssss", 
    $lastName, $firstName, $middleName, $extName, 
    $contactNumber, $address, $email, $hashedPassword,
    $yearGraduated, $studentNumber, $college, $course
);

if ($stmt->execute()) {
    // Clear Google OAuth session data after successful registration
    if ($isGoogleRegistration) {
        unset($_SESSION['google_verified_email']);
        unset($_SESSION['google_first_name']);
        unset($_SESSION['google_last_name']);
        unset($_SESSION['google_id']);
        unset($_SESSION['is_student_registration']);
    }
    
    echo "<script>
        alert('Registration Successful! You can now login with your email and password.');
        window.location.href='login.php';
    </script>";
} else {
    echo "<script>alert('Error occurred during registration: " . $stmt->error . "'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>