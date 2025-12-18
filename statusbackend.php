<?php
session_start();

// ⚠ CHECK IF USER IS LOGGED-IN (must have reg_id stored)
if (!isset($_SESSION['reg_id'])) {
    header("Location: login.php");
    exit();
}

$logged_reg_id = $_SESSION['reg_id'];

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tupdb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ref_num'])) {

    $ref_num = $conn->real_escape_string($_POST['ref_num']);

    /*
     🔎 STEP 1: Check if reference number exists regardless of owner
     */
    $checkExist = $conn->prepare("SELECT reg_id FROM request_student_tbl WHERE req_id = ?");
    $checkExist->bind_param("i", $ref_num);
    $checkExist->execute();
    $existsResult = $checkExist->get_result();

    // ❌ Completely unknown reference number
    if ($existsResult->num_rows == 0) {
        header("Location: status.html?search=not_found");
        exit();
    }

    $record = $existsResult->fetch_assoc();
    $owner_reg_id = $record['reg_id'];

    /*
     🔐 STEP 2: Check if it belongs to logged-in user
     */
    if ($owner_reg_id != $logged_reg_id) {
        // ❌ Exists but belongs to someone else
        header("Location: status.html?search=unauthorized");
        exit();
    }

    /*
     👍 STEP 3: Fetch full data now that user is authorized
     */
    $sql = "SELECT req_id, progress, claim_date, payment_status, request_date
            FROM request_student_tbl
            WHERE req_id = ? AND reg_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $ref_num, $logged_reg_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Guaranteed to have result
    $row = $result->fetch_assoc();
    $_SESSION['request_data'] = $row;

    header("Location: status.html?search=success");
    exit();
}

$conn->close();
?>
