<?php
ob_start(); // Start output buffering
header('Content-Type: application/json; charset=utf-8');
session_start();

// Database connection
$host = 'localhost'; 
$username = 'root'; 
$password = ''; 
$database = 'tupdb';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $username, $password, $database);
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Connection failed: " . $e->getMessage()]);
    exit;
}

// Include PHPMailer configuration
require_once '../email-config.php';

$input = json_decode(file_get_contents("php://input"), true);
$req_id = intval($input['req_id'] ?? 0);
$action = $input['action'] ?? '';

if ($req_id <= 0 || !in_array($action, ['approve', 'deny'])) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

try {
    $newStatus = ($action === 'approve') ? 'Approved' : 'Denied';

    // Update payment confirmation status
    $stmt = $conn->prepare("UPDATE payment_info_tbl SET payment_confirmation = ? WHERE req_id = ?");
    $stmt->bind_param("si", $newStatus, $req_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Fetch user details for email notification
            $stmt_user = $conn->prepare("SELECT `full-name`, email FROM request_student_tbl WHERE req_id = ?");
            $stmt_user->bind_param("i", $req_id);
            $stmt_user->execute();
            $result = $stmt_user->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $fullName = $user['full-name'];
                $email = $user['email'];
                
                // Send payment status email
                $emailSent = sendPaymentStatusEmail($email, $fullName, $newStatus, $req_id);
                
                ob_end_clean(); // Clear buffer before JSON output
                
                if ($emailSent) {
                    echo json_encode([
                        "status" => "success", 
                        "message" => "Payment marked as " . $newStatus . ". Email notification sent."
                    ]);
                } else {
                    echo json_encode([
                        "status" => "success", 
                        "message" => "Payment marked as " . $newStatus . ". Email notification failed to send."
                    ]);
                }
            } else {
                ob_end_clean();
                echo json_encode([
                    "status" => "success", 
                    "message" => "Payment marked as " . $newStatus . ". User not found for email notification."
                ]);
            }
            
            $stmt_user->close();
        } else {
            ob_end_clean();
            echo json_encode(["status" => "error", "message" => "No payment record found to update."]);
        }
    } else {
        throw new Exception("Update failed.");
    }
    
    $stmt->close();

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}

$conn->close();

/**
 * Get mailer instance from email-config.php
 */
function getMailerInstance() {
    loadPHPMailer();
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = EMAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = EMAIL_USERNAME;
    $mail->Password = str_replace(' ', '', EMAIL_PASSWORD);
    $mail->SMTPSecure = EMAIL_ENCRYPTION;
    $mail->Port = EMAIL_PORT;
    $mail->CharSet = 'UTF-8';
    
    // Disable SSL verification for localhost
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    return $mail;
}

/**
 * Send payment status email notification
 */
function sendPaymentStatusEmail($email, $fullName, $status, $reqId) {
    try {
        $mail = getMailerInstance();
        
        // Set email parameters
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($email, $fullName);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
        
        // Get subject and content based on status
        $emailData = getPaymentEmailContent($status, $reqId);
        
        $mail->Subject = $emailData['subject'];
        $mail->isHTML(true);
        $mail->Body = getPaymentEmailTemplate($fullName, $emailData['message'], $emailData['note'], $status);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email subject, message, and note based on payment status
 */
function getPaymentEmailContent($status, $reqId) {
    
    $content = [
        'Approved' => [
            'subject' => 'Payment Approved - Request ID: ' . $reqId,
            'message' => 'Your payment has been <strong>approved</strong> by the cashier. Your document request will now proceed to processing.',
            // Content placed directly into 'note' as a single string with <br> tags
            'note' => '<strong>Next Steps:</strong><br>
                        • Please download or save the Claim Stub for your records. This document is accessible in your Profile section.<br>
                        • You will receive another email notification once your documents are "Ready for Claiming".'
        ],
        'Denied' => [
            'subject' => 'Payment Verification Issue - Request ID: ' . $reqId,
            'message' => 'Your payment could not be verified and has been <strong>denied</strong>. This may be due to incorrect payment details, invalid receipt, or payment discrepancies.',
            'note' => '<strong>Action Required:</strong> Please contact the TUP Registrar\'s Office or Cashier to resolve this issue. You may need to request the document(s) again and resubmit your payment.'
        ]
    ];
    
    return $content[$status] ?? [
        'subject' => 'Payment Status Update - Request ID: ' . $reqId,
        'message' => "Your payment status has been updated to: {$status}",
        'note' => ''
    ];
}

/**
 * Generate HTML email template for payment status
 */
function getPaymentEmailTemplate($fullName, $message, $note, $status) {
    $statusColors = [
        'Approved' => '#28a745',
        'Denied' => '#dc3545'
    ];
    
    $statusColor = $statusColors[$status] ?? '#666666';
    $noteSection = $note ? "<div style='background-color: " . ($status === 'Approved' ? '#d4edda' : '#f8d7da') . "; border: 1px solid " . ($status === 'Approved' ? '#c3e6cb' : '#f5c6cb') . "; border-radius: 4px; padding: 15px 20px; margin: 20px 0;'><p style='color: " . ($status === 'Approved' ? '#155724' : '#721c24') . "; margin: 0; font-size: 14px; line-height: 1.6;'>{$note}</p></div>" : "";
    
    $html = '<!DOCTYPE html>';
    $html .= '<html lang="en">';
    $html .= '<head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $html .= '<title>Payment Status Update</title>';
    $html .= '</head>';
    $html .= '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">';
    $html .= '<table role="presentation" style="width: 100%; border-collapse: collapse;">';
    $html .= '<tr><td style="padding: 20px 0;">';
    $html .= '<table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
    
    // Header
    $html .= '<tr>';
    $html .= '<td style="background: linear-gradient(135deg, #550000 0%, #8B0000 100%); padding: 30px; text-align: center;">';
    $html .= '<h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">TUP Online Document Services System</h1>';
    $html .= '<p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Cashier Payment Verification</p>';
    $html .= '</td>';
    $html .= '</tr>';
    
    // Content
    $html .= '<tr>';
    $html .= '<td style="padding: 40px 30px;">';
    $html .= '<h2 style="color: #333333; margin: 0 0 20px 0; font-size: 22px;">Hello, ' . htmlspecialchars($fullName) . '!</h2>';
    
    $html .= '<div style="background-color: #f8f9fa; border-left: 4px solid ' . $statusColor . '; padding: 15px 20px; margin: 20px 0;">';
    $html .= '<p style="color: #666666; margin: 0; font-size: 14px; line-height: 1.6;">';
    $html .= '<strong style="color: ' . $statusColor . '; font-size: 16px;">Payment Status: ' . $status . '</strong>';
    $html .= '</p>';
    $html .= '<p style="color: #333333; margin: 10px 0 0 0; font-size: 16px; line-height: 1.6;">' . $message . '</p>';
    $html .= '</div>';
    
    $html .= $noteSection;
    
    $html .= '<p style="color: #666666; margin: 20px 0 0 0; font-size: 14px; line-height: 1.6;">';
    $html .= 'If you have any questions or concerns about your payment status, please contact the TUP Cashier\'s Office or Registrar.';
    $html .= '</p>';
    $html .= '</td>';
    $html .= '</tr>';
    
    // Footer
    $html .= '<tr>';
    $html .= '<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">';
    $html .= '<p style="color: #999999; margin: 0; font-size: 12px; line-height: 1.5;">';
    $html .= 'This is an automated message from the TUP Online Document Services System.<br>';
    $html .= 'Please do not reply to this email.';
    $html .= '</p>';
    $html .= '<p style="color: #999999; margin: 10px 0 0 0; font-size: 12px;">';
    $html .= '&copy; 2024 Technological University of the Philippines. All rights reserved.';
    $html .= '</p>';
    $html .= '</td>';
    $html .= '</tr>';
    
    $html .= '</table>';
    $html .= '</td></tr>';
    $html .= '</table>';
    $html .= '</body>';
    $html .= '</html>';
    
    return $html;
}
?>