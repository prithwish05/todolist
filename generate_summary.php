<?php
header('Content-Type: application/json');

// Database connection
include("connect.php");

session_start();

if (!isset($_SESSION['email'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$email = $_SESSION['email'];

try {
    // Get counts from database
    $todayCount = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date = CURDATE()");
    $upcomingCount = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date > CURDATE()");
    $completedCount = getCount($conn, $email, "SELECT COUNT(*) as count FROM completed_tasks WHERE user_email = ? AND DATE(completed_at) = CURDATE()");
    
    // Generate summary text
    $summary = "📊 Daily Task Summary - " . date('F j, Y') . "\n\n";
    $summary .= "✅ Completed tasks today: $completedCount\n";
    $summary .= "📅 Today's tasks: $todayCount\n";
    $summary .= "⏳ Upcoming tasks: $upcomingCount\n\n";
    
    // Get high priority tasks
    $stmt = $conn->prepare("SELECT task_name, due_date FROM tasks WHERE user_email = ? AND priority = 'High' AND due_date >= CURDATE() ORDER BY due_date LIMIT 3");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $highPriority = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($highPriority)) {
        $summary .= "⚠️ High Priority Tasks:\n";
        foreach ($highPriority as $task) {
            $dueDate = date('M j', strtotime($task['due_date']));
            $summary .= "• {$task['task_name']} (Due: $dueDate)\n";
        }
        $summary .= "\n";
    }
    
    // Get today's completed tasks
    $stmt = $conn->prepare("SELECT task_name, description, completed_at FROM completed_tasks WHERE user_email = ? AND DATE(completed_at) = CURDATE() ORDER BY completed_at DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $completedTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (!empty($completedTasks)) {
        $summary .= "🎉 Tasks Completed Today:\n";
        foreach ($completedTasks as $task) {
            $time = date('g:i a', strtotime($task['completed_at']));
            $summary .= "• {$task['task_name']}";
            if (!empty($task['description'])) {
                $summary .= ": {$task['description']}";
            }
            $summary .= " (Completed at $time)\n";
        }
    } else {
        $summary .= "No tasks completed today yet. Keep going! 💪\n";
    }
    
    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'completed_tasks' => $completedTasks // Optional: send the data separately if needed
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Reuse your getCount function
function getCount($conn, $email, $query) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $count = $res->fetch_assoc()['count'] ?? 0;
    $stmt->close();
    return $count;
}
