<?php
ob_start(); 
header('Content-Type: application/json; charset=utf-8');
session_start();

$host = 'localhost'; $username = 'root'; $password = ''; $database = 'tupdb';
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Database connection failed."]); exit;
}

$whereClause = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchTerm = $conn->real_escape_string($_GET['search']);
    $safeSearchTerm = "%{$searchTerm}%";
    $whereClause = "WHERE (rst.`req_id` LIKE '{$safeSearchTerm}' OR rst.`full-name` LIKE '{$safeSearchTerm}')";
}

try {
    // JOIN payment_info_tbl to get payment_confirmation
    $sql_requests = "
        SELECT 
            rst.`req_id`, rst.`full-name`, rst.`student-number`, rst.`course`, rst.`college`, 
            rst.`contact-number`, rst.`email`, rst.`address`, rst.`purpose`, 
            rst.`payment_status`, rst.`progress`, rst.`claim_date`,
            pit.`payment_confirmation`
        FROM request_student_tbl rst
        LEFT JOIN payment_info_tbl pit ON rst.req_id = pit.req_id
        {$whereClause}
        ORDER BY rst.`req_id` DESC";

    $result_requests = $conn->query($sql_requests);
    $requests = [];

    while ($row = $result_requests->fetch_assoc()) {
        $req_id = $row['req_id'];
        foreach($row as $key => $val) { $row[$key] = htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
        
        $requests[$req_id] = $row;
        $requests[$req_id]['documents_requested'] = [];
        $requests[$req_id]['total_copies'] = 0;
        $requests[$req_id]['final_amount'] = 0.00;
        
        // Default to 'Pending' if null (e.g. Unpaid or not yet approved or denied)
        $requests[$req_id]['payment_confirmation'] = $row['payment_confirmation'] ? $row['payment_confirmation'] : 'Pending';
    }

    $sql_documents = "SELECT drt.req_id, drt.copies, drt.amount, dlt.description FROM document_request_tbl drt JOIN document_list_tbl dlt ON drt.document_id = dlt.document_id";
    $result_documents = $conn->query($sql_documents);
    while ($row = $result_documents->fetch_assoc()) {
        if (isset($requests[$row['req_id']])) {
            $requests[$row['req_id']]['documents_requested'][] = htmlspecialchars($row['description']) . " <b>(" . $row['copies'] . "x)</b>";
            $requests[$row['req_id']]['total_copies'] += (int)$row['copies'];
            $requests[$row['req_id']]['final_amount'] += (float)$row['amount'];
        }
    }

    $sql_cancels = "SELECT req_id, reason FROM cancel_req_tbl";
    $result_cancels = $conn->query($sql_cancels);
    while ($row = $result_cancels->fetch_assoc()) {
        if (isset($requests[$row['req_id']])) {
            $requests[$row['req_id']]['cancel_reason'] = htmlspecialchars($row['reason']); 
        }
    }

    $final_data = array_values($requests);
    foreach ($final_data as &$request) {
        $request['documents_display'] = implode('<br>', $request['documents_requested']);
        $request['final_amount'] = number_format($request['final_amount'], 2);
    }

    ob_end_clean();
    echo json_encode(["status" => "success", "data" => $final_data]);
    
} catch (Exception $e) { ob_end_clean(); echo json_encode(["status" => "error", "message" => $e->getMessage()]); }
$conn->close();
?>