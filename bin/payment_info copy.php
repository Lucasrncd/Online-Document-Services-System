<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if req_id exists, if not, redirect to request form
if (!isset($_SESSION['req_id']) || $_SESSION['req_id'] === null) {
    echo "
    <script>
        alert('You need to submit a document request first before making a payment.');
        window.location.href = 'request_form.html';
    </script>
    ";
    exit();
}

$req_id = $_SESSION['req_id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Info</title>
    <link rel="stylesheet" href="payment_des.css"> 
    <link rel="icon" type="image/png" href="assets/logo.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="main-container">
        <header class="main-header">
            <div class="logo-container">
                <img src="./assets/logo.png" alt="CAS Logo" class="header-logo">
            </div>
            <nav class="main-nav">
                <a href="Main.php#home-section" class="active">Home</a>
                <a href="Main.php#about-section">About</a>
                <a href="Main.php#guidelines-section">Guidelines</a>
                <a href="Main.php#contacts-section">Contacts</a>
                <a href="payment_info.php">Payment</a>
                <a href="status.html">Status</a>
            </nav>
            <a href="logout.php" class="logout-btn">Logout</a>
        </header>
        
        <div class="content-wrapper">
            <div class="form-container payment-info-container"> 
                <form id="paymentForm" enctype="multipart/form-data">
                    <h2 class="payment-form-heading">Payment Information</h2> 
                    <div class="payment-content">
                        <div class="payment-form-section">
                            <div class="form-group">
                                <label for="ref_number">Reference Number</label>
                                <input type="text" id="ref_number" name="req_id" value="<?php echo htmlspecialchars($req_id); ?>" readonly required>
                            </div>
                            <div class="form-group">
                                <label for="receipt_number">Receipt Reference Number</label>
                                <input type="text" id="receipt_number" name="receipt_number" required>
                            </div>
                            <div class="form-group">
                                <label for="amount_paid">Amount Paid</label>
                                <input type="number" id="amount_paid" name="amount_paid" step="0.01" required>
                            </div>
                        </div>
                        
                        <div class="payment-methods-section">
                            <h3 class="section-title">Payment Methods</h3>
                            <div class="payment-options">
                                <img src="assets/gcash.png" alt="GCash" class="payment-icon1" onclick="openModal('gcash-modal')">
                                <img src="assets/bdo.png" alt="BDO" class="payment-icon2" onclick="openModal('bdo-modal')">
                            </div>
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="proof_of_receipt">Proof of Receipt</label>
                                <input type="file" class="add-file-btn" id="proof_of_receipt" name="proof_of_receipt" accept="image/*" required>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions-center">
                        <button type="button" class="btn-submit" onclick="validateAndSubmit()">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- GCASH Modal -->
    <div id="gcash-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('gcash-modal')">&times;</span>
            <h3 class="modal-header">GCash</h3>
            <div class="modal-body">
                <img src="assets/gcashqr.png" alt="gcashqr" class="qr-image1">
                <p>QR Code / Payment Details Placeholder</p>
            </div>
        </div>
    </div>
    
    <!-- BDO Modal -->
    <div id="bdo-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('bdo-modal')">&times;</span>
            <h3 class="modal-header">BDO</h3>
            <div class="modal-body">
                <img src="assets/bdoqr.png" alt="bdoqr" class="qr-image2">
                <p>Account Details Placeholder</p>
            </div>
        </div>
    </div>
    
    <!-- Payment Complete Modal -->
    <div id="payment-complete-modal" class="modal">
        <div class="modal-content">
            <div class="payment-complete-icon">✅</div>
            <h3 class="modal-header">Payment Complete</h3>
            <p>Expect your status to be updated within 5-10 business days.</p>
            <p>Download and present the claim stub with the Official Receipt when claiming the requested document/s.</p>
            <div class="claim-stub">
                <span>Claim Stub:</span>
                <button type="button"="download-btn" id="downloadBtn">Download</button>
            </div>
            <button type="button" class="modal-btn ok-btn" onclick="window.location.href='status.html'">OK</button>
        </div>
    </div>

    <script>
        let pdfUrl = '';

        // Modal controls
        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) modal.style.display = 'none';
            }
        }

        // Validate and submit the form
        function validateAndSubmit() {
            const form = document.getElementById("paymentForm");
            const requiredFields = form.querySelectorAll("input[required]");
            
            // Check if all fields have values
            for (let field of requiredFields) {
                if (!field.value) {
                    field.reportValidity();
                    return;
                }
            }

            // If all fields are filled, submit to PHP backend
            const formData = new FormData(form);
            fetch("payment_infobackend.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    pdfUrl = data.pdf_url;
                    openModal('payment-complete-modal');
                    form.reset();
                } else {
                    alert(data.message || "An error occurred while submitting payment.");
                }
            })
            .catch(error => {
                console.error("Error submitting payment:", error);
                alert("An error occurred while submitting payment.");
            });
        }

        // Download PDF
        document.getElementById('downloadBtn').addEventListener('click', function() {
            if (pdfUrl) {
                window.open(pdfUrl, '_blank');
            } else {
                alert('No claim stub available for download.');
            }
        });
    </script>
</body>
</html>