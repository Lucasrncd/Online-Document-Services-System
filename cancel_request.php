<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['reg_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "tupdb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Processing cancellation request
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // input
    $req_id = trim($_POST['ref_num'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $logged_in_reg_id = $_SESSION['reg_id'];

    if (empty($req_id)) {
        header('Location: status.html');
        exit();
    }

    // First, check if the request belongs to the logged-in user
    $check_owner_query = "SELECT `reg_id`, `payment_status` FROM `request_student_tbl` WHERE `req_id` = ?";
    $stmt_owner = $conn->prepare($check_owner_query);
    
    if (!$stmt_owner) {
        header('Location: status.html');
        exit();
    }
    
    $stmt_owner->bind_param("i", $req_id);
    $stmt_owner->execute();
    $result_owner = $stmt_owner->get_result();
    $row_owner = $result_owner->fetch_assoc();
    $stmt_owner->close();

    if (!$row_owner) {
        // Request ID is not found in the request table
        header('Location: status.html?search=not_found');
        exit();
    }

    //SECURITY 
    // Check if the request belongs to the logged-in user
    if ($row_owner['reg_id'] != $logged_in_reg_id) {
        header('Location: status.html?cancel=unauthorized');
        exit();
    }

    // Check if request is already cancelled by looking in `cancel_req_tbl`
    $check_cancelled_query = "SELECT req_id FROM `cancel_req_tbl` WHERE `req_id` = ?";
    $stmt_cancelled = $conn->prepare($check_cancelled_query);
    
    if (!$stmt_cancelled) {
        header('Location: status.html');
        exit();
    }
    
    $stmt_cancelled->bind_param("i", $req_id);
    $stmt_cancelled->execute();
    $result_cancelled = $stmt_cancelled->get_result();
    
    if ($result_cancelled->num_rows > 0) {
        $stmt_cancelled->close();
        header('Location: status.html?cancel=already_cancelled');
        exit();
    }
    $stmt_cancelled->close();

    $current_status = $row_owner['payment_status'];

    if ($current_status === 'Paid') {
        // CANCELLATION FAILED (Already 'Paid' in the request table)
        header('Location: status.html?cancel=paid');
        exit();

    } else {
        // CANCELLATION IS APPROVED (If not Paid or haven't been cancelled yet)
        $conn->begin_transaction(); 

        try {
            // INSERT the cancellation log record into `cancel_req_tbl`. 
            $insert_query = "INSERT INTO `cancel_req_tbl` (`req_id`, `reason`) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($insert_query);
            
            if (!$stmt_insert) {
                throw new Exception("Error preparing INSERT statement for cancel_req_tbl: " . $conn->error);
            }
            
            $stmt_insert->bind_param("is", $req_id, $reason);

            if (!$stmt_insert->execute()) {
                 throw new Exception("Error logging cancellation to cancel_req_tbl: " . $stmt_insert->error);
            }
            $stmt_insert->close();

            // Update progress to 'Cancelled' in request_student_tbl
            $update_query = "UPDATE `request_student_tbl` SET `progress` = 'Cancelled' WHERE `req_id` = ?";
            $stmt_update = $conn->prepare($update_query);
            
            if (!$stmt_update) {
                throw new Exception("Error preparing UPDATE statement: " . $conn->error);
            }
            
            $stmt_update->bind_param("i", $req_id);
            
            if (!$stmt_update->execute()) {
                throw new Exception("Error updating progress status: " . $stmt_update->error);
            }
            $stmt_update->close();

            // Commit transaction if both operations succeeded
            $conn->commit();
            
            // Success - redirect to success modal
            header('Location: status.html?cancel=success');
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            header('Location: status.html');
            exit();
        }
    }
} else {
    header('Location: status.html');
    exit();
}

$conn->close();
?>