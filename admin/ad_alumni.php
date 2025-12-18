<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Verification</title>
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
                <a href="admin_dboard.html" class="nav-link">Dashboard</a>
                <a href="ad_all.php" class="nav-link">ALL</a>
                <a href="ad_paid.php" class="nav-link">Paid</a>
                <a href="ad_claimed.php" class="nav-link">Claimed</a>
                <a href="ad_cancel.php" class="nav-link">Cancelled</a>
                <a href="ad_alumni.php" class="nav-link active">Alumni</a>
            </nav>
            <a href="../login.php" class="logout-button">Logout</a>
        </div>
    </header>

    <main class="main-content">
        <h1 class="main-heading">Alumni Account Verification</h1>

        <div class="search-bar-container">
            <input type="text" id="searchInput" placeholder="Search by name, student number, or email" class="search-input">
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
                        <th class="table-header-cell" style="text-align:center;">Year Graduated</th>
                        <th class="table-header-cell" style="text-align:center;">Account Status</th>
                        <th class="table-header-cell" style="text-align:center;">Action</th>
                    </tr>
                </thead>
                <tbody class="table-body" id="alumniTableBody"> 
                    <tr><td colspan="10" style="text-align: center;">Loading alumni accounts...</td></tr>
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

        function fetchDataAndRenderTable(searchTerm = '') {
            const tableBody = document.getElementById('alumniTableBody'); 
            let url = 'admin_alumni_requests.php'; 
            if (searchTerm) url += `?search=${encodeURIComponent(searchTerm)}`;

            fetch(url) 
                .then(response => response.json())
                .then(data => {
                    if (data.status === "success" && data.data && data.data.length > 0) {
                        tableBody.innerHTML = ''; 
                        
                        data.data.forEach(alumni => {
                            const accountStatus = alumni.account_status || 'Pending';
                            const isPending = (accountStatus === 'Pending');
                            const rowClass = isPending ? 'row-new' : ''; 
                            
                            let statusBadgeClass = 'badge-pending';
                            if (accountStatus === 'Approved') statusBadgeClass = 'badge-paid';
                            else if (accountStatus === 'Denied') statusBadgeClass = 'badge-unpaid';
                            
                            const btnApproveClass = !isPending ? 'action-button btn-gray-action' : 'action-button approve-button';
                            const btnDenyClass = !isPending ? 'action-button btn-gray-action' : 'action-button deny-button';
                            
                            const row = `
                                <tr class="table-row ${rowClass}">
                                    <td class="table-cell table-cell-name">${alumni.fullName}</td>
                                    <td class="table-cell" style="text-align:center;">${alumni.studentNumber}</td>
                                    <td class="table-cell" style="text-align:center;">${alumni.course}</td>
                                    <td class="table-cell" style="text-align:center;">${alumni.college}</td>
                                    <td class="table-cell">${alumni.contactNumber}</td>
                                    <td class="table-cell">${alumni.email}</td>
                                    <td class="table-cell">${alumni.address}</td>
                                    <td class="table-cell" style="text-align:center; font-weight:600;">${alumni.yearGraduated}</td>
                                    <td class="table-cell" style="text-align:center;">
                                        <span class="status-badge ${statusBadgeClass}">${accountStatus}</span>
                                    </td>
                                    <td class="table-cell table-actions">
                                        <button class="${btnApproveClass}" onclick="processAlumni(${alumni.reg_id}, 'approve')" ${!isPending ? 'disabled' : ''}>Approve</button>
                                        <button class="${btnDenyClass}" onclick="processAlumni(${alumni.reg_id}, 'deny')" ${!isPending ? 'disabled' : ''}>Deny</button>
                                    </td>
                                </tr>
                            `;
                            tableBody.innerHTML += row;
                        });
                    } else {
                         tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center;">No alumni accounts found for verification.</td></tr>';
                    }
                })
                .catch(err => {
                    tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: red;">Error loading data: ' + err.message + '</td></tr>';
                });
        }
        
        function processAlumni(regId, action) {
            const verb = action === 'approve' ? 'APPROVE' : 'DENY';
            if (!confirm(`Are you sure you want to ${verb} this alumni account?`)) return;

            fetch('admin_alumni_action.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ reg_id: regId, action: action })
            }).then(res => res.json()).then(data => {
                if (data.status === 'success') {
                    alert(data.message);
                    fetchDataAndRenderTable(document.getElementById('searchInput').value.trim());
                } else {
                    alert('Action Failed: ' + data.message);
                }
            }).catch(err => alert('Network Error: ' + err.message));
        }
    </script>
</body>
</html>