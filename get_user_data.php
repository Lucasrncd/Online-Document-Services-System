<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['reg_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'tupdb';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$conn->set_charset("utf8mb4");

// Get user data from registration_tbl including academic information
$reg_id = $_SESSION['reg_id'];
$sql = "SELECT firstName, lastName, email, contactNumber, address, studentNumber, college, course, yearGraduated FROM registration_tbl WHERE reg_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reg_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'firstName' => $user['firstName'],
        'lastName' => $user['lastName'],
        'email' => $user['email'],
        'contactNumber' => $user['contactNumber'],
        'address' => $user['address'],
        'studentNumber' => $user['studentNumber'],
        'college' => $user['college'],
        'course' => $user['course'],
        'yearGraduated' => $user['yearGraduated']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$stmt->close();
$conn->close();
?>