<?php
session_start();

// Check if user is logged in - FIXED to use reg_id
if (!isset($_SESSION['reg_id'])) {
    header("Location: login.php");
    exit();
}

// Get req_id from session if it exists (for convenience)
$req_id = isset($_SESSION['req_id']) ? $_SESSION['req_id'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Info</title>
    <link rel="stylesheet" href="payment_des3.css"> 
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
                <a href="Main.php#home-section">Home</a>
                <a href="Main.php#about-section">About</a>
                <a href="Main.php#guidelines-section">Guidelines</a>
                <a href="Main.php#contacts-section">Contacts</a>
                <a href="payment_info.php" class="active">Payment</a>
                <a href="status.html">Status</a>
                <a href="profile.php">Profile</a>
            </nav>
            <a href="login.php" class="logout-btn">Logout</a>
        </header>
        
        <div class="content-wrapper">
            <div class="form-container payment-info-container"> 
                <!--FORM-->
                <form id="paymentForm" enctype="multipart/form-data">
                    <h2 class="payment-form-heading">Payment Information</h2> 
                    <div class="payment-content">
                        <div class="payment-form-section">
                            <div class="form-group">
                                <label for="ref_number">Reference Number</label>
                                <input type="text" id="ref_number" name="req_id" value="<?php echo htmlspecialchars($req_id); ?>" placeholder="Enter your request reference number" required>
                            </div>
                            <div class="form-group">
                                <label for="receipt_number">Receipt Reference Number</label>
                                <input type="text" id="receipt_number" name="receipt_number" required placeholder='e.g., 5029327299933'>
                            </div>
                            <div class="form-group">
                                <label for="amount_paid">Amount Paid</label>
                                <input type="number" id="amount_paid" name="amount_paid" step="0.01" required placeholder='e.g., 520'>
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

                    <div class="payment-notes">
                        <p class="payment-notice">
                            <strong>Important:</strong> Cash payments are not accepted. Please pay through GCash or BDO only.
                        </p>
                        <p class="contact-info2">
                            To cancel a requested document, navigate to the Status tab. Only unpaid requests can be canceled.
                        </p>
                        <p class="contact-info">
                            For concerns and inquiries, email us at: <span>uitc@gmail.com</span> or <span>registrar@tup.edu.ph</span>
                        </p>
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
                <img src="assets/qrofthegcash.png" alt="gcashqr" class="qr-image1">
                <p>No.: 09296110153   /    Kyle Louise L.</p>
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

    <!-- Insufficient Payment Error Modal -->
    <div id="error-payment-modal" class="error-modal-overlay">
        <div class="error-modal-content">
            <div class="error-icon"></div>
            <h2 class="error-modal-title">Insufficient Payment!</h2>
            
            <div class="payment-details-box">
                <div class="payment-row">
                    <span class="payment-label">Required Amount:</span>
                    <span class="payment-value" id="requiredAmount">₱0.00</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Amount You Paid:</span>
                    <span class="payment-value" id="paidAmount">₱0.00</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Shortage:</span>
                    <span class="shortage-value" id="shortageAmount">₱0.00</span>
                </div>
            </div>

            <div class="warning-text">
                ⚠️ Please pay the correct amount to proceed with your document request.
            </div>

            <p class="error-modal-message">
                Your payment is insufficient. Please verify the required amount and submit the correct payment.
            </p>

            <button class="error-modal-button" onclick="closeErrorModal()">Try Again</button>
        </div>
    </div>

    <!-- NEW: Overpayment Warning Modal -->
    <div id="overpayment-modal" class="warning-modal-overlay">
        <div class="warning-modal-content">
            <div class="warning-icon"></div>
            <h2 class="warning-modal-title">Overpayment Detected!</h2>
            
            <div class="payment-details-box">
                <div class="payment-row">
                    <span class="payment-label">Required Amount:</span>
                    <span class="payment-value" id="requiredAmountOver">₱0.00</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Amount You Paid:</span>
                    <span class="payment-value" id="paidAmountOver">₱0.00</span>
                </div>
                <div class="payment-row">
                    <span class="payment-label">Overpayment:</span>
                    <span class="overpayment-value" id="overpaymentAmount">₱0.00</span>
                </div>
            </div>

            <div class="warning-text-info">
                ⚠️ You have paid more than the required amount. Please verify your payment.
            </div>

            <p class="warning-modal-message">
                Note: Overpayments are non-refundable. Please double-check the amount before proceeding.
            </p>

            <div class="modal-actions">
                <button class="warning-modal-button cancel-btn" onclick="closeOverpaymentModal()">Go Back</button>
                <button class="warning-modal-button confirm-btn" onclick="confirmOverpayment()">Proceed Anyway</button>
            </div>
        </div>
    </div>
    
    <!-- Payment Complete Modal - UPDATED: Removed download button, added email reminder -->
    <div id="payment-complete-modal" class="success-modal-overlay">
        <div class="success-modal-content">
            <div class="success-icon"></div>
            <h2 class="success-modal-title">Payment Submitted Successfully</h2>
            
            <p class="success-modal-message">
                Your payment has been submitted and is pending verification by the cashier.
            </p>
            
            <div class="success-info-box">
                <p class="success-instruction">
                    ⏳ <strong>Awaiting Payment Verification.</strong>
                </p>
                
                <p class="success-instruction" style="margin-top: 10px;">
                    Once your payment is approved, you will receive an email notification. Your **Claim Stub** will be available for download in the **Profile Tab** and must be presented when claiming your requested document(s).
                </p>
                <p class="success-instruction" style="margin-top: 10px; color: #dc3545;">
                    ⚠️ <strong>Important:</strong> Keep the email with the Claim Stub safe. You will need to download or screenshot it for claiming purposes.
                </p>
            </div>

            <button type="button" class="success-modal-button" onclick="window.location.href='status.html'">OK</button>
        </div>
    </div>

        <!-- Unauthorized Payment Modal -->
        <div id="unauthorized-modal" class="error-modal-overlay">
            <div class="error-modal-content">
                <div class="error-icon"></div>
                <h2 class="error-modal-title">Unauthorized Payment</h2>
                
                <p class="error-modal-message">
                    ⚠️ You cannot make payment for a request that doesn't belong to your account.
                </p>

                <div class="warning-text">
                    Please verify that you are using the correct reference number for your own request.
                </div>

                <button class="error-modal-button" onclick="closeUnauthorizedModal()">OK</button>
            </div>
        </div>

    <script>
        let pendingFormData = null;

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function closeErrorModal() {
            document.getElementById('error-payment-modal').style.display = 'none';
        }

        function closeOverpaymentModal() {
            document.getElementById('overpayment-modal').style.display = 'none';
            pendingFormData = null;
        }

        function closeSuccessModal() {
            document.getElementById('payment-complete-modal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) modal.style.display = 'none';
            }
            
            const errorModal = document.getElementById('error-payment-modal');
            if (event.target === errorModal) {
                closeErrorModal();
            }

            const overpaymentModal = document.getElementById('overpayment-modal');
            if (event.target === overpaymentModal) {
                closeOverpaymentModal();
            }

            const successModal = document.getElementById('payment-complete-modal');
            if (event.target === successModal) {
                closeSuccessModal();
            }
        }

        function showInsufficientPaymentModal(requiredAmount, paidAmount) {
            const shortage = requiredAmount - paidAmount;
            
            document.getElementById('requiredAmount').textContent = '₱' + parseFloat(requiredAmount).toFixed(2);
            document.getElementById('paidAmount').textContent = '₱' + parseFloat(paidAmount).toFixed(2);
            document.getElementById('shortageAmount').textContent = '₱' + parseFloat(shortage).toFixed(2);
            
            document.getElementById('error-payment-modal').style.display = 'flex';
        }

        function showOverpaymentModal(requiredAmount, paidAmount, overpayment) {
            document.getElementById('requiredAmountOver').textContent = '₱' + parseFloat(requiredAmount).toFixed(2);
            document.getElementById('paidAmountOver').textContent = '₱' + parseFloat(paidAmount).toFixed(2);
            document.getElementById('overpaymentAmount').textContent = '₱' + parseFloat(overpayment).toFixed(2);
            
            document.getElementById('overpayment-modal').style.display = 'flex';
        }

        function confirmOverpayment() {
            if (pendingFormData) {
                closeOverpaymentModal();
                
                const form = document.getElementById("paymentForm");
                const newFormData = new FormData(form);
                newFormData.append('confirm_overpayment', '1');
                
                submitPayment(newFormData);
            }
        }

        function submitPayment(formData) {
            fetch("payment_infobackend.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log("Raw server response:", text);
                try {
                    const data = JSON.parse(text);
                    if (data.status === "success") {
                        document.getElementById('payment-complete-modal').style.display = 'flex';
                        document.getElementById("paymentForm").reset();
                        pendingFormData = null;
                    } else if (data.status === "warning" && data.type === "overpayment") {
                        console.error("Overpayment warning returned even with confirmation flag");
                        alert("An error occurred processing your overpayment confirmation.");
                        pendingFormData = null;
                    } else if (data.status === "error") {
                        if (data.message.includes("Insufficient payment")) {
                            const requiredMatch = data.message.match(/₱([\d,]+\.\d{2})/);
                            const paidMatch = data.message.match(/you paid ₱([\d,]+\.\d{2})/);
                            
                            if (requiredMatch && paidMatch) {
                                const requiredAmount = requiredMatch[1].replace(',', '');
                                const paidAmount = paidMatch[1].replace(',', '');
                                showInsufficientPaymentModal(requiredAmount, paidAmount);
                            } else {
                                alert(data.message);
                            }
                        } else {
                            alert(data.message || "An error occurred while submitting payment.");
                        }
                        pendingFormData = null;
                    }
                } catch (e) {
                    alert("Server returned invalid response. Check console for details.");
                    console.error("Invalid JSON:", text);
                    pendingFormData = null;
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                alert("An error occurred while submitting payment.");
                pendingFormData = null;
            });
        }

        function validateAndSubmit() {
            const form = document.getElementById("paymentForm");
            const requiredFields = form.querySelectorAll("input[required]");
            
            for (let field of requiredFields) {
                if (!field.value) {
                    field.reportValidity();
                    return;
                }
            }

            const formData = new FormData(form);
            
            console.log("Submitting form data...");
            
            fetch("payment_infobackend.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(text => {
                console.log("Raw server response:", text);
                try {
                    const data = JSON.parse(text);
                    
                    console.log("Parsed response:", data);
                    
                    if (data.status === "success") {
                        document.getElementById('payment-complete-modal').style.display = 'flex';
                        form.reset();
                    } else if (data.status === "warning" && data.type === "overpayment") {
                        console.log("Overpayment detected, showing modal");
                        pendingFormData = true;
                        showOverpaymentModal(
                            data.required_amount.replace(',', ''),
                            data.paid_amount.replace(',', ''),
                            data.overpayment.replace(',', '')
                        );
                    } else if (data.status === "error") {
                        if (data.message.includes("Insufficient payment")) {
                            const requiredMatch = data.message.match(/₱([\d,]+\.\d{2})/);
                            const paidMatch = data.message.match(/you paid ₱([\d,]+\.\d{2})/);
                            
                            if (requiredMatch && paidMatch) {
                                const requiredAmount = requiredMatch[1].replace(',', '');
                                const paidAmount = paidMatch[1].replace(',', '');
                                showInsufficientPaymentModal(requiredAmount, paidAmount);
                            } else {
                                alert(data.message);
                            }
                        } else {
                            alert(data.message || "An error occurred while submitting payment.");
                        }
                    }
                } catch (e) {
                    alert("Server returned invalid response. Check console for details.");
                    console.error("Invalid JSON:", text);
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                alert("An error occurred while submitting payment.");
            });
        }
    </script>
</body>
</html>