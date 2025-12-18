<?php
session_start();

// Redirect to alumni registration if user is trying to register as alumni
if (isset($_SESSION['is_alumni_registration']) && $_SESSION['is_alumni_registration'] === true) {
    header('Location: registration_alumni.php');
    exit();
}

// Check if user came from Google OAuth for student registration
$isGoogleRegistration = isset($_SESSION['google_verified_email']) && isset($_SESSION['is_student_registration']);
$googleEmail = $isGoogleRegistration ? $_SESSION['google_verified_email'] : '';
$googleFirstName = isset($_SESSION['google_first_name']) ? $_SESSION['google_first_name'] : '';
$googleLastName = isset($_SESSION['google_last_name']) ? $_SESSION['google_last_name'] : '';

// Display error message if exists
$errorMessage = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration Form</title>
    <link rel="stylesheet" href="reg_des3.css">
    <link rel="icon" type="image/png" href="assets/logo.png?v=2">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <style>
        .google-verified-badge {
            display: inline-flex;
            align-items: center;
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 8px;
        }
        
        .google-verified-badge svg {
            width: 16px;
            height: 16px;
            margin-right: 4px;
        }
        
        .verified-email-note {
            background-color: #e3f2fd;
            border-left: 4px solid #1976d2;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .verified-email-note p {
            margin: 0;
            color: #1565c0;
            font-size: 14px;
        }

        .error-message {
            background-color: #ffebee;
            border-left: 4px solid #c62828;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
            color: #c62828;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <div class="form-header">Student Registration Form</div>
        <div class="form-content">
            <div class="left-section">
                <?php if (!empty($errorMessage)): ?>
                <div class="error-message">
                    <p><strong>⚠ Error:</strong> <?php echo htmlspecialchars($errorMessage); ?></p>
                </div>
                <?php endif; ?>

                <?php if ($isGoogleRegistration): ?>
                <div class="verified-email-note">
                    <p><strong>✓ Google Account Verified</strong></p>
                    <p>Your email has been verified by Google. Complete the form below to finish student registration.</p>
                </div>
                <?php endif; ?>
                
                <!--FORM SECTION-->
                <form action="reg_process.php" method="POST" onsubmit="return validateForm()">
                    <div class="form-section">
                        <h3>Applicant's Information</h3>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="lastName">Last Name</label>
                                <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($googleLastName); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="firstName">First Name</label>
                                <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($googleFirstName); ?>" required>
                            </div>
                        </div>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="middleName">Middle Name</label>
                                <input type="text" id="middleName" name="middleName">
                            </div>
                            <div class="form-group">
                                <label for="extName">Extension Name</label>
                                <input type="text" id="extName" name="extName">
                            </div>
                        </div>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="contactNumber">Contact Number</label>
                                <input type="text" id="contactNumber" name="contactNumber" required>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <input type="text" id="address" name="address" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Academic Information</h3>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="studentNumber">Student Number</label>
                                <input type="text" id="studentNumber" name="studentNumber" placeholder="e.g., TUPM-XX-XXXX" required>
                            </div>
                            <div class="form-group">
                                <label for="yearGraduated">Expected Year of Graduation</label>
                                <input type="text" id="yearGraduated" name="yearGraduated" pattern="[0-9]{4}" placeholder="e.g., 2025" required>
                            </div>
                        </div>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="college">College</label>
                                <select id="college" name="college" required>
                                    <option value="">Select College</option>
                                    <option value="COS">COS - College of Science</option>
                                    <option value="CAFA">CAFA - College of Architecture and Fine Arts</option>
                                    <option value="CIE">CIE - College of Industrial Education</option>
                                    <option value="CIT">CIT - College of Industrial Technology</option>
                                    <option value="COE">COE - College of Engineering</option>
                                    <option value="CLA">CLA - College of Liberal Arts</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="course">Course</label>
                                <select id="course" name="course" required disabled>
                                    <option value="">Select College First</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h3>Account Details</h3>
                        <div class="two-column">
                            <div class="form-group">
                                <label for="email">
                                    Email
                                    <?php if ($isGoogleRegistration): ?>
                                    <span class="google-verified-badge">
                                        <svg viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        Verified by Google
                                    </span>
                                    <?php endif; ?>
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       placeholder="Email must have more than 5 characters" 
                                       value="<?php echo htmlspecialchars($googleEmail); ?>"
                                       <?php echo $isGoogleRegistration ? 'readonly style="background-color: #f5f5f5; cursor: not-allowed;"' : ''; ?>
                                       required>
                                <input type="hidden" name="is_google_registration" value="<?php echo $isGoogleRegistration ? '1' : '0'; ?>">
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" placeholder="Enter a strong password" required>
                                <div class="show-password-option">
                                    <input type="checkbox" id="showPassword" onclick="togglePasswordVisibility('password')">
                                    <label for="showPassword">Show Password</label>
                                </div>
                                <div class="password-requirements">
                                    <strong>Password must contain:</strong>
                                    <ul>
                                        <li id="req-length">At least 12 characters</li>
                                        <li id="req-uppercase">At least one uppercase letter (A-Z)</li>
                                        <li id="req-lowercase">At least one lowercase letter (a-z)</li>
                                        <li id="req-number">At least one number (0-9)</li>
                                        <li id="req-special">At least one special character (!@#$%^&*...)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Re-enter your password" required>
                            <div class="show-password-option">
                                <input type="checkbox" id="showConfirmPassword" onclick="togglePasswordVisibility('confirmPassword')">
                                <label for="showConfirmPassword">Show Password</label>
                            </div>
                        </div>
                    </div>
        
                    <!--BTN-->
                    <div class="form-actions">
                        <a href="login.php" class="back-btn" id="backBtn">Back</a>
                        <button type="submit" class="register-btn" id="registerBtn">Register as Student</button>
                    </div>
                </form>
            </div>

            <div class="privacy-notice">
                <h3>DATA PRIVACY NOTICE</h3>
                <div class="privacy-text">
                    <b>We value and protect your personal information in compliance with the Data Privacy Act of 2012 (RA 10173). All data will be kept secure and confidential by the <span class="highlight">Technological University of the Philippines</span> only. The information will serve as a reference for communication. Any personal information will not be disclosed without your consent.</b>
                </div>
                <div class="privacy-choice">
                    <input type="checkbox" id="accept" name="privacy_accept" value="accept">
                    <label for="accept">
                        <b>I hereby acknowledge that I have read, and <span class="accept">do</span> accept the Data Privacy Policy contained in this form.</b>
                    </label>
                </div>
                <div class="privacy-choice">
                    <input type="checkbox" id="decline" name="privacy_decline" value="decline">
                    <label for="decline">
                        <b>I hereby acknowledge that I have read, and <span class="accept">do not</span> accept the Data Privacy Policy contained in this form.</b>
                    </label>
                </div>
            </div>
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

        const accept = document.getElementById('accept');
        const decline = document.getElementById('decline');
        const registerBtn = document.getElementById('registerBtn');
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirmPassword');
        const collegeSelect = document.getElementById('college');
        const courseSelect = document.getElementById('course');

        const reqLength = document.getElementById('req-length');
        const reqUppercase = document.getElementById('req-uppercase');
        const reqLowercase = document.getElementById('req-lowercase');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Handle college selection
        collegeSelect.addEventListener('change', function() {
            const selectedCollege = this.value;
            courseSelect.innerHTML = '<option value="">Select Course</option>';
            
            if (selectedCollege && coursesData[selectedCollege]) {
                courseSelect.disabled = false;
                coursesData[selectedCollege].forEach(course => {
                    const option = document.createElement('option');
                    option.value = course;
                    option.textContent = course;
                    courseSelect.appendChild(option);
                });
            } else {
                courseSelect.disabled = true;
                courseSelect.innerHTML = '<option value="">Select College First</option>';
            }
        });

        passwordInput.addEventListener('input', () => {
            const password = passwordInput.value;
            reqLength.classList.toggle('valid', password.length >= 12);
            reqUppercase.classList.toggle('valid', /[A-Z]/.test(password));
            reqLowercase.classList.toggle('valid', /[a-z]/.test(password));
            reqNumber.classList.toggle('valid', /[0-9]/.test(password));
            reqSpecial.classList.toggle('valid', /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password));
        });

        function validatePassword(password) {
            return password.length >= 12 && 
                   /[A-Z]/.test(password) && 
                   /[a-z]/.test(password) && 
                   /[0-9]/.test(password) && 
                   /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
        }

        function validateForm() {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (!accept.checked) {
                alert('Please accept the Data Privacy Policy to continue.');
                return false;
            }
            
            if (!validatePassword(password)) {
                alert('Password does not meet all requirements. Please ensure:\n' +
                      '- At least 12 characters long\n' +
                      '- Contains at least one uppercase letter (A-Z)\n' +
                      '- Contains at least one lowercase letter (a-z)\n' +
                      '- Contains at least one number (0-9)\n' +
                      '- Contains at least one special character (!@#$%^&*...)');
                return false;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            return true;
        }

        function updateRegisterState() {
            registerBtn.disabled = !(accept.checked && !decline.checked);
        }

        accept.addEventListener('change', () => {
            if (accept.checked) decline.checked = false;
            updateRegisterState();
        });

        decline.addEventListener('change', () => {
            if (decline.checked) accept.checked = false;
            updateRegisterState();
        });

        updateRegisterState();
    </script>
</body>
</html>