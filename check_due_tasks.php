<?php
session_start();
include("connect.php");

header('Content-Type: application/json');

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$email = $_SESSION['email'];
$now = date('Y-m-d H:i:s');
$oneHourFromNow = date('Y-m-d H:i:s', strtotime('+1 hour'));
$twentyFourHoursFromNow = date('Y-m-d H:i:s', strtotime('+24 hours'));

// Get tasks that are due within the next 24 hours
$query = "SELECT id, task_name, due_date FROM tasks 
          WHERE user_email = ? 
          AND completed = 0 
          AND due_date BETWEEN ? AND ? 
          ORDER BY due_date ASC";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $email, $now, $twentyFourHoursFromNow);
$stmt->execute();
$result = $stmt->get_result();

$tasks = [];
while ($row = $result->fetch_assoc()) {
    $tasks[] = [
        'id' => $row['id'],
        'task_name' => htmlspecialchars($row['task_name']),
        'due_date' => $row['due_date']
    ];
}

echo json_encode(['success' => true, 'tasks' => $tasks]);
$stmt->close();
$conn->close();
?>