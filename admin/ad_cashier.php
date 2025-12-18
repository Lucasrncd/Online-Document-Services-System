<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Verification</title>
    <link rel="icon" type="image/png" href="../assets/logo.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_tables2.css"> 
</head>
<body>

    <header class="dashboard-header">
        <div class="header-container">
            <div class="logo-area">
                <img src="../assets/logo.png" alt="Logo" class="header-logo">
            </div>
            <nav class="nav-links">
                <a href="ad_cashier.php" class="nav-link active">Cashier</a>
                </nav>
            <a href="../login.php" class="logout-button">Logout</a>
        </div>
    </header>

    <main class="main-content">
        <h1 class="main-heading">Cashier Verification</h1>

        <div class="search-bar-container">
            <input type="text" id="searchInput" placeholder="Search" class="search-input">
            <button class="search-button">Search</button>
        </div>

        <div class="data-table-container">
            <table class="data-table">
                <thead class="table-header-row header-gold">
                    <tr>
                        <th class="table-header-cell">Full Name</th>
                        <th class="table-header-cell" style="text-align:center;">Student Number</th>
                        <th class="table-header-cell" style="text-align:center;">Course</th>
                        <th class="table-header-cell" style="text-align:center;">College</th>
                        <th class="table-header-cell">Contact Number</th>
                        <th class="table-header-cell">Email</th>
                        <th class="table-header-cell">Address</th>
                        <th class="table-header-cell">Purpose</th>
                        <th class="table-header-cell">Requested Documents</th>
                        <th class="table-header-cell" style="text-align:center;">No. of Copies</th>
                        <th class="table-header-cell" style="text-align:center;">Amount</th>
                        <th class="table-header-cell" style="text-align:center;">Payment Status</th>
                        <th class="table-header-cell" style="text-align:center;">Reference No.</th>
                        <th class="table-header-cell" style="text-align:center;">Receipt</th>
                        <th class="table-header-cell" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody class="table-body" id="requestTableBody"> 
                    <tr><td colspan="15" style="text-align: center;">Loading requests...</td></tr>
                </tbody>
            </table>
        </div>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetchDataAndRenderTable();
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function() { fetchDataAndRenderTable(this.value.trim()); });
            document.querySelector('.search-button').addEventListener('click', function() { fetchDataAndRenderTable(searchInput.value.trim()); });
        });

        /**
         * Fetches data from the server endpoint and renders the table rows.
         * @param {string} searchTerm - Optional search term to filter results.
         */
        function fetchDataAndRenderTable(searchTerm = '') {
            const tableBody = document.getElementById('requestTableBody'); 
            // Endpoint to fetch payment requests awaiting cashier approval
            let url = 'admin_cashier_requests.php'; 
            if (searchTerm) url += `?search=${encodeURIComponent(searchTerm)}`;

            fetch(url) 
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === "success" && data.data && data.data.length > 0) {
                        tableBody.innerHTML = ''; // Clear existing rows
                        
                        data.data.forEach(request => {
                            // Determine the final status for action button logic
                            const dbProgress = (request.progress || 'Not Processed').trim();
                            const dbConfirmation = (request.payment_confirmation || 'Pending').trim();
                            
                            let finalStatus = 'Pending';
                            
                            if (['Pending', 'In Progress', 'Ready for Claiming', 'Claimed'].includes(dbProgress)) {
                                finalStatus = 'Approved'; 
                            } else if (dbProgress === 'Cancelled') {
                                finalStatus = 'Denied'; 
                            } else {
                                finalStatus = dbConfirmation; // Use payment_confirmation as primary source for action check
                            }
                            
                            // Only allow Approve/Deny actions if the payment confirmation is 'Pending'
                            const isPendingConfirmation = (finalStatus === 'Pending');
                            const rowClass = isPendingConfirmation ? 'row-new' : ''; 
                            
                            const btnApproveClass = !isPendingConfirmation ? 'action-button btn-gray-action' : 'action-button approve-button';
                            const btnDenyClass = !isPendingConfirmation ? 'action-button btn-gray-action' : 'action-button deny-button';
                            
                            const receiptLink = request.payment_info && request.payment_info.proof_of_receipt 
                                ? `<a href="../uploads/${request.payment_info.proof_of_receipt}" target="_blank" class="receipt-link">View Receipt</a>` 
                                : 'None';
                            const refNum = request.payment_info && request.payment_info.receipt_number || 'N/A';

                            const row = `
                                <tr class="table-row ${rowClass}">
                                    <td class="table-cell table-cell-name">${request['full-name']}</td>
                                    <td class="table-cell" style="text-align:center;">${request['student-number']}</td>
                                    <td class="table-cell" style="text-align:center;">${request.course}</td>
                                    <td class="table-cell" style="text-align:center;">${request.college}</td>
                                    <td class="table-cell">${request['contact-number']}</td>
                                    <td class="table-cell">${request.email}</td>
                                    <td class="table-cell">${request.address}</td>
                                    <td class="table-cell">${request.purpose}</td>
                                    <td class="table-cell">${request.documents_display}</td>
                                    <td class="table-cell" style="text-align:center;">${request.total_copies}</td>
                                    <td class="table-cell" style="text-align:center;">PHP ${request.final_amount}</td>
                                    <td class="table-cell" style="text-align:center;">
                                        <span class="status-badge badge-paid">${request.payment_status}</span>
                                    </td>
                                    <td class="table-cell" style="text-align:center; font-weight:bold;">${refNum}</td>
                                    <td class="table-cell" style="text-align:center;">${receiptLink}</td>
                                    
                                    <td class="table-cell table-actions">
                                        <button class="${btnApproveClass}" onclick="processPayment(${request.req_id}, 'approve')" ${!isPendingConfirmation ? 'disabled' : ''}>Approve</button>
                                        <button class="${btnDenyClass}" onclick="processPayment(${request.req_id}, 'deny')" ${!isPendingConfirmation ? 'disabled' : ''}>Deny</button>
                                    </td>
                                </tr>
                            `;
                            tableBody.innerHTML += row;
                        });
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="15" style="text-align: center;">No paid requests awaiting verification.</td></tr>';
                    }
                })
                .catch(err => {
                    console.error('Error fetching data:', err);
                    tableBody.innerHTML = '<tr><td colspan="15" style="text-align: center;">Error loading data. Please check the network or server endpoint.</td></tr>';
                });
        }
        
        /**
         * Sends an AJAX request to approve or deny a payment.
         * @param {number} reqId - The request ID.
         * @param {string} action - 'approve' or 'deny'.
         */
        function processPayment(reqId, action) {
            const verb = action === 'approve' ? 'APPROVE' : 'DENY';
            if (!confirm(`Are you sure you want to ${verb} this payment? This action is usually irreversible.`)) return;

            // Endpoint to handle the cashier's action (admin_cashier_action.php)
            fetch('admin_cashier_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ req_id: reqId, action: action })
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    // Reload the table after a successful action
                    fetchDataAndRenderTable(document.getElementById('searchInput').value.trim());
                } else {
                    alert('Action Failed: ' + data.message);
                }
            }).catch(err => alert('Network Error: Could not connect to the action server.'));
        }
    </script>
</body>
</html>