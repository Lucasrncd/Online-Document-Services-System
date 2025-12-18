<?php
/**
 * PHPMailer Configuration for 2FA Email System
 * * SETUP INSTRUCTIONS:
 * 1. Download PHPMailer from: https://github.com/PHPMailer/PHPMailer
 * 2. Extract to your project root: /PHPMailer/
 * 3. Enable 2-Step Verification in your Gmail account
 * 4. Generate App Password: Google Account > Security > 2-Step Verification > App passwords
 * 5. The values below are updated with your Gmail and App Password.
 */

// Email Configuration
define('EMAIL_HOST', 'smtp.gmail.com');
define('EMAIL_PORT', 587); // Use 465 for SSL, 587 for TLS
// 🔑 Your Gmail address used for SMTP login
define('EMAIL_USERNAME', 'lukerenacido@gmail.com'); 
// 🔑 Your 16-character App Password (uiyu axaq vtzw nbdt)
define('EMAIL_PASSWORD', 'uiyuaxaqvtzwnbdt'); 
// 🔑 The email address that appears as the sender (must match EMAIL_USERNAME)
define('EMAIL_FROM', 'lukerenacido@gmail.com'); 
define('EMAIL_FROM_NAME', 'TUP Online Document Services System');
define('EMAIL_ENCRYPTION', 'tls'); // 'tls' or 'ssl'

// 2FA Settings
define('CODE_EXPIRY_MINUTES', 10); // Code expires after 10 minutes
define('CODE_LENGTH', 6); // 6-digit code

/**
 * Load PHPMailer
 */
function loadPHPMailer() {
    // We check for the two common directory structures: /PHPMailer/src/ or just /PHPMailer/
    $phpmailerDir = __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR;
    
    // Check for the standard modern PHPMailer structure (with a 'src' folder)
    $srcPath = $phpmailerDir . 'src' . DIRECTORY_SEPARATOR;
    if (file_exists($srcPath . 'PHPMailer.php')) {
        $phpmailerDir = $srcPath;
    }
    
    // Final check for the PHPMailer class file
    if (!file_exists($phpmailerDir . 'PHPMailer.php')) {
        die('PHPMailer not found! Please download and install PHPMailer in the /PHPMailer/ directory.');
    }
    
    require_once $phpmailerDir . 'PHPMailer.php';
    require_once $phpmailerDir . 'SMTP.php';
    require_once $phpmailerDir . 'Exception.php';
}

/**
 * Send 2FA Email
 * * @param string $to Recipient email
 * @param string $name Recipient name
 * @param string $code 6-digit verification code
 * @return bool True on success, false on failure
 */
function send2FAEmail($to, $name, $code) {
    // Ensure PHPMailer classes are loaded
    loadPHPMailer();
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = EMAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = EMAIL_USERNAME;
        
        // Removed spaces from the App Password for security and consistency
        $mail->Password = str_replace(' ', '', EMAIL_PASSWORD); 
        
        $mail->SMTPSecure = EMAIL_ENCRYPTION;
        $mail->Port = EMAIL_PORT;
        $mail->CharSet = 'UTF-8';
        
        // Disable SSL verification (use only for localhost/development testing)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to, $name);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your TUP 2FA Verification Code';
        
        // Email body with professional styling
        $mail->Body = getEmailTemplate($name, $code);
        $mail->AltBody = "Hello $name,\n\nYour verification code is: $code\n\nThis code will expire in " . CODE_EXPIRY_MINUTES . " minutes.\n\nIf you didn't request this code, please ignore this email.\n\nBest regards,\nTUP Online Document Services System";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log("2FA Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * HTML Email Template
 */
function getEmailTemplate($name, $code) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
            .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
            
            /* --- COLOR CHANGE HERE: Header background set to #550000 --- */
            .header { background: #550000; color: white; padding: 30px; text-align: center; }
            
            .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .content { padding: 40px 30px; }
            .greeting { font-size: 18px; color: #333; margin-bottom: 20px; }
            
            /* --- COLOR CHANGE HERE: Border color set to #550000 --- */
            .code-container { background: #f8f9fa; border: 2px dashed #550000; border-radius: 8px; padding: 30px; text-align: center; margin: 30px 0; }
            
            /* --- COLOR CHANGE HERE: Code text color set to #550000 --- */
            .code { font-size: 36px; font-weight: bold; color: #550000; letter-spacing: 8px; font-family: 'Courier New', monospace; }
            
            .message { color: #555; line-height: 1.6; margin: 20px 0; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; color: #856404; }
            .footer { background: #f8f9fa; padding: 20px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #dee2e6; }
            
            /* --- COLOR CHANGE HERE: Footer link color set to #550000 --- */
            .footer a { color: #550000; text-decoration: none; }
            
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🔐 Two-Factor Authentication</h1>
            </div>
            <div class='content'>
                <p class='greeting'>Hello <strong>" . htmlspecialchars($name) . "</strong>,</p>
                <p class='message'>You recently attempted to log in to your TUP Online Document Services System account. To complete your login, please use the verification code below:</p>
                
                <div class='code-container'>
                    <p style='margin: 0 0 10px 0; color: #666; font-size: 14px;'>Your Verification Code</p>
                    <div class='code'>" . htmlspecialchars($code) . "</div>
                </div>
                
                <p class='message'>This code will expire in <strong>" . CODE_EXPIRY_MINUTES . " minutes</strong>. Please enter it on the verification page to complete your login.</p>
                
                <div class='warning'>
                    <strong>⚠️ Security Notice:</strong> If you didn't request this code, please ignore this email and ensure your account is secure.
                </div>
            </div>
            <div class='footer'>
                <p>Technological University of the Philippines</p>
                <p>Ayala Blvd., corner San Marcelino St., Ermita, Manila, 1000</p>
                <p>For concerns: <a href='mailto:uitc@tup.edu.ph'>uitc@tup.edu.ph</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
}
?>