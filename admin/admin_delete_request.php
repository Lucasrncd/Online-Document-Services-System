<?php
header('Content-Type: application/json');
session_start();

// Database Connection
$conn = new mysqli('localhost', 'root', '', 'tupdb');
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed."]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $req_id = $input['req_id'] ?? null;

    if (!$req_id) {
        echo json_encode(["status" => "error", "message" => "Missing Request ID."]);
        exit;
    }

    $req_id = (int)$req_id;
    $conn->begin_transaction();

    try {
        // delete from linked tables first (due to the foreign key)
        $tables_to_delete = [
            'document_request_tbl',
            'payment_info_tbl',
            'cancel_req_tbl'
        ];

        foreach ($tables_to_delete as $table) {
            $stmt = $conn->prepare("DELETE FROM {$table} WHERE req_id = ?");
            if (!$stmt) throw new Exception("Prepare failed for {$table}: " . $conn->error);
            $stmt->bind_param("i", $req_id);
            $stmt->execute();
            $stmt->close();
        }

        // delete the main request
        $stmt = $conn->prepare("DELETE FROM request_student_tbl WHERE req_id = ?");
        if (!$stmt) throw new Exception("Prepare failed for main table: " . $conn->error);
        $stmt->bind_param("i", $req_id);
        $stmt->execute();
        $rows_affected = $stmt->affected_rows;
        $stmt->close();

        if ($rows_affected === 0) {
            throw new Exception("Request ID {$req_id} not found in main table.");
        }

        $conn->commit();
        echo json_encode(["status" => "success", "message" => "Request ID {$req_id} and all related data deleted successfully."]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Deletion failed. Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}

$conn->close();
?>