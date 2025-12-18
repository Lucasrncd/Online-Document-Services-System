<?php
ob_clean();
header('Content-Type: application/json; charset=utf-8');
session_start();

// Database Connection
$conn = new mysqli('localhost', 'root', '', 'tupdb');
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed']);
    exit;
}

try {
    // 1. Total Requests 
    $result = $conn->query("SELECT COUNT(*) as count FROM request_student_tbl");
    $total = $result->fetch_assoc()['count'];

    // 2. Paid Requests 
    $result = $conn->query("SELECT COUNT(*) as count FROM request_student_tbl WHERE payment_status = 'Paid'");
    $paid = $result->fetch_assoc()['count'];

    // 3. Claimed Requests 
    $result = $conn->query("SELECT COUNT(*) as count FROM request_student_tbl WHERE progress = 'Claimed'");
    $claimed = $result->fetch_assoc()['count'];

    // 4. Cancelled Requests 
    $result = $conn->query("SELECT COUNT(*) as count FROM request_student_tbl WHERE progress = 'Cancelled'");
    $cancelled = $result->fetch_assoc()['count'];

    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'total' => $total,
            'paid' => $paid,
            'claimed' => $claimed,
            'cancelled' => $cancelled
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>