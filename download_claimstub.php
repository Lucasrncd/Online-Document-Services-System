<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['reg_id'])) {
    header("Location: login.php");
    exit();
}

// Get req_id from URL
$req_id = isset($_GET['req_id']) ? intval($_GET['req_id']) : 0;

if ($req_id === 0) {
    die("Invalid request ID");
}

// Database connection
$conn = new mysqli("localhost", "root", "", "tupdb");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify that this request belongs to the logged-in user and payment is approved
$verify_query = "SELECT rs.reg_id, p.payment_confirmation 
                 FROM request_student_tbl rs
                 LEFT JOIN payment_info_tbl p ON rs.req_id = p.req_id
                 WHERE rs.req_id = ?";
$stmt = $conn->prepare($verify_query);
$stmt->bind_param("i", $req_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Security checks
if (!$row) {
    die("Request not found");
}

if ($row['reg_id'] != $_SESSION['reg_id']) {
    die("Unauthorized access");
}

if (strtolower($row['payment_confirmation']) !== 'approved') {
    die("Payment must be approved before downloading claim stub");
}

// Find the claim stub file
$claimStubPattern = __DIR__ . "/claimstubs/claimstub_" . $req_id . "_*.pdf";
$claimStubFiles = glob($claimStubPattern);

if (empty($claimStubFiles)) {
    die("Claim stub file not found");
}

$claimStubPath = $claimStubFiles[0];
$claimStubName = basename($claimStubPath);

// Force download
if (file_exists($claimStubPath)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $claimStubName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($claimStubPath));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read and output file
    readfile($claimStubPath);
    exit;
} else {
    die("File does not exist");
}
?>