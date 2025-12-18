<?php
session_start();

// ============================================
// CHECK IF USER IS LOGGED IN
// ============================================
if (!isset($_SESSION['reg_id'])) {
    echo "<script>
            alert('You must be logged in to submit a request.');
            window.location.href = 'login.php';
          </script>";
    exit();
}

// ============================================
// DATABASE CONNECTION
// ============================================
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'tupdb';

// Create a connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
date_default_timezone_set('Asia/Manila');

// ============================================
// PROCESS STUDENT REQUEST FORM
// ============================================

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get reg_id FROM SESSION
    $reg_id = $_SESSION['reg_id'];
    
    // ============================================
    // CHECK DAILY REQUEST LIMIT (2 requests per 24 hours)
    // ============================================
    $limit_check_sql = "SELECT COUNT(*) as request_count, MIN(request_date) as earliest_request 
                        FROM request_student_tbl 
                        WHERE reg_id = ? 
                        AND request_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $limit_stmt = $conn->prepare($limit_check_sql);
    $limit_stmt->bind_param("i", $reg_id);
    $limit_stmt->execute();
    $limit_result = $limit_stmt->get_result();
    $limit_data = $limit_result->fetch_assoc();
    
    $request_count = $limit_data['request_count'];
    $earliest_request = $limit_data['earliest_request'];
    
    // If user has reached the limit of 2 requests in 24 hours
    if ($request_count >= 2) {
        // Calculate when they can request again
        $earliest_time = strtotime($earliest_request);
        $reset_time = $earliest_time + (24 * 60 * 60); // Add 24 hours
        $current_time = time();
        $wait_time = $reset_time - $current_time;
        
        // Convert wait time to hours and minutes
        $hours_remaining = floor($wait_time / 3600);
        $minutes_remaining = floor(($wait_time % 3600) / 60);
        
        echo "<script>
                alert('Request limit exceeded. You have reached the maximum of 2 document requests within 24 hours.\\n\\nPlease try again in approximately " . $hours_remaining . " hour(s) and " . $minutes_remaining . " minute(s).');
                window.location.href = 'Main.php';
              </script>";
        exit();
    }
    
    $limit_stmt->close();
    
    // ============================================
    // CONTINUE WITH FORM PROCESSING
    // ============================================
    
    // Get form data
    $full_name = isset($_POST['full-name']) ? trim($_POST['full-name']) : '';
    $student_number = isset($_POST['student-number']) ? trim($_POST['student-number']) : '';
    $course = isset($_POST['course']) ? trim($_POST['course']) : '';
    $college = isset($_POST['college']) ? trim($_POST['college']) : '';
    $purpose = isset($_POST['purpose']) ? trim($_POST['purpose']) : '';
    $contact_number = isset($_POST['contact-number']) ? trim($_POST['contact-number']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    // Validate required fields
    if (empty($full_name) || empty($student_number) || empty($course) || empty($college) || empty($purpose)) {
        echo "<script>
                alert('Please fill out all required fields.');
                window.history.back();
              </script>";
        exit();
    }

    // Prepare SQL (use backticks for column names with hyphens)
    $sql = "INSERT INTO request_student_tbl 
            (`reg_id`, `full-name`, `student-number`, `course`, `college`, `purpose`, `contact-number`, `address`, `email`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    // Prepare statement
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("SQL error: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        "issssssss", 
        $reg_id,
        $full_name, 
        $student_number, 
        $course, 
        $college, 
        $purpose, 
        $contact_number, 
        $address, 
        $email
    );

    // Execute and check
    if ($stmt->execute()) {
        // Get the generated req_id
        $req_id = $conn->insert_id;
        
        // Store information in session for document selection page
        $_SESSION['req_id'] = $req_id;
        $_SESSION['student_name'] = $full_name;
        $_SESSION['student_number'] = $student_number;
        $_SESSION['contact_number'] = $contact_number;
        $_SESSION['address'] = $address;
        $_SESSION['email'] = $email;
        $_SESSION['course'] = $course;
        $_SESSION['college'] = $college;
        $_SESSION['purpose'] = $purpose;
        
        // Show remaining requests for the day
        $remaining_requests = 2 - ($request_count + 1);
        if ($remaining_requests > 0) {
            echo "<script>
                    alert('Request submitted successfully!\\n\\nYou have " . $remaining_requests . " request(s) remaining for today.');
                  </script>";
        } else {
            echo "<script>
                    alert('Request submitted successfully!\\n\\nYou have used all your requests for today. You can submit again after 24 hours.');
                  </script>";
        }
        
        // Redirect to document selection page
        header('Location: docu_list.html');
        exit();
    } else {
        echo "<script>
                alert('Error submitting request: " . addslashes($stmt->error) . "');
                window.history.back();
              </script>";
    }

    $stmt->close();
} else {
    // If accessed directly without POST, redirect to form
    header('Location: request_form.html');
    exit();
}

$conn->close();
?>