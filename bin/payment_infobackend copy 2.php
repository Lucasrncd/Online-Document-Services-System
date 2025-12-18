<?php
session_start();
header("Content-Type: application/json");
require 'config.php'; // 🔹 Your database connection file (adjust path if needed)

// ------------------ SESSION VALIDATION ------------------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['req_id'])) {
    echo json_encode(["status" => "error", "message" => "Session expired. Please log in again."]);
    exit();
}

$user_id = $_SESSION['user_id'];
$req_id  = $_SESSION['req_id'];

// ------------------ FORM VALIDATION ------------------
if (
    !isset($_POST['receipt_number']) ||
    !isset($_POST['amount_paid']) ||
    !isset($_POST['date']) ||
    !isset($_FILES['proof_of_receipt'])
) {
    echo json_encode(["status" => "error", "message" => "Incomplete form data."]);
    exit();
}

$receipt_number = $_POST['receipt_number'];
$amount_paid    = $_POST['amount_paid'];
$date           = $_POST['date'];

// ------------------ FILE UPLOAD ------------------
$uploadDir = "uploads/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$file = $_FILES['proof_of_receipt'];
$fileName = time() . "_" . basename($file['name']);
$targetFile = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
    echo json_encode(["status" => "error", "message" => "Failed to upload proof of receipt."]);
    exit();
}

// ------------------ DATABASE INSERT ------------------
$stmt = $conn->prepare("INSERT INTO payments (req_id, user_id, receipt_number, amount_paid, date, proof_of_receipt) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iissss", $req_id, $user_id, $receipt_number, $amount_paid, $date, $targetFile);

if (!$stmt->execute()) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit();
}

// ------------------ PDF GENERATION ------------------
$pdfDir = "claim_stubs/";
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0777, true);
}

$pdfPath = $pdfDir . "claimstub_" . $req_id . ".pdf";

// 🔹 Generate PDF using FPDF (make sure fpdf.php exists in fpdf186 folder)
require('uploads/claim_stubs/fpdf186/fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'CLAIM STUB', 0, 1, 'C');
$pdf->Ln(8);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Reference Number: " . $req_id, 0, 1);
$pdf->Cell(0, 10, "Receipt Reference Number: " . $receipt_number, 0, 1);
$pdf->Cell(0, 10, "Amount Paid: ₱" . $amount_paid, 0, 1);
$pdf->Cell(0, 10, "Date: " . $date, 0, 1);
$pdf->Ln(10);
$pdf->Cell(0, 10, "Please present this claim stub and official receipt when claiming your document.", 0, 1);
$pdf->Output('F', $pdfPath);

// ------------------ JSON RESPONSE ------------------
echo json_encode([
    "status" => "success",
    "pdf_url" => $pdfPath
]);

$stmt->close();
$conn->close();
?>
