<?php 
ob_clean(); // clear any previous output
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/fpdf186/fpdf.php';

// ✅ Connect to the correct database
$conn = new mysqli("localhost", "root", "", "cyberiondb");
if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $conn->connect_error]);
    exit;
}

// ✅ Logging function
function log_error($msg) {
    file_put_contents(__DIR__ . '/error_log.txt', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// ✅ Ensure folders exist
$uploadDir = __DIR__ . "/uploads/";
$pdfDir = __DIR__ . "/claimstubs/";
if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($pdfDir)) mkdir($pdfDir, 0777, true);

// ✅ Read POST data
$req_id = $_POST['req_id'] ?? '';
$receipt_number = $_POST['receipt_number'] ?? '';
$amount_paid = $_POST['amount_paid'] ?? '';
$date = $_POST['date'] ?? '';
$proof = $_FILES['proof_of_receipt'] ?? null;

// ✅ Validate inputs
if (empty($req_id) || empty($receipt_number) || empty($amount_paid) || empty($date) || !$proof) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// ✅ Save uploaded image
$fileName = time() . "_" . basename($proof["name"]);
$uploadPath = $uploadDir . $fileName;
if (!move_uploaded_file($proof["tmp_name"], $uploadPath)) {
    echo json_encode(["status" => "error", "message" => "Failed to upload proof image."]);
    exit;
}

// ✅ Save payment record in correct table
$stmt = $conn->prepare("INSERT INTO payment_info_tbl (req_id, receipt_number, amount_paid, date, proof_of_receipt) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("isiss", $req_id, $receipt_number, $amount_paid, $date, $fileName);
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Database insert failed: " . $stmt->error]);
        exit;
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Statement prepare failed: " . $conn->error]);
    exit;
}

// ✅ Generate claim stub PDF (without ₱ symbol to prevent encoding issues)
try {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont("Arial", "B", 20);
    $pdf->SetTextColor(3, 14, 79);
    $pdf->Image(__DIR__ . '/assets/logo.png', 10, 10, 30);
    $pdf->Cell(0, 10, 'Technological University of the Philippines', 0, 1, 'C');

    $pdf->SetFont("Arial", "", 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 8, "Claim Stub", 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont("Arial", "", 11);
    $pdf->Cell(60, 8, "Request ID:", 1);
    $pdf->Cell(130, 8, $req_id, 1, 1);

    $pdf->Cell(60, 8, "Receipt Number:", 1);
    $pdf->Cell(130, 8, $receipt_number, 1, 1);

    // ✅ FIXED: Remove peso symbol to avoid "â‚±" issue
    $pdf->Cell(60, 8, "Amount Paid:", 1);
    $pdf->Cell(130, 8, "PHP " . number_format($amount_paid, 2), 1, 1);

    $pdf->Cell(60, 8, "Date of Payment:", 1);
    $pdf->Cell(130, 8, $date, 1, 1);

    $pdf->Ln(10);
    $pdf->SetFont("Arial", "I", 10);
    $pdf->MultiCell(0, 8, "Please present this claim stub and your official receipt when claiming your requested documents.", 0, 'L');

    $fileNamePDF = "claimstub_" . $req_id . "_" . time() . ".pdf";
    $pdfPath = $pdfDir . $fileNamePDF;
    $pdf->Output("F", $pdfPath);

    echo json_encode([
        "status" => "success",
        "message" => "Payment record saved successfully!",
        "pdf_url" => "claimstubs/" . $fileNamePDF
    ]);
} catch (Exception $e) {
    log_error("PDF generation failed: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Failed to generate PDF."]);
}
?>
