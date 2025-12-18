<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claimed Requests</title> <link rel="icon" type="image/png" href="../assets/logo.png?v=2">
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
                <a href="admin_dboard.html" class="nav-link">Dashboard</a>
                <a href="ad_all.php" class="nav-link">ALL</a>
                <a href="ad_paid.php" class="nav-link">Paid</a>
                <a href="ad_claimed.php" class="nav-link active">Claimed</a>
                <a href="ad_cancel.php" class="nav-link">Cancelled</a>
                <a href="ad_alumni.php" class="nav-link">Alumni</a>
            </nav>
            <a href="../login.php" class="logout-button">Logout</a>
        </div>
    </header>

    <main class="main-content">
        <h1 class="main-heading">Claimed Requests</h1>

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
                        <th class="table-header-cell" style="text-align:center;">Progress</th>
                        <th class="table-header-cell" style="text-align:center;">Date of Claiming</th>
                        <th class="table-header-cell" style="text-align:center;">Cancel Request</th>
                        <th class="table-header-cell" style="text-align:center;">Payment Confirmation</th>
                        <th class="table-header-cell" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody class="table-body" id="requestTableBody"> 
                    <tr><td colspan="17" style="text-align: center;">Loading requests...</td></tr>
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

        function getBadgeClass(status) {
            if (!status) return 'badge-not-processed';
            const s = status.trim();
            const statusMap = {
                'Unpaid': 'badge-unpaid', 'Paid': 'badge-paid', 'Pending': 'badge-pending',
                'In Progress': 'badge-in-progress', 'Ready for Claiming': 'badge-ready',
                'Claimed': 'badge-claimed', 'Cancelled': 'badge-cancelled', 'Not Processed': 'badge-not-processed'
            };
            return statusMap[s] || 'badge-not-processed';
        }

        function getConfirmBadgeClass(status) {
            const s = status.trim();
            if (s === 'Approved') return 'badge-confirm-approved'; // Green
            if (s === 'Denied') return 'badge-confirm-denied';     // Red
            return 'badge-confirm-pending';                        // Yellow
        }
        
        function createStatusDropdown(currentStatus, type, reqId, isDisabled) {
            const options = ['Pending', 'In Progress', 'Ready for Claiming', 'Claimed', 'Cancelled', 'Not Processed'];
            const disabledAttr = isDisabled ? 'disabled title="Payment must be Approved by Cashier first"' : '';
            
            let html = `<select id="progress-select-${reqId}" class="status-select status-badge ${getBadgeClass(currentStatus)}" 
                onfocus="this.dataset.prev = this.value;"
                onchange="validateProgressChange(this); updateBadgeColor(this);" ${disabledAttr}>`;
            options.forEach(status => {
                const isSelected = status === currentStatus ? 'selected' : '';
                html += `<option value="${status}" ${isSelected}>${status}</option>`;
            });
            html += `</select>`;
            return html;
        }

        function validateProgressChange(selectElement) {
            const newStatus = selectElement.value;
            const dateInput = selectElement.closest('tr').querySelector('.date-input');
            const dateVal = dateInput ? dateInput.value : '';
            
            if ((newStatus === 'Ready for Claiming' || newStatus === 'Claimed') && !dateVal) {
                alert(`⚠️ ACTION REQUIRED: You must enter a Date of Claiming before setting the status to '${newStatus}'.`);
                dateInput.focus(); 
                dateInput.style.border = "2px solid red"; 
                selectElement.value = selectElement.dataset.prev;
                updateBadgeColor(selectElement);
                return false; 
            }
            if (dateInput) dateInput.style.border = ""; 
            selectElement.dataset.prev = newStatus;
        }

        function fetchDataAndRenderTable(searchTerm = '') {
            const tableBody = document.getElementById('requestTableBody'); 
            let url = 'admin_claimed_requests.php'; 
            if (searchTerm) url += `?search=${encodeURIComponent(searchTerm)}`;

            fetch(url) 
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success" && data.data && data.data.length > 0) {
                        tableBody.innerHTML = ''; 
                        
                        data.data.forEach(request => {
                            const dbProgress = (request.progress || 'Not Processed').trim();
                            const dbConfirmation = (request.payment_confirmation || 'Pending').trim();
                            const payStatus = (request.payment_status || 'Unpaid').trim();
                            
                            let finalStatus = 'Pending';
                            if (['Pending', 'In Progress', 'Ready for Claiming', 'Claimed'].includes(dbProgress)) {
                                finalStatus = 'Approved';
                            } else if (dbProgress === 'Cancelled') {
                                finalStatus = 'Denied';
                            } else {
                                finalStatus = dbConfirmation;
                            }
                            
                            const rowClass = ''; 

                            const isDropdownLocked = (finalStatus !== 'Approved');

                            const confirmBadge = `<span class="status-badge ${getConfirmBadgeClass(finalStatus)}">${finalStatus}</span>`;

                            const isDateDisabled = (dbProgress === 'Claimed') ? 'disabled' : '';
                            const dateStyle = (dbProgress === 'Claimed') ? 'background-color: #e9ecef; cursor: not-allowed;' : '';

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
                                        <span id="payment-status-${request.req_id}" class="status-badge ${getBadgeClass(payStatus)}">
                                            ${payStatus}
                                        </span>
                                    </td>
                                    
                                    <td class="table-cell" style="text-align:center;">
                                        ${createStatusDropdown(dbProgress, 'progress', request.req_id, isDropdownLocked)}
                                    </td>
                                    
                                    <td class="table-cell" style="text-align:center;">
                                        <input type="date" value="${request.claim_date || ''}" class="date-input" style="${dateStyle}" ${isDateDisabled}>
                                    </td>
                                    
                                    <td class="table-cell" style="text-align:center;">${request.cancel_reason || ''}</td>
                                    
                                    <td class="table-cell" style="text-align:center;">${confirmBadge}</td>
                                    
                                    <td class="table-cell table-actions">
                                        <button class="action-button update-button" onclick="updateRequest(${request.req_id})">Update</button>
                                        <button class="action-button delete-button" onclick="deleteRequest(${request.req_id})">Delete</button>
                                    </td>
                                </tr>
                            `;
                            tableBody.innerHTML += row;
                        });
                    } else {
                         tableBody.innerHTML = '<tr><td colspan="17" style="text-align: center;">No requests found.</td></tr>';
                    }
                });
        }
        
        function updateRequest(reqId) {
            const progressSelect = document.getElementById(`progress-select-${reqId}`);
            if (progressSelect.disabled) {
                alert("Cannot update. Waiting for Cashier Approval.");
                return;
            }
            const progressVal = progressSelect.value;
            const paymentVal = document.getElementById(`payment-status-${reqId}`).innerText.trim();
            const dateInput = progressSelect.closest('tr').querySelector('.date-input');
            const dateVal = dateInput ? dateInput.value : '';
            
            if ((progressVal === 'Ready for Claiming' || progressVal === 'Claimed') && !dateVal) {
                alert(`⚠️ ACTION REQUIRED: Date is required for '${progressVal}'.`);
                progressSelect.value = progressSelect.dataset.prev;
                updateBadgeColor(progressSelect);
                return; 
            }
            if (progressVal === 'Claimed' && !confirm(`Mark ID ${reqId} as CLAIMED? This locks the date.`)) return;

            fetch('admin_update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ req_id: reqId, payment_status: paymentVal, progress_status: progressVal, claim_date: dateVal })
            }).then(res => res.json()).then(data => {
                alert(data.message);
                fetchDataAndRenderTable(document.getElementById('searchInput').value.trim());
            });
        }

        function deleteRequest(reqId) {
            if (!confirm(`Permanently delete Request ID ${reqId}?`)) return;
            fetch('admin_delete_request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ req_id: reqId })
            }).then(res => res.json()).then(data => {
                alert(data.message);
                fetchDataAndRenderTable(document.getElementById('searchInput').value.trim());
            });
        }
        
        function updateBadgeColor(selectElement) {
            selectElement.className = `status-select status-badge ${getBadgeClass(selectElement.value)}`;
        }
    </script>
</body>
</html>