<?php
ob_start(); 
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

// Build WHERE clause
$whereClauses = ["is_alumni = 1"]; 

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $safeSearchTerm = "%{$searchTerm}%";
    $whereClauses[] = "(firstName LIKE '{$safeSearchTerm}' OR lastName LIKE '{$safeSearchTerm}' OR studentNumber LIKE '{$safeSearchTerm}' OR email LIKE '{$safeSearchTerm}')";
}

$finalWhere = " WHERE " . implode(' AND ', $whereClauses);

try {
    $sql = "
        SELECT 
            reg_id,
            CONCAT(firstName, ' ', COALESCE(middleName, ''), ' ', lastName) as fullName,
            studentNumber,
            course,
            college,
            contactNumber,
            email,
            address,
            yearGraduated,
            account_status
        FROM registration_tbl
        {$finalWhere}
        ORDER BY 
            CASE 
                WHEN account_status = 'Pending' THEN 1
                WHEN account_status = 'Approved' THEN 2
                WHEN account_status = 'Denied' THEN 3
                ELSE 4
            END,
            reg_id DESC
    ";

    $result = $conn->query($sql);
    $alumni = [];

    while ($row = $result->fetch_assoc()) {
        // Sanitize output
        foreach($row as $key => $val) { 
            $row[$key] = htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
        }
        $alumni[] = $row;
    }

    ob_end_clean();
    echo json_encode(["status" => "success", "data" => $alumni]);

} catch (Exception $e) { 
    ob_end_clean(); 
    echo json_encode(["status" => "error", "message" => $e->getMessage()]); 
}

$conn->close();
?>