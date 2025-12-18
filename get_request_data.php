<?php
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['request_data'])) {
    echo json_encode([
        'success' => true,
        'data' => $_SESSION['request_data']
    ]);
    // Clear session data after reading
    unset($_SESSION['request_data']);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No data found'
    ]);
}
?>