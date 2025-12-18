<?php

session_start();

// Verify the submitted req_id matches the session req_id
if (!isset($_SESSION['req_id']) || !isset($_POST['req_id']) || $_POST['req_id'] != $_SESSION['req_id']) {
    echo json_encode(["status" => "error", "message" => "Unauthorized request. Please log in again."]);
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cyberiondb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $req_id = $_POST['req_id'] ?? NULL;
    $receipt_number = $_POST['receipt_number'] ?? NULL;
    $amount_paid = $_POST['amount_paid'] ?? NULL;
    $date = $_POST['date'] ?? NULL;

    // Folder for uploads (make sure this folder exists)
    $uploadDir = "C:/xampp/htdocs/REVISEDD/uploads/";

    // Make sure the uploads folder exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Default: no file
    $proof_of_receipt_path = NULL;

    // Handle file upload
    if (isset($_FILES['proof_of_receipt']) && $_FILES['proof_of_receipt']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['proof_of_receipt']['tmp_name'];
        $fileName = basename($_FILES['proof_of_receipt']['name']);
        $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

        // Rename the file to avoid conflicts
        $newFileName = "receipt_" . time() . "_" . uniqid() . "." . $fileExtension;

        // Final destination path
        $destination = $uploadDir . $newFileName;

        // Move uploaded file
        if (move_uploaded_file($fileTmpPath, $destination)) {
            // Store relative path (for easy access later)
            $proof_of_receipt_path = "uploads/" . $newFileName;
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to move uploaded file."]);
            exit;
        }
    }

    // --- Prepare SQL ---
    $stmt = $conn->prepare("
        INSERT INTO payment_info_tbl (req_id, receipt_number, amount_paid, date, proof_of_receipt)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("isiss", $req_id, $receipt_number, $amount_paid, $date, $proof_of_receipt_path);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Payment record saved successfully!"]);
    } else {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
    }

    $stmt->close();
}
$conn->close();
?>