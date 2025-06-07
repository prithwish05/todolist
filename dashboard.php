<?php
ini_set("display_errors", "1");
error_reporting(E_ALL);
session_start();
include("connect.php");

// Initialize variables with defaults
$userName = "User";
$section = 'today';
$email = '';

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

$email = trim($_SESSION['email']);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add new task
    if (isset($_POST['task_name'])) {
        $taskName = trim($_POST['task_name']);
        $description = trim($_POST['description']);
        $dueDate = $_POST['due_date'];
        $priority = $_POST['priority'] ?? 'Medium';
        
        $stmt = $conn->prepare("INSERT INTO tasks (user_email, task_name, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $email, $taskName, $description, $dueDate, $priority);
        
        header("Location: dashboard.php?section=" . ($_POST['section'] ?? 'today'));
        $stmt->execute();
        exit;
    }
    
    // Handle edit request
if (isset($_POST['edit_task_id'])) {
    $taskId = $_POST['edit_task_id'];
    $section = $_POST['section'] ?? 'today';
    
    // Store in session for the edit form
    $_SESSION['edit_task_id'] = $taskId;
    $_SESSION['edit_section'] = $section;
  
    // Redirect with edit flag
    header("Location: dashboard.php?section=$section&edit=1");
    exit;
}

// Handle task update
if (isset($_POST['update_task_id'])) {
    $taskId = $_POST['update_task_id'];
    $taskName = trim($_POST['task_name']);
    $description = trim($_POST['description']);
    $dueDate = $_POST['due_date'];
    $section = $_POST['section'] ?? 'today';
    
    $stmt = $conn->prepare("UPDATE tasks SET task_name = ?, description = ?, due_date = ? WHERE id = ? AND user_email = ?");
    $stmt->bind_param("sssis", $taskName, $description, $dueDate, $taskId, $email);
    $stmt->execute();
    $stmt->close();
    
    // Clear session variables
    unset($_SESSION['edit_task_id']);
    unset($_SESSION['edit_section']);
    
    // Redirect back to the current section
    header("Location: dashboard.php?section=$section");
    exit;
}

    // Complete task
    if (isset($_POST['complete_task_id'])) {
    $taskId = $_POST['complete_task_id'];
    
    // Get task details
    $selectStmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_email = ?");
    if (!$selectStmt) {
        die("Prepare failed: " . $conn->error);
    }
    $selectStmt->bind_param("is", $taskId, $email);
    if (!$selectStmt->execute()) {
        die("Execute failed: " . $selectStmt->error);
    }
    $task = $selectStmt->get_result()->fetch_assoc();
    $selectStmt->close();
    
    if ($task) {
        // Move to completed_tasks
        $insertStmt = $conn->prepare("INSERT INTO completed_tasks (user_email, task_name, description, due_date, completed_at) VALUES (?, ?, ?, ?, NOW())");
        if (!$insertStmt) {
            die("Prepare failed: " . $conn->error);
        }
        $insertStmt->bind_param("ssss", $email, $task['task_name'], $task['description'], $task['due_date']);
        if (!$insertStmt->execute()) {
            die("Execute failed: " . $insertStmt->error);
        }
        $insertStmt->close();
            
            // Delete from tasks
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->bind_param("i", $taskId);
            $stmt->execute();
        }
        
        header("Location: dashboard.php?section=" . ($_GET['section'] ?? 'today'));
        exit;
    }
    
    // Delete task
    if (isset($_POST['delete_task_id'])) {
        $taskId = $_POST['delete_task_id'];
        
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_email = ?");
        $stmt->bind_param("is", $taskId, $email);
        $stmt->execute();
        
        header("Location: dashboard.php?section=" . ($_GET['section'] ?? 'today'));
        exit;
    }
    //Sorting
    // Add this near the top of your PHP code (after the database connection)
$sort = $_GET['sort'] ?? '';

// Then modify your task fetching sections:

if ($section === 'today') {
    $query = "SELECT * FROM tasks WHERE user_email = ? AND due_date = CURDATE()";
    
    // Add sorting
    switch ($sort) {
        case 'due_date':
            $query .= " ORDER BY due_date ASC";
            break;
        case 'due_date_desc':
            $query .= " ORDER BY due_date DESC";
            break;
        case 'priority':
            $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')";
            break;
        case 'priority_desc':
            $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')";
            break;
        case 'completed':
            $query .= " ORDER BY completed DESC";
            break;
        case 'incomplete':
            $query .= " ORDER BY completed ASC";
            break;
        default:
            $query .= " ORDER BY id DESC"; // Default sorting
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $todayTasks = $stmt->get_result();
    $stmt->close();
} 
elseif ($section === 'upcoming') {
    $todayDate = date('Y-m-d');
    $query = "SELECT * FROM tasks WHERE user_email = ? AND due_date > ?";
    
    // Add sorting
    switch ($sort) {
        case 'due_date':
            $query .= " ORDER BY due_date ASC";
            break;
        case 'due_date_desc':
            $query .= " ORDER BY due_date DESC";
            break;
        case 'priority':
            $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')";
            break;
        case 'priority_desc':
            $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')";
            break;
        case 'completed':
            $query .= " ORDER BY completed DESC";
            break;
        case 'incomplete':
            $query .= " ORDER BY completed ASC";
            break;
        default:
            $query .= " ORDER BY due_date ASC"; // Default sorting for upcoming
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $email, $todayDate);
    $stmt->execute();
    $upcomingTasks = $stmt->get_result();
    $stmt->close();
} 
elseif ($section === 'completed') {
    $query = "SELECT * FROM completed_tasks WHERE user_email = ?";
    
    // Add sorting
    switch ($sort) {
        case 'due_date':
            $query .= " ORDER BY due_date ASC";
            break;
        case 'due_date_desc':
            $query .= " ORDER BY due_date DESC";
            break;
        default:
            $query .= " ORDER BY completed_at DESC"; // Default sorting for completed
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $completedTasks = $stmt->get_result();
    $stmt->close();
}
}

// Fetch user info
$stmt = $conn->prepare("SELECT name FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $userName = htmlspecialchars($row['name']);
}
$stmt->close();

// Get counts helper
function getCount($conn, $email, $query, $params = []) {
    $stmt = $conn->prepare($query);
    if ($params) {
        $types = str_repeat("s", count($params));
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $count = 0;
    if ($res && $row = $res->fetch_assoc()) {
        $count = $row['count'];
    }
    $stmt->close();
    return $count;
}

$countToday = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date = CURDATE()");
$countUpcoming = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date > CURDATE()");
$countCompleted = getCount($conn, $email, "SELECT COUNT(*) as count FROM completed_tasks WHERE user_email = ?");

// Determine active section
$section = $_GET['section'] ?? 'today';

// Fetch tasks for the active section
$todayTasks = null;
$upcomingTasks = null;
$completedTasks = null;

if ($section === 'today') {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE user_email = ? AND due_date = CURDATE() ORDER BY id DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $todayTasks = $stmt->get_result();
    $stmt->close();
} elseif ($section === 'upcoming') {
    $todayDate = date('Y-m-d');
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE user_email = ? AND due_date > ? ORDER BY due_date ASC");
    $stmt->bind_param("ss", $email, $todayDate);
    $stmt->execute();
    $upcomingTasks = $stmt->get_result();
    $stmt->close();
} elseif ($section === 'completed') {
    $stmt = $conn->prepare("SELECT * FROM completed_tasks WHERE user_email = ? ORDER BY completed_at DESC");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $completedTasks = $stmt->get_result();
    $stmt->close();
}
?>

<!-- YOUR EXACT ORIGINAL HTML/CSS (NO CHANGES) -->
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="dashboard.css">
<title>Dashboard</title>
</head>
<body>

<div class="sidebar">
    <div class="profile">
        <img src="/taskroom/img/profile.jpeg" alt="User Image"  style="height: 160px; width: 170px;"/>

        <p>Hello, <?= $userName ?> 🙂</p>
        <small><?= htmlspecialchars($email) ?></small>
    </div>

    <nav class="sidebar-nav" style="padding-top: 60px;">
        <a href="create_task.php">+ Create Task</a>
        <a href="view_tasks.php">Due Tasks</a>
    </nav>
    <form method="" action="" style="">
        <button class="logout-btn" type="">Edit Profile</button>
    </form>
    <form method="POST" action="logout.php">
        <button class="logout-btn" type="submit">Logout</button>
    </form>
</div>

<div class="main-content">
    <header>
        <h1>Welcome back, <?= $userName ?> 👋</h1>
        <div class="header-actions">
            <span id="date-time"></span>
        </div>
    </header>

    <div class="tabs">
        <a href="dashboard.php?section=today" class="tab <?= $section === 'today' ? 'active' : '' ?>">
            Today <span class="count"><?= $countToday ?></span>
        </a>
        <a href="dashboard.php?section=upcoming" class="tab <?= $section === 'upcoming' ? 'active' : '' ?>">
            Upcoming <span class="count"><?= $countUpcoming ?></span>
        </a>
        <a href="dashboard.php?section=completed" class="tab <?= $section === 'completed' ? 'active' : '' ?>">
            Completed <span class="count"><?= $countCompleted ?></span>
        </a>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
    <?php if ($section === 'today' || $section === 'upcoming'): ?>
        <button onclick="openAddTaskModal()" style="background: #4CAF50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">+ Add Task</button>
    <?php endif; ?>
    
    <button onclick="toggleSortOptions()" style="background: #2196F3; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">Sort Tasks</button>
</div>
   <div id="sorting-options" class="sorting-options" style="display: none; margin-bottom: 15px;">
    <select id="sort-by" class="form-select">
        <option value="">Default Order</option>
        <option value="due_date">Due Date (Soonest First)</option>
        <option value="due_date_desc">Due Date (Latest First)</option>
        <option value="priority">Priority (High > Medium > Low)</option>
        <option value="priority_desc">Priority (Low > Medium > High)</option>
        <option value="completed">Completion Status (Completed First)</option>
        <option value="incomplete">Completion Status (Incomplete First)</option>
    </select>
</div>

    <div class="task-list">
    <?php if ($section === 'today'): ?>
        <?php if ($todayTasks && $todayTasks->num_rows > 0): ?>
            <table class="task-table">
                <thead>
                    <tr>
                        <th width="5%"style="text-align: center;">#</th>
                        <th width="50%"style="text-align: center;">Task Details</th>
                        <th width="10%" style="text-align: center;">Priority</th>
                        <th width="10%"style="text-align: center;">Edit</th>
                        <th width="10%"style="text-align: center;">Delete</th>
                        <th width="10%"style="text-align: center;">Complete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php while ($task = $todayTasks->fetch_assoc()): ?>
                    <tr>
                        <td><?= $counter ?></td>
                        <td>
                            <div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
                            <div class="task-desc"><?= htmlspecialchars($task['description']) ?></div>
                        </td>
                         <td style="text-align: center;">
                            <span class="priority-<?= strtolower($task['priority']) ?>">
                                <?= htmlspecialchars($task['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" action="dashboard.php">
                                <input type="hidden" name="edit_task_id" value="<?= $task['id'] ?>">
                                <input type="hidden" name="section" value="<?= $section ?>">
                                <button class="action-btn edit-btn" type="submit" style="text-align: center;">Edit</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <input type="hidden" name="delete_task_id" value="<?= $task['id'] ?>">
                                <button class="action-btn delete-btn" type="submit"style="text-align: center;">Delete</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="complete_task_id" value="<?= $task['id'] ?>">
                                <button class="action-btn complete-btn" type="submit"style="text-align: center;">Complete</button>
                            </form>
                        </td>
                    </tr>
                    <?php $counter++; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-tasks">All tasks completed for today! 🎉</div>
        <?php endif; ?>

    <?php elseif ($section === 'upcoming'): ?>
        <?php if ($upcomingTasks && $upcomingTasks->num_rows > 0): ?>
            <table class="task-table">
                <thead>
                    <tr>
                        <th width="5%" style="text-align: center;">#</th>
                        <th width="50%"style="text-align: center;">Task Details</th>
                        <th width="10%"style="text-align: center;">Due Date</th>
                        <th width="10%"style="text-align: center;">Edit</th>
                        <th width="10%"style="text-align: center;">Delete</th>
                        <th width="10%"style="text-align: center;">Complete</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php while ($task = $upcomingTasks->fetch_assoc()): ?>
                    <tr>
                        <td><?= $counter ?></td>
                        <td>
                            <div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
                            <div class="task-desc"><?= htmlspecialchars($task['description']) ?></div>
                        </td>
                        <td class="task-date"><?= date("M j, Y", strtotime($task['due_date'])) ?></td>
                        <td>
                            <form method="POST" action="dashboard.php">
                                <input type="hidden" name="edit_task_id" value="<?= $task['id'] ?>">
                                <input type="hidden" name="section" value="<?= $section ?>">
                                <button class="action-btn edit-btn" type="submit" style="text-align: center;">Edit</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <input type="hidden" name="delete_task_id" value="<?= $task['id'] ?>">
                                <button class="action-btn delete-btn" type="submit"style="text-align: center;">Delete</button>
                            </form>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="complete_task_id" value="<?= $task['id'] ?>">
                                <button class="action-btn complete-btn" type="submit"style="text-align: center;">Complete</button>
                            </form>
                        </td>
                    </tr>
                    <?php $counter++; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-tasks">No upcoming tasks found. Enjoy your free time! 😊</div>
        <?php endif; ?>

    <?php elseif ($section === 'completed'): ?>
        <?php if ($completedTasks && $completedTasks->num_rows > 0): ?>
            <table class="task-table">
                <thead>
                    <tr>
                        <th width="5%"style="text-align: center;">#</th>
                        <th width="60%"style="text-align: center;">Task Details</th>
                        <th width="35%"style="text-align: center;">Completed On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $counter = 1; ?>
                    <?php while ($task = $completedTasks->fetch_assoc()): ?>
                    <tr>
                        <td><?= $counter ?></td>
                        <td>
                            <div class="task-name"><?= htmlspecialchars($task['task_name']) ?></div>
                            <div class="task-desc"><?= htmlspecialchars($task['description']) ?></div>
                        </td>
                        <td class="task-date"><?= date("M j, Y g:i A", strtotime($task['completed_at'])) ?></td>
                    </tr>
                    <?php $counter++; ?>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-tasks">No tasks completed yet. Get started! 💪</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>

<div id="addTaskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center;">
  <div style="background: white; padding: 20px; border-radius: 8px; width: 300px;">
    <h3 style="margin-top: 0;">Add New Task</h3>
    <form method="POST" action="">
      <label>Title:</label><br>
      <input type="text" name="task_name" required style="width: 100%; margin-bottom: 10px;"><br>

      <label>Description:</label><br>
      <textarea name="description" required style="width: 100%; margin-bottom: 10px;"></textarea><br>

            <label>Priority:</label><br>
        <select name="priority" style="width: 100%; margin-bottom: 10px;">
        <option value="High">High</option>
        <option value="Medium" selected>Medium</option>
        <option value="Low">Low</option>
        </select><br>

      <label>Due Date:</label><br>
      <input type="date" name="due_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; margin-bottom: 10px;"><br>

      <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

      <div style="text-align: right;">
        <button type="button" onclick="closeAddTaskModal()" style="margin-right: 10px;">Cancel</button>
        <button type="submit" style="background: #4CAF50; color: white; border: none; padding: 6px 12px; border-radius: 4px;">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Task Modal -->
<div id="editTaskModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center;">
  <div style="background: white; padding: 20px; border-radius: 8px; width: 300px;">
    <h3 style="margin-top: 0;">Edit Task</h3>
    <?php
    if (isset($_SESSION['edit_task_id'])) {
        $editStmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
        $editStmt->bind_param("i", $_SESSION['edit_task_id']);
        $editStmt->execute();
        $editTask = $editStmt->get_result()->fetch_assoc();
        $editStmt->close();
    }
    ?>
    <form method="POST" action="dashboard.php">
      <input type="hidden" name="update_task_id" value="<?= $_SESSION['edit_task_id'] ?? '' ?>">
      <input type="hidden" name="section" value="<?= $_SESSION['edit_section'] ?? 'today' ?>">
      
      <label>Title:</label><br>
      <input type="text" name="task_name" value="<?= htmlspecialchars($editTask['task_name'] ?? '') ?>" required style="width: 100%; margin-bottom: 10px;"><br>

      <label>Description:</label><br>
      <textarea name="description" required style="width: 100%; margin-bottom: 10px;"><?= htmlspecialchars($editTask['description'] ?? '') ?></textarea><br>

      <label>Due Date:</label><br>
      <input type="date" name="due_date" value="<?= htmlspecialchars($editTask['due_date'] ?? date('Y-m-d')) ?>" required style="width: 100%; margin-bottom: 10px;"><br>

      <div style="text-align: right; margin-top: 15px;">
        <button type="button" onclick="closeEditTaskModal()" style="margin-right: 10px;">Cancel</button>
        <button type="submit" style="background: #4CAF50; color: white; padding: 6px 12px; border: none; border-radius: 4px;">Update</button>
      </div>
    </form>
  </div>
</div>
<script src="dashboard.js"></script>

</body>
</html>