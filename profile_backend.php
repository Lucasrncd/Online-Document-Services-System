<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tupdb";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['reg_id'])) {
    header("Location: login.php?redirect=profile.php");
    exit();
}

$reg_id = $_SESSION['reg_id'];

// Initialize variables for modal
$showModal = false;
$modalTitle = "";
$modalMessage = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    
    if ($action == "update_profile") {
        // Update Personal Information including academic info
        $lastName = trim($_POST['lastName']);
        $firstName = trim($_POST['firstName']);
        $middleName = trim($_POST['middleName']);
        $extName = trim($_POST['extName']);
        $contactNumber = trim($_POST['contactNumber']);
        $address = trim($_POST['address']);
        $email = trim($_POST['email']);
        $studentNumber = trim($_POST['studentNumber']);
        $college = trim($_POST['college']);
        $course = trim($_POST['course']);

        try {
            $updateQuery = "UPDATE registration_tbl SET 
                            lastName = :lastName, 
                            firstName = :firstName, 
                            middleName = :middleName, 
                            extName = :extName, 
                            contactNumber = :contactNumber, 
                            address = :address, 
                            email = :email,
                            studentNumber = :studentNumber,
                            college = :college,
                            course = :course
                            WHERE reg_id = :reg_id";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([
                ':lastName' => $lastName,
                ':firstName' => $firstName,
                ':middleName' => $middleName,
                ':extName' => $extName,
                ':contactNumber' => $contactNumber,
                ':address' => $address,
                ':email' => $email,
                ':studentNumber' => $studentNumber,
                ':college' => $college,
                ':course' => $course,
                ':reg_id' => $reg_id
            ]);
            
            $_SESSION['modal_show'] = true;
            $_SESSION['modal_title'] = "Profile Updated!";
            $_SESSION['modal_message'] = "Your personal information has been updated successfully.";
            header("Location: profile.php");
            exit();
        } catch(PDOException $e) {
            echo "<script>alert('Error updating profile: " . $e->getMessage() . "'); window.history.back();</script>";
            exit();
        }
        
    } elseif ($action == "change_password") {
        // Change Password
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];

        try {
            // Verify current password
            $passwordQuery = "SELECT password FROM registration_tbl WHERE reg_id = :reg_id";
            $passwordStmt = $conn->prepare($passwordQuery);
            $passwordStmt->execute([':reg_id' => $reg_id]);
            $row = $passwordStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row && password_verify($currentPassword, $row['password'])) {
                if ($newPassword === $confirmPassword) {
                    // Validate password length
                    if (strlen($newPassword) < 8) {
                        echo "<script>alert('New password must be at least 8 characters long!'); window.history.back();</script>";
                        exit();
                    }
                    
                    // Update password (HASH)
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updatePasswordQuery = "UPDATE registration_tbl SET password = :password WHERE reg_id = :reg_id";
                    $updatePasswordStmt = $conn->prepare($updatePasswordQuery);
                    $updatePasswordStmt->execute([
                        ':password' => $hashedPassword,
                        ':reg_id' => $reg_id
                    ]);
                    
                    $_SESSION['modal_show'] = true;
                    $_SESSION['modal_title'] = "Password Changed!";
                    $_SESSION['modal_message'] = "Your password has been changed successfully.";
                    header("Location: profile.php");
                    exit();
                } else {
                    echo "<script>alert('New passwords do not match!'); window.history.back();</script>";
                    exit();
                }
            } else {
                echo "<script>alert('Current password is incorrect!'); window.history.back();</script>";
                exit();
            }
        } catch(PDOException $e) {
            echo "<script>alert('Error changing password: " . $e->getMessage() . "'); window.history.back();</script>";
            exit();
        }
    }
}

// Check for success message from session
if (isset($_SESSION['modal_show'])) {
    $showModal = true;
    $modalTitle = $_SESSION['modal_title'];
    $modalMessage = $_SESSION['modal_message'];
    unset($_SESSION['modal_show']);
    unset($_SESSION['modal_title']);
    unset($_SESSION['modal_message']);
}

// Fetch user data including academic information
try {
    $userQuery = "SELECT * FROM registration_tbl WHERE reg_id = :reg_id";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute([':reg_id' => $reg_id]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
} catch(PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Fetch requested documents with JOIN - UPDATED to include payment_confirmation and payment_id
try {
    $documentsQuery = "SELECT DISTINCT
                        dr.req_id,
                        dl.description,
                        dr.copies,
                        dr.amount,
                        rs.payment_status,
                        rs.progress,
                        rs.request_date,
                        p.payment_confirmation,
                        p.payment_id
                       FROM document_request_tbl dr
                       INNER JOIN request_student_tbl rs ON dr.req_id = rs.req_id
                       INNER JOIN document_list_tbl dl ON dr.document_id = dl.document_id
                       LEFT JOIN payment_info_tbl p ON rs.req_id = p.req_id
                       WHERE rs.reg_id = :reg_id
                       ORDER BY rs.request_date DESC, dr.docureq_id DESC";
    
    $documentsStmt = $conn->prepare($documentsQuery);
    $documentsStmt->execute([':reg_id' => $reg_id]);
    $documentsResult = $documentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    die("Error fetching documents: " . $e->getMessage());
}

// Pass variables to frontend
$conn = null; // Close connection
?>