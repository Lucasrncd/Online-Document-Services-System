<?php
session_start();

// Check if user came from Google OAuth and is alumni registration
if (!isset($_SESSION['google_verified_email']) || !isset($_SESSION['is_alumni_registration'])) {
    header('Location: login.php');
    exit();
}

// Check if form was submitted via POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: registration_alumni.php');
    exit();
}

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "tupdb";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sanitize and retrieve form data
$lastName = trim($conn->real_escape_string($_POST['lastName']));
$firstName = trim($conn->real_escape_string($_POST['firstName']));
$middleName = trim($conn->real_escape_string($_POST['middleName']));
$extName = trim($conn->real_escape_string($_POST['extName']));
$contactNumber = trim($conn->real_escape_string($_POST['contactNumber']));
$address = trim($conn->real_escape_string($_POST['address']));
$yearGraduated = trim($conn->real_escape_string($_POST['yearGraduated']));
$studentNumber = trim($conn->real_escape_string($_POST['studentNumber']));
$college = trim($conn->real_escape_string($_POST['college']));
$course = trim($conn->real_escape_string($_POST['course']));
$email = $_SESSION['google_verified_email']; // Use verified email from session
$password = $_POST['password'];

// Validate required fields
if (empty($lastName) || empty($firstName) || empty($contactNumber) || 
    empty($address) || empty($yearGraduated) || empty($studentNumber) || 
    empty($college) || empty($course) || empty($email) || empty($password)) {
    $_SESSION['error_message'] = "All required fields must be filled out.";
    header('Location: registration_alumni.php');
    exit();
}

// Validate contact number (numeric only)
if (!preg_match('/^[0-9]+$/', $contactNumber)) {
    $_SESSION['error_message'] = "Contact number must contain only numbers.";
    header('Location: registration_alumni.php');
    exit();
}

// Validate year graduated (4 digits)
if (!preg_match('/^[0-9]{4}$/', $yearGraduated)) {
    $_SESSION['error_message'] = "Year graduated must be a 4-digit year.";
    header('Location: registration_alumni.php');
    exit();
}

// Validate password strength
if (strlen($password) < 12 || 
    !preg_match('/[A-Z]/', $password) || 
    !preg_match('/[a-z]/', $password) || 
    !preg_match('/[0-9]/', $password) || 
    !preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
    $_SESSION['error_message'] = "Password does not meet security requirements.";
    header('Location: registration_alumni.php');
    exit();
}

// Hash the password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// Check if email already exists in registration_tbl
$checkEmailQuery = "SELECT reg_id FROM registration_tbl WHERE email = ?";
$stmt = $conn->prepare($checkEmailQuery);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error_message'] = "This email is already registered.";
    $stmt->close();
    $conn->close();
    header('Location: registration_alumni.php');
    exit();
}
$stmt->close();

// Check if student number already exists
$checkStudentQuery = "SELECT reg_id FROM registration_tbl WHERE studentNumber = ?";
$stmt = $conn->prepare($checkStudentQuery);
$stmt->bind_param("s", $studentNumber);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $_SESSION['error_message'] = "This student number is already registered.";
    $stmt->close();
    $conn->close();
    header('Location: registration_alumni.php');
    exit();
}
$stmt->close();

// Insert alumni data into registration_tbl
// For alumni: account_status = 'Pending', is_alumni = 1
$insertQuery = "INSERT INTO registration_tbl 
                (lastName, firstName, middleName, extName, contactNumber, 
                 address, email, password, two_factor_enabled, confirmPassword, 
                 yearGraduated, studentNumber, college, course, account_status, is_alumni) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 'Pending', 1)";

$stmt = $conn->prepare($insertQuery);
$stmt->bind_param("sssssssssssss", 
    $lastName, $firstName, $middleName, $extName, $contactNumber, 
    $address, $email, $hashedPassword, $hashedPassword,
    $yearGraduated, $studentNumber, $college, $course
);

if ($stmt->execute()) {
    // Clear Google verification session data
    unset($_SESSION['google_verified_email']);
    unset($_SESSION['google_first_name']);
    unset($_SESSION['google_last_name']);
    unset($_SESSION['google_id']);
    unset($_SESSION['is_alumni_registration']);
    
    // Set success flag for popup
    $_SESSION['alumni_registration_success'] = true;
    
    $stmt->close();
    $conn->close();
    
    // Redirect to login with success message
    header('Location: login.php?alumni_registered=1');
    exit();
} else {
    $_SESSION['error_message'] = "Registration failed. Please try again. Error: " . $stmt->error;
    $stmt->close();
    $conn->close();
    header('Location: registration_alumni.php');
    exit();
}
?>