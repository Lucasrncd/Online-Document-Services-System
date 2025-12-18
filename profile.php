<?php 
require_once 'profile_backend.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - TUP</title>
    <link rel="stylesheet" href="main_nav.css">
    <link rel="stylesheet" href="profile_des2.css">
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
                <a href="payment_info.php">Payment</a>
                <a href="status.html">Status</a>
                <a href="profile.php" class="active">Profile</a>
            </nav>
            <a href="login.php" class="logout-btn">Logout</a>
        </header>

        <div class="profile-content">
            <div class="profile-container">
                <h1 class="profile-title">User Profile</h1>

                <!-- Requested Documents Section (Top) -->
                <div class="profile-section documents-section">
                    <h2 class="section-title">Requested Documents</h2>
                    <div class="documents-table-container">
                        <table class="documents-table">
                            <thead>
                                <tr>
                                    <th>Reference Number</th>
                                    <th>Document</th>
                                    <th>Copies</th>
                                    <th>Amount</th>
                                    <th>Payment Status</th>
                                    <th>Payment Confirmation</th>
                                    <th>Request Date</th>
                                    <th>Claim Stub</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (!empty($documentsResult)) {
                                    foreach($documentsResult as $doc) {
                                        echo "<tr>";
                                        echo "<td>" . htmlspecialchars($doc['req_id']) . "</td>";
                                        echo "<td>" . htmlspecialchars($doc['description']) . "</td>";
                                        echo "<td>" . htmlspecialchars($doc['copies']) . "</td>";
                                        echo "<td>₱" . number_format($doc['amount'], 2) . "</td>";
                                        
                                        // Payment Status with styling
                                        $paymentStatusClass = '';
                                        $paymentStatusText = htmlspecialchars($doc['payment_status']);
                                        
                                        switch(strtolower($doc['payment_status'])) {
                                            case 'paid':
                                                $paymentStatusClass = 'status-paid';
                                                break;
                                            case 'pending':
                                                $paymentStatusClass = 'status-pending';
                                                break;
                                            case 'unpaid':
                                                $paymentStatusClass = 'status-unpaid';
                                                break;
                                            default:
                                                $paymentStatusClass = 'status-default';
                                        }
                                        
                                        echo "<td><span class='payment-status " . $paymentStatusClass . "'>" . $paymentStatusText . "</span></td>";
                                        
                                        // Payment Confirmation Status
                                        $confirmationStatus = $doc['payment_confirmation'] ?? 'N/A';
                                        $confirmationClass = '';
                                        
                                        switch(strtolower($confirmationStatus)) {
                                            case 'approved':
                                                $confirmationClass = 'status-approved';
                                                break;
                                            case 'pending':
                                                $confirmationClass = 'status-pending';
                                                break;
                                            case 'denied':
                                                $confirmationClass = 'status-denied';
                                                break;
                                            default:
                                                $confirmationClass = 'status-default';
                                        }
                                        
                                        echo "<td><span class='payment-status " . $confirmationClass . "'>" . htmlspecialchars($confirmationStatus) . "</span></td>";
                                        
                                        // Request Date formatted
                                        $requestDate = date('M d, Y', strtotime($doc['request_date']));
                                        echo "<td>" . htmlspecialchars($requestDate) . "</td>";
                                        
                                        // Download Claim Stub Button
                                        echo "<td>";
                                        if (strtolower($confirmationStatus) === 'approved') {
                                            // Find the claim stub file
                                            $claimStubPattern = __DIR__ . "/claimstubs/claimstub_" . $doc['req_id'] . "_*.pdf";
                                            $claimStubFiles = glob($claimStubPattern);
                                            
                                            if (!empty($claimStubFiles)) {
                                                $claimStubFile = basename($claimStubFiles[0]);
                                                echo "<a href='download_claimstub.php?req_id=" . $doc['req_id'] . "' class='download-btn' title='Download Claim Stub'>
                                                        <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                            <path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path>
                                                            <polyline points='7 10 12 15 17 10'></polyline>
                                                            <line x1='12' y1='15' x2='12' y2='3'></line>
                                                        </svg>
                                                        Download
                                                      </a>";
                                            } else {
                                                echo "<span class='no-stub'>No file</span>";
                                            }
                                        } else {
                                            echo "<button class='download-btn disabled' disabled title='Payment must be approved first'>
                                                    <svg width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                        <path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path>
                                                        <polyline points='7 10 12 15 17 10'></polyline>
                                                        <line x1='12' y1='15' x2='12' y2='3'></line>
                                                    </svg>
                                                    Download
                                                  </button>";
                                        }
                                        echo "</td>";
                                        
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='8' style='text-align: center; color: #999; padding: 2rem;'>No documents requested yet</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <form id="profileForm" method="POST" action="profile_backend.php">
                    <div class="profile-section">
                        <h2 class="section-title">Personal Information</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" id="lastName" name="lastName" placeholder="Last Name" value="<?php echo htmlspecialchars($userData['lastName']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" id="firstName" name="firstName" placeholder="First Name" value="<?php echo htmlspecialchars($userData['firstName']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" id="middleName" name="middleName" placeholder="Middle Name" value="<?php echo htmlspecialchars($userData['middleName']); ?>">
                            </div>

                            <div class="form-group">
                                <label>Extension Name</label>
                                <input type="text" id="extName" name="extName" placeholder="Jr., Sr., III, etc." value="<?php echo htmlspecialchars($userData['extName']); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Contact Number</label>
                                <input type="text" id="contactNumber" name="contactNumber" placeholder="Enter contact number" value="<?php echo htmlspecialchars($userData['contactNumber']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" id="address" name="address" placeholder="Enter address" value="<?php echo htmlspecialchars($userData['address']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" id="email" name="email" placeholder="Enter email" value="<?php echo htmlspecialchars($userData['email']); ?>" required readonly>
                            </div>

                            <div class="form-group">
                                <label>Student Number</label>
                                <input type="text" id="studentNumber" name="studentNumber" placeholder="e.g., TUPM-XX-XXXX" value="<?php echo htmlspecialchars($userData['studentNumber']); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>College</label>
                                <select id="college" name="college" required>
                                    <option value="">Select College</option>
                                    <option value="COS" <?php echo ($userData['college'] == 'COS') ? 'selected' : ''; ?>>COS - College of Science</option>
                                    <option value="CAFA" <?php echo ($userData['college'] == 'CAFA') ? 'selected' : ''; ?>>CAFA - College of Architecture and Fine Arts</option>
                                    <option value="CIE" <?php echo ($userData['college'] == 'CIE') ? 'selected' : ''; ?>>CIE - College of Industrial Education</option>
                                    <option value="CIT" <?php echo ($userData['college'] == 'CIT') ? 'selected' : ''; ?>>CIT - College of Industrial Technology</option>
                                    <option value="COE" <?php echo ($userData['college'] == 'COE') ? 'selected' : ''; ?>>COE - College of Engineering</option>
                                    <option value="CLA" <?php echo ($userData['college'] == 'CLA') ? 'selected' : ''; ?>>CLA - College of Liberal Arts</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Course</label>
                                <select id="course" name="course" required>
                                    <option value="">Select College First</option>
                                </select>
                            </div>
                        </div>

                        <input type="hidden" name="action" value="update_profile">
                        <button type="submit" class="save-btn">SAVE PERSONAL INFORMATION</button>
                    </div>
                </form>

                <!-- Change Password Section -->
                <form id="passwordForm" method="POST" action="profile_backend.php">
                    <div class="profile-section">
                        <h2 class="section-title">Change Password</h2>
                        
                        <div class="form-group">
                            <label>Current Password</label>
                            <input type="password" id="currentPassword" name="currentPassword" placeholder="Enter current password" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password" id="newPassword" name="newPassword" placeholder="Min. 12 characters" required>
                            </div>

                            <div class="form-group">
                                <label>Confirm Password</label>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" required>
                            </div>
                        </div>

                        <input type="hidden" name="action" value="change_password">
                        <button type="submit" class="save-btn">CHANGE PASSWORD</button>
                    </div>
                </form>
            </div>
        </div>

        <footer class="general-footer">
            <p>Copyright &copy; 2025. Designed by <span>Group 4 BSCS - 3B</span></p>
        </footer>
    </div>

    <!-- Success Modal -->
    <div id="successModal" class="modal" <?php if($showModal) echo 'style="display: block;"'; ?>>
        <div class="modal-content">
            <div class="modal-icon">✓</div>
            <h2 id="modalTitle"><?php echo htmlspecialchars($modalTitle); ?></h2>
            <p id="modalMessage"><?php echo htmlspecialchars($modalMessage); ?></p>
            <button class="modal-btn" onclick="closeModal()">OK</button>
        </div>
    </div>

    <script>
        // Course data for each college
        const coursesData = {
            'COS': ['BSCS', 'BAS-LT', 'BSES', 'BSIS', 'BSIT'],
            'CAFA': ['BSA', 'BFA', 'BGT - AT', 'BGT - ID', 'BGT - MDT'],
            'CIE': ['BTLE-ICT', 'BTLE-HE', 'BTLE-IA', 'BTVTE-A', 'BTVTE-BCW', 'BTVTE-CP', 'BTVTE-Electrical', 'BTVTE-Electronics', 'BTVTE-FSM', 'BTVTE-FG', 'BTVTE-HVAC', 'BTTE'],
            'CIT': ['BSFT', 'BET-CpET', 'BET-CT', 'BET-EET', 'BET-ECET', 'BET-EsT', 'BET-ICET', 'BET-MET', 'BET-MT', 'BET-RET', 'BET-AET', 'BET-FET', 'BET-HVAC', 'BET-PPET', 'BET-WET', 'BET-DMT', 'BT-AFT', 'BT-CT', 'BT-CLT', 'BT-PMT'],
            'COE': ['BSCE', 'BSEE', 'BSECE', 'BSME'],
            'CLA': ['BSBA-IM', 'BSE', 'BSHM']
        };

        const collegeSelect = document.getElementById('college');
        const courseSelect = document.getElementById('course');
        const savedCourse = "<?php echo htmlspecialchars($userData['course']); ?>";

        // Function to populate courses based on selected college
        function populateCourses(selectedCollege, courseToSelect = '') {
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            
            if (selectedCollege && coursesData[selectedCollege]) {
                coursesData[selectedCollege].forEach(course => {
                    const option = document.createElement('option');
                    option.value = course;
                    option.textContent = course;
                    if (course === courseToSelect) {
                        option.selected = true;
                    }
                    courseSelect.appendChild(option);
                });
            }
        }

        // Initialize courses on page load
        const currentCollege = collegeSelect.value;
        if (currentCollege) {
            populateCourses(currentCollege, savedCourse);
        }

        // Handle college selection change
        collegeSelect.addEventListener('change', function() {
            populateCourses(this.value);
        });

        function closeModal() {
            document.getElementById('successModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('successModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Escape key to close modal
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>