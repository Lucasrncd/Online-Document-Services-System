<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "cyberiondb";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form is submitteda
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['studentEmail']);
    $pass = trim($_POST['password']);

    // Prevent SQL Injection
    $email = $conn->real_escape_string($email);
    $pass = $conn->real_escape_string($pass);

    // Check if email exists
    $checkEmail = "SELECT * FROM registration_tbl WHERE email='$email'";
    $emailResult = $conn->query($checkEmail);

    if ($emailResult->num_rows === 0) {
        // Email not found
        echo "
        <script>
            alert('Email not found! Please register or check your email.');
            window.location.href = 'login.php';
        </script>
        ";
        exit();
    } else {
        // Email exists — now check password
        $row = $emailResult->fetch_assoc();
        if ($row['password'] !== $pass) {
            echo "
            <script>
                alert('Incorrect password! Please try again.');
                window.location.href = 'login.php';
            </script>
            ";
            exit();
        } else {
            // Successful login
            $_SESSION['user_id'] = $row['reg_id'];
            $_SESSION['user_name'] = $row['firstName'];

            header('Location: Main.php');
            exit();
        }
    }
}

$conn->close();
?>
