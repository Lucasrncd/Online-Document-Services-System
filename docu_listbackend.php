<?php
session_start();

// ============================================
// DATABASE CONNECTION
// ============================================
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "tupdb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');

// ============================================
// BACKEND PROCESSING
// ============================================

if (!isset($_SESSION['req_id'])) {
    echo "<script>
            alert('Please fill out the student request form first.');
            window.location.href = 'request_form.html';
          </script>";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: docu_des.php');
    exit();
}

$req_id = $_SESSION['req_id'];
$documents = isset($_POST['documents']) ? $_POST['documents'] : [];
$submitted_total = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0;

if (empty($documents)) {
    echo "<script>
            alert('Please select at least one document.');
            window.history.back();
          </script>";
    exit();
}

try {
    $conn->begin_transaction();
    
    $stmt = $conn->prepare("SELECT req_id, `full-name`, `student-number` FROM request_student_tbl WHERE req_id = ?");
    $stmt->bind_param("i", $req_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid request. Please start from the beginning.");
    }
    
    $student_info = $result->fetch_assoc();
    $stmt->close();
    
    $valid_documents = [];
    $calculated_total = 0;
    
    foreach ($documents as $doc_id => $doc_data) {
        if (isset($doc_data['selected']) && $doc_data['selected'] == '1') {
            $copies = isset($doc_data['copies']) ? intval($doc_data['copies']) : 0;
            
            if ($copies < 1 || $copies > 2) {
                continue;
            }
            
            $stmt = $conn->prepare("SELECT document_id, description, amount FROM document_list_tbl WHERE document_id = ?");
            $stmt->bind_param("i", $doc_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $doc = $result->fetch_assoc();
                $price = floatval($doc['amount']);
                $subtotal = $price * $copies;
                
                $valid_documents[] = [
                    'document_id' => $doc['document_id'],
                    'description' => $doc['description'],
                    'copies' => $copies,
                    'price' => $price,
                    'subtotal' => $subtotal
                ];
                
                $calculated_total += $subtotal;
            }
            $stmt->close();
        }
    }
    
    if (empty($valid_documents)) {
        throw new Exception("No valid documents selected.");
    }
    
    if (abs($calculated_total - $submitted_total) > 0.01) {
        throw new Exception("Total amount mismatch. Please try again.");
    }
    
    $stmt = $conn->prepare("INSERT INTO document_request_tbl (req_id, document_id, copies, amount) VALUES (?, ?, ?, ?)");
    
    foreach ($valid_documents as $doc) {
        $amount_int = intval($doc['subtotal']);
        
        $stmt->bind_param("iiii", 
            $req_id, 
            $doc['document_id'], 
            $doc['copies'], 
            $amount_int
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Error adding document item: " . $stmt->error);
        }
    }
    $stmt->close();
    
    $conn->commit();

    // ============================================
    // REFERENCE NUMBER DISPLAY WITH MODAL
    // ============================================
    $reference_no = $req_id;

    $_SESSION['reference_no'] = $reference_no;
    $_SESSION['total_amount'] = $calculated_total;
    $_SESSION['requested_documents'] = $valid_documents;
    $_SESSION['student_info'] = $student_info;

    // Show success modal with reference number
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Request Submitted</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Poppins', sans-serif;
                background-color: #550000;
            }

            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.6);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }

            .modal-content {
                background-color: white;
                border-radius: 16px;
                padding: 40px;
                max-width: 500px;
                width: 90%;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                text-align: center;
                animation: slideDown 0.3s ease-out;
            }

            @keyframes slideDown {
                from {
                    transform: translateY(-50px);
                    opacity: 0;
                }
                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .success-icon {
                width: 80px;
                height: 80px;
                margin: 0 auto 20px;
                background-color: #4CAF50;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .success-icon::after {
                content: "✓";
                color: white;
                font-size: 50px;
                font-weight: bold;
            }

            .modal-title {
                color: #4CAF50;
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 20px;
            }

            .modal-message {
                color: #333;
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 25px;
            }

            .reference-box {
                background-color: #f5f5f5;
                border: 2px solid #a12f2f;
                border-radius: 10px;
                padding: 20px;
                margin: 20px 0;
            }

            .reference-label {
                color: #666;
                font-size: 14px;
                margin-bottom: 8px;
            }

            .reference-number {
                color: #a12f2f;
                font-size: 32px;
                font-weight: 700;
                letter-spacing: 2px;
            }

            .reminder-box {
                background-color: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
                text-align: left;
            }

            .reminder-title {
                color: #856404;
                font-weight: 600;
                font-size: 16px;
                margin-bottom: 10px;
            }

            .reminder-text {
                color: #856404;
                font-size: 14px;
                line-height: 1.6;
            }

            .modal-button {
                background-color: #a12f2f;
                color: white;
                border: none;
                padding: 14px 40px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 8px;
                cursor: pointer;
                transition: background-color 0.3s ease;
                margin-top: 10px;
            }

            .modal-button:hover {
                background-color: #6e0101;
            }
        </style>
    </head>
    <body>
        <div class="modal-overlay" id="successModal">
            <div class="modal-content">
                <div class="success-icon"></div>
                <h2 class="modal-title">Request Submitted Successfully!</h2>
                
                <div class="reference-box">
                    <div class="reference-label">Your Reference Number:</div>
                    <div class="reference-number"><?php echo $reference_no; ?></div>
                </div>

                <div class="reminder-box">
                    <div class="reminder-title">⚠️ Important Reminders:</div>
                    <div class="reminder-text">
                        • <strong>Do not forget your reference number</strong><br>
                        • Screenshot this to save your reference number
                        • Please settle the payment as soon as possible so the process will be smooth and hassle-free.
                    </div>
                </div>

                <p class="modal-message">
                    You will be redirected to the payment information page.
                </p>

                <button class="modal-button" onclick="proceedToPayment()">Proceed to Payment</button>
            </div>
        </div>

        <script>
            function proceedToPayment() {
                window.location.href = 'payment_info.php';
            }
        </script>
    </body>
    </html>
    <?php
    
} catch (Exception $e) {
    $conn->rollback();
    
    echo "<script>
            alert('Error processing request: " . addslashes($e->getMessage()) . "');
            window.history.back();
          </script>";
    exit();
    
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>