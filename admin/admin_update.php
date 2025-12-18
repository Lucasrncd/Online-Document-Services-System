<?php
ob_start(); // Start output buffering
header('Content-Type: application/json');

// Database connection with error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 
try {
    $conn = new mysqli('localhost', 'root', '', 'tupdb');
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "DB Connection Failed: " . $e->getMessage()]);
    exit;
}

// Include PHPMailer configuration
require_once '../email-config.php';

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['req_id'])) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "No Request ID provided."]);
    exit;
}

$req_id = (int)$input['req_id'];
$payment = $input['payment_status'] ?? 'Unpaid';
$progress = $input['progress_status'] ?? 'Pending';
$date = !empty($input['claim_date']) ? $input['claim_date'] : NULL;

try {
    // Update the database
    $stmt = $conn->prepare("UPDATE request_student_tbl SET payment_status = ?, progress = ?, claim_date = ? WHERE req_id = ?");
    $stmt->bind_param("sssi", $payment, $progress, $date, $req_id);
    $stmt->execute();

    if ($stmt->affected_rows >= 0) {
        // Fetch user details for email notification
        $stmt_user = $conn->prepare("SELECT `full-name`, email FROM request_student_tbl WHERE req_id = ?");
        $stmt_user->bind_param("i", $req_id);
        $stmt_user->execute();
        $result = $stmt_user->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $fullName = $user['full-name'];
            $email = $user['email'];
            
            // Send status update email
            $emailSent = sendStatusUpdateEmail($email, $fullName, $progress, $date, $req_id);
            
            ob_end_clean(); // Clear buffer before JSON output
            
            if ($emailSent) {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Updated Request ID $req_id successfully. Email notification sent."
                ]);
            } else {
                echo json_encode([
                    "status" => "success", 
                    "message" => "Updated Request ID $req_id successfully. Email notification failed to send."
                ]);
            }
        } else {
            ob_end_clean();
            echo json_encode([
                "status" => "success", 
                "message" => "Updated Request ID $req_id successfully. User not found for email notification."
            ]);
        }
        
        $stmt_user->close();
    } else {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Update failed (No rows affected)."]);
    }
    $stmt->close();

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "SQL Error: " . $e->getMessage()]);
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
 * Send status update email notification
 */
function sendStatusUpdateEmail($email, $fullName, $status, $claimDate, $reqId) {
    try {
        $mail = getMailerInstance();
        
        // Set email parameters
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($email, $fullName);
        $mail->addReplyTo(EMAIL_FROM, EMAIL_FROM_NAME);
        
        // Get subject and content based on status
        $emailData = getEmailContentByStatus($status, $claimDate, $reqId);
        
        $mail->Subject = $emailData['subject'];
        $mail->isHTML(true);
        $mail->Body = getUpdateEmailTemplate($fullName, $emailData['message'], $emailData['note'], $status);
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email subject, message, and note based on status
 */
function getEmailContentByStatus($status, $claimDate, $reqId) {
    $formattedDate = $claimDate ? date('F j, Y', strtotime($claimDate)) : '';
    
    $content = [
        'Pending' => [
            'subject' => 'Your Document Request is Now Pending',
            'message' => 'Your request is now pending. Please wait for the documents to be processed.',
            'note' => ''
        ],
        'In Progress' => [
            'subject' => 'Your Document Request is In Progress',
            'message' => 'Your document(s) are now being processed. Please wait for the release of the date of claiming.',
            'note' => ''
        ],
        'Ready for Claiming' => [
            'subject' => 'Documents Ready for Claiming!',
            'message' => "You may now claim your requested document(s) on <strong>{$formattedDate}</strong> onwards. Please go to the TUP Registrar and present your Claim Stub / Valid ID / E-Receipt.",
            'note' => '<strong>CLAIMING REMINDERS:</strong><br><br>
                        • Please download or save the <strong>Claim Stub</strong> for your records. This document is accessible in your Profile section.<br><br>
                        • Please have your <strong>e-receipt</strong>, <strong>Valid ID</strong>, and the <strong>Claim Stub</strong> ready, as they may be requested by the registrar upon collection.<br><br>
                        • If a third party will claim the document on your behalf, they must present an Authorization Letter from the requestor and a photocopy of the Requestor\'s Valid ID, in addition to their own Valid ID.'
        ],
        'Claimed' => [
            'subject' => 'Document Claimed Successfully',
            'message' => "You have claimed your requested document(s) on <strong>{$formattedDate}</strong>.",
            'note' => ''
        ],
        'Cancelled' => [
            'subject' => 'Your Document Request Has Been Cancelled',
            'message' => 'Your request for the document(s) has been successfully cancelled.',
            'note' => ''
        ]
    ];
    
    return $content[$status] ?? [
        'subject' => 'Document Request Status Update',
        'message' => "Your document request (ID: {$reqId}) status has been updated to: {$status}",
        'note' => ''
    ];
}

/**
 * Generate HTML email template for status updates
 */
function getUpdateEmailTemplate($fullName, $message, $note, $status) {
    $statusColors = [
        'Pending' => '#FFA500',
        'In Progress' => '#1E90FF',
        'Ready for Claiming' => '#32CD32',
        'Claimed' => '#228B22',
        'Cancelled' => '#DC143C'
    ];
    
    $statusColor = $statusColors[$status] ?? '#666666';
    
    // The note is placed in a visually separated div with a warning color
    $noteSection = $note ? "<div style='background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px 20px; margin: 20px 0;'><p style='color: #856404; margin: 0; font-size: 14px; line-height: 1.6;'>{$note}</p></div>" : "";
    
    $html = '<!DOCTYPE html>';
    $html .= '<html lang="en">';
    $html .= '<head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $html .= '<title>Document Request Status Update</title>';
    $html .= '</head>';
    $html .= '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">';
    $html .= '<table role="presentation" style="width: 100%; border-collapse: collapse;">';
    $html .= '<tr><td style="padding: 20px 0;">';
    $html .= '<table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">';
    
    // Header
    $html .= '<tr>';
    $html .= '<td style="background: linear-gradient(135deg, #550000 0%, #8B0000 100%); padding: 30px; text-align: center;">';
    $html .= '<h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">TUP Document Request System</h1>';
    $html .= '<p style="color: #ffffff; margin: 10px 0 0 0; font-size: 14px; opacity: 0.9;">Technological University of the Philippines</p>';
    $html .= '</td>';
    $html .= '</tr>';
    
    // Content
    $html .= '<tr>';
    $html .= '<td style="padding: 40px 30px;">';
    $html .= '<h2 style="color: #333333; margin: 0 0 20px 0; font-size: 22px;">Hello, ' . htmlspecialchars($fullName) . '!</h2>';
    
    $html .= '<div style="background-color: #f8f9fa; border-left: 4px solid ' . $statusColor . '; padding: 15px 20px; margin: 20px 0;">';
    $html .= '<p style="color: #666666; margin: 0; font-size: 14px; line-height: 1.6;">';
    $html .= '<strong style="color: ' . $statusColor . '; font-size: 16px;">Status Update:</strong>';
    $html .= '</p>';
    $html .= '<p style="color: #333333; margin: 10px 0 0 0; font-size: 16px; line-height: 1.6;">' . $message . '</p>';
    $html .= '</div>';
    
    $html .= $noteSection; // The separate note div is rendered here if $note is not empty.
    
    $html .= '<p style="color: #666666; margin: 20px 0 0 0; font-size: 14px; line-height: 1.6;">';
    $html .= 'If you have any questions or concerns, please contact the TUP Registrar\'s Office.';
    $html .= '</p>';
    $html .= '</td>';
    $html .= '</tr>';
    
    // Footer
    $html .= '<tr>';
    $html .= '<td style="background-color: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #e0e0e0;">';
    $html .= '<p style="color: #999999; margin: 0; font-size: 12px; line-height: 1.5;">';
    $html .= 'This is an automated message from the TUP Document Request System.<br>';
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