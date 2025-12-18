<?php
ob_clean(); // clear any previous output
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/fpdf186/fpdf.php';

// ✅ Connect to the database
$conn = new mysqli("localhost", "root", "", "tupdb");
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

// Get logged-in user's reg_id
$logged_in_reg_id = $_SESSION['reg_id'] ?? null;

if (!$logged_in_reg_id) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

// ✅ Validate inputs
if (empty($req_id) || empty($receipt_number) || empty($amount_paid) || empty($date) || !$proof) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// 🔐 SECURITY CHECK: Verify that the request belongs to the logged-in user
$check_owner_query = "SELECT `reg_id`, `payment_status` FROM `request_student_tbl` WHERE `req_id` = ?";
$stmt_owner = $conn->prepare($check_owner_query);

if (!$stmt_owner) {
    echo json_encode(["status" => "error", "message" => "Database error occurred."]);
    exit;
}

$stmt_owner->bind_param("i", $req_id);
$stmt_owner->execute();
$result_owner = $stmt_owner->get_result();
$row_owner = $result_owner->fetch_assoc();
$stmt_owner->close();

if (!$row_owner) {
    // Request ID not found
    echo json_encode(["status" => "error", "message" => "Request not found."]);
    exit;
}

// Check if the request belongs to the logged-in user
if ($row_owner['reg_id'] != $logged_in_reg_id) {
    echo json_encode([
        "status" => "error", 
        "type" => "unauthorized",
        "message" => "You cannot make payment for a request that doesn't belong to your account."
    ]);
    exit;
}

// Check if payment has already been made
if ($row_owner['payment_status'] === 'Paid') {
    echo json_encode([
        "status" => "error",
        "message" => "Payment has already been made for this request."
    ]);
    exit;
}

// 🔐 SECURE IMAGE VALIDATION
$allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

// 🚫 Block dangerous extensions (double extensions, PHP, etc.)
$originalName = $proof["name"];
if (preg_match("/\.(php|php\d?|phtml|exe|sh|js|html|htm)$/i", $originalName)) {
    echo json_encode(["status" => "error", "message" => "Invalid file. Executable extensions are not allowed."]);
    exit;
}

// 📌 Validate real image signature
$imgInfo = @getimagesize($proof["tmp_name"]);
if ($imgInfo === false || !in_array($imgInfo[2], $allowedTypes)) {
    echo json_encode(["status" => "error", "message" => "Uploaded file is not a valid image."]);
    exit;
}

// 🛑 Optional: Max file size 5MB
if ($proof["size"] > (5 * 1024 * 1024)) {
    echo json_encode(["status" => "error", "message" => "Image file too large. Max 5MB allowed."]);
    exit;
}

// 🆔 Generate secure random filename
$secureFileName = time() . "_" . bin2hex(random_bytes(8));
if ($imgInfo[2] == IMAGETYPE_JPEG) $secureFileName .= ".jpg";
elseif ($imgInfo[2] == IMAGETYPE_PNG) $secureFileName .= ".png";

$uploadPath = $uploadDir . $secureFileName;

// 💾 Move uploaded file securely
if (!move_uploaded_file($proof["tmp_name"], $uploadPath)) {
    echo json_encode(["status" => "error", "message" => "Failed to upload proof image."]);
    exit;
}

// ✅ Validate payment amount against document_request_tbl
$amount_check = $conn->prepare("SELECT SUM(amount) as total_amount FROM document_request_tbl WHERE req_id = ?");
$amount_check->bind_param("i", $req_id);
$amount_check->execute();
$amount_result = $amount_check->get_result();
$amount_row = $amount_result->fetch_assoc();
$expected_amount = $amount_row['total_amount'] ?? 0;
$amount_check->close();

// ✅ Check for insufficient payment
if (floatval($amount_paid) < floatval($expected_amount)) {
    echo json_encode([
        "status" => "error",
        "message" => "Insufficient payment! Required: ₱" . number_format($expected_amount, 2) . ", you paid ₱" . number_format($amount_paid, 2)
    ]);
    exit;
}

// ✅ Check for overpayment (only if not confirmed)
$confirm_overpayment = $_POST['confirm_overpayment'] ?? '';
if (floatval($amount_paid) > floatval($expected_amount) && $confirm_overpayment !== '1') {
    $overpayment = floatval($amount_paid) - floatval($expected_amount);
    echo json_encode([
        "status" => "warning",
        "type" => "overpayment",
        "message" => "You have paid more than the required amount!",
        "required_amount" => number_format($expected_amount, 2),
        "paid_amount" => number_format($amount_paid, 2),
        "overpayment" => number_format($overpayment, 2)
    ]);
    exit;
}

// ✅ Save payment record
$stmt = $conn->prepare("INSERT INTO payment_info_tbl (req_id, receipt_number, amount_paid, date, proof_of_receipt) VALUES (?, ?, ?, ?, ?)");
if ($stmt) {
    $stmt->bind_param("isiss", $req_id, $receipt_number, $amount_paid, $date, $secureFileName);
    if ($stmt->execute()) {
        $stmt->close();

        // ✅ Update payment_status in request_student_tbl
        $updateStatus = $conn->prepare("UPDATE request_student_tbl SET payment_status = 'Paid' WHERE req_id = ?");
        $updateStatus->bind_param("i", $req_id);
        $updateStatus->execute();
        $updateStatus->close();
    } else {
        echo json_encode(["status" => "error", "message" => "Database insert failed: " . $stmt->error]);
        exit;
    }
} else {
    echo json_encode(["status" => "error", "message" => "Statement prepare failed: " . $conn->error]);
    exit;
}

// ✅ Fetch student info
$student_query = $conn->prepare("
    SELECT 
        `full-name`, 
        `student-number`, 
        course, 
        college, 
        purpose, 
        `contact-number`, 
        address, 
        email 
    FROM request_student_tbl 
    WHERE req_id = ?
");
$student_query->bind_param("i", $req_id);
$student_query->execute();
$student_result = $student_query->get_result();
$student = $student_result->fetch_assoc();
$student_query->close();

// ✅ Fetch requested documents
$docs_query = $conn->prepare("
    SELECT dl.description, dr.copies, dr.amount 
    FROM document_request_tbl dr
    INNER JOIN document_list_tbl dl ON dr.document_id = dl.document_id
    WHERE dr.req_id = ?
");
$docs_query->bind_param("i", $req_id);
$docs_query->execute();
$docs_result = $docs_query->get_result();
$documents = [];
while ($row = $docs_result->fetch_assoc()) {
    $documents[] = $row;
}
$docs_query->close();

// ✅ Generate claim stub PDF
try {
    $pdf = new FPDF();
    $pdf->AddPage();

    $pdf->Image(__DIR__ . '/assets/logo.png', 20, 12, 20);
    $pdf->SetFont("Arial", "B", 20);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(45, 15);
    $pdf->Cell(0, 10, 'Technological University of the Philippines', 0, 1, 'L');

    $pdf->SetXY(45, 24); 
    $pdf->SetFont("Arial", "", 11);
    $pdf->Cell(0, 8, "Ayala Blvd., corner of San Marcelino St., Ermita, Manila.", 0, 1, 'L');

    $pdf->SetFont("Arial", "", 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 8, "Claim Stub", 0, 1, 'C');
    $pdf->Ln(5);

    // Payment Info
    $pdf->SetFont("Arial", "", 11);
    $pdf->Cell(60, 8, "Reference Number:", 1);
    $pdf->Cell(130, 8, $req_id, 1, 1);
    $pdf->Cell(60, 8, "E-Receipt Number:", 1);
    $pdf->Cell(130, 8, $receipt_number, 1, 1);
    $pdf->Cell(60, 8, "Amount Paid:", 1);
    $pdf->Cell(130, 8, "PHP " . number_format($amount_paid, 2), 1, 1);
    $pdf->Cell(60, 8, "Date of Payment:", 1);
    $pdf->Cell(130, 8, $date, 1, 1);
    $pdf->Ln(10);

    // Student Info
    if ($student) {
        $pdf->SetFont("Arial", "B", 12);
        $pdf->Cell(0, 8, "Student Information", 0, 1);
        $pdf->SetFont("Arial", "", 11);

        $fields = ['Full Name'=>'full-name','Student Number'=>'student-number','Course'=>'course','College'=>'college','Purpose'=>'purpose','Contact Number'=>'contact-number','Email'=>'email','Address'=>'address'];
        foreach($fields as $label=>$field) {
            if($field=='address'){
                $pdf->Cell(60,8,$label.":",1);
                $pdf->MultiCell(130,8,$student[$field],1);
            }else{
                $pdf->Cell(60,8,$label.":",1);
                $pdf->Cell(130,8,$student[$field],1,1);
            }
        }
        $pdf->Ln(10);
    }

    // Documents
    $pdf->SetFont("Arial", "B", 12);
    $pdf->Cell(0, 8, "Requested Documents", 0, 1);
    $pdf->SetFont("Arial", "B", 11);
    $pdf->Cell(100, 8, "Document", 1);
    $pdf->Cell(40, 8, "Copies", 1);
    $pdf->Cell(50, 8, "Amount", 1, 1);
    $pdf->SetFont("Arial", "", 11);

    if (!empty($documents)) {
        foreach ($documents as $doc) {
            $pdf->Cell(100, 8, $doc['description'], 1);
            $pdf->Cell(40, 8, $doc['copies'], 1);
            $pdf->Cell(50, 8, "PHP " . number_format($doc['amount'], 2), 1, 1);
        }
    } else {
        $pdf->Cell(190, 8, "No documents found for this request.", 1, 1, 'C');
    }

    $pdf->Ln(10);
    $pdf->SetFont("Arial", "I", 10);
    $pdf->MultiCell(0, 8, "Please present this claim stub and your official receipt when claiming your requested documents.", 0, 'L');

// ** START: Two-Column Signature Block (TUP Registrar Left, Requestor Right) **
    
    // Check if we need a new page
    $yPosition = $pdf->GetY() + 10;
    if ($yPosition > 230) { 
        $pdf->AddPage();
        $yPosition = 30; // Reset Y position on the new page
    }
    
    $pdf->SetY($yPosition);
    $pdf->SetFont("Arial", "B", 11);
    
    $blockWidth = 80; // Width of each signature block
    $spacing = 10; // Space between the two blocks
    $leftX = 20; // Left block X position
    $rightX = $leftX + $blockWidth + $spacing; // Right block X position
    
    // LEFT COLUMN: TUP REGISTRAR with signature image
    $imgWidth = $blockWidth * 0.8; // Make image 80% of block width
    $imgHeight = ($imgWidth * 864) / 1920; // Maintain aspect ratio
    $imgX = $leftX + (($blockWidth - $imgWidth) / 2); // Center image in block
    $imgY = $pdf->GetY();
    
    // Add registrar signature image
    $pdf->Image(__DIR__ . '/assets/registrar_signature.jpg', $imgX, $imgY, $imgWidth, $imgHeight);
    
    // Draw line and text for TUP REGISTRAR
    $pdf->SetY($imgY + $imgHeight);
    $pdf->SetX($leftX);
    $pdf->Cell($blockWidth, 0, "", "T", 0, 'C'); // Draw the line (no line break)
    
    // RIGHT COLUMN: SIGNATURE OVER PRINTED NAME (at same Y position)
    $pdf->SetXY($rightX, $imgY + $imgHeight);
    $pdf->Cell($blockWidth, 0, "", "T", 1, 'C'); // Draw the line (with line break)
    
    // Text labels on next line
    $pdf->SetX($leftX);
    $pdf->Cell($blockWidth, 7, 'TUP REGISTRAR', 0, 0, 'C');
    $pdf->SetX($rightX);
    $pdf->Cell($blockWidth, 7, 'SIGNATURE OVER PRINTED NAME', 0, 1, 'C');
    
    // ** END: Two-Column Signature Block **

    $pdf->Ln(10); // Add space after the blocks

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