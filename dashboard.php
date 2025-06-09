<?php
// INITIALIZATION & CONFIGURATION
ini_set("display_errors", "1");
error_reporting(E_ALL);
date_default_timezone_set('Asia/Kolkata');

// First validate JWT
require 'validate_jwt.php';
try {
    $user = validateJWT();
    $email = $user->email;
    $userName = $user->name;
    $userId = $user->user_id;
} catch (Exception $e) {
    // If JWT validation fails, redirect to login
    header("Location: index.php?error=session_expired");
    exit();
}

// Also check session for additional security
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=session_expired");
    exit();
}

include("connect.php");

// DEFAULT VARIABLES
$userName = $_SESSION['name'] ?? 'User';
$section = 'today';
$email = $_SESSION['email'] ?? '';
/*------------------------------------------
  PAGINATION CONFIGURATION
------------------------------------------*/
$perPage = 10; // Number of items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $perPage;

/*------------------------------------------
  AUTHENTICATION CHECK
------------------------------------------*/
if (!isset($_SESSION['email'])) {
    header("Location: dashboard.php");
    exit();
}

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

$email = trim($_SESSION['email']);  // Get email from session


/*------------------------------------------
  EDIT PROFILE HANDLER
------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    
    // Validate inputs
    if (empty($newName) || empty($newEmail)) {
        $_SESSION['profile_error'] = "Name and email cannot be empty";
    } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_error'] = "Invalid email format";
    } else {
        try {
            // Check if email is being changed to one that already exists
            if ($newEmail !== $email) {
                $checkStmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
                $checkStmt->bind_param("s", $newEmail);
                $checkStmt->execute();
                $checkStmt->store_result();
                
                if ($checkStmt->num_rows > 0) {
                    $_SESSION['profile_error'] = "Email already in use by another account";
                    $checkStmt->close();
                    header("Location: dashboard.php");
                    exit;
                }
                $checkStmt->close();
            }
            
            // Update profile
            $stmt = $conn->prepare("UPDATE user SET name = ?, email = ? WHERE email = ?");
            $stmt->bind_param("sss", $newName, $newEmail, $email);
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                $_SESSION['profile_success'] = "Profile updated successfully";
                $_SESSION['email'] = $newEmail; // Update session email
                $email = $newEmail; // Update current script's email variable
                $userName = $newName; // Update current script's username
            } else {
                $_SESSION['profile_error'] = "No changes were made";
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['profile_error'] = "Error updating profile: " . $e->getMessage();
        }
    }
    
    header("Location: dashboard.php");
    exit;
}


/*------------------------------------------
  FORM SUBMISSION HANDLING
------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    /*-- TASK UPDATE HANDLER --*/
    if (isset($_POST['update_task_id'])) {
        // Get form data
        $taskId = $_POST['update_task_id'];
        $taskName = trim($_POST['task_name']);
        $description = trim($_POST['description']);
        $dueDate = $_POST['due_date'];
        $priority = $_POST['priority'] ?? 'Medium';
        $section = $_POST['section'] ?? 'today';
        
        // Prepare and execute update query
        $stmt = $conn->prepare("UPDATE tasks SET task_name = ?, description = ?, due_date = ?, priority = ? WHERE id = ? AND user_email = ?");
        $stmt->bind_param("ssssis", $taskName, $description, $dueDate, $priority, $taskId, $email);
        $stmt->execute();
        $stmt->close();
        
        // Clear edit session data
        unset($_SESSION['edit_task_id']);
        unset($_SESSION['edit_section']);
        
        // Redirect back to section
        header("Location: dashboard.php?section=$section");
        exit;
    }
    
    /*-- NEW TASK CREATION HANDLER --*/
    if (isset($_POST['task_name']) && !isset($_POST['update_task_id'])) {
        // Get form data
        $taskName = trim($_POST['task_name']);
        $description = trim($_POST['description']);
        $dueDate = $_POST['due_date'];
        $priority = $_POST['priority'] ?? 'Medium';
        
        // Prepare and execute insert query
        $stmt = $conn->prepare("INSERT INTO tasks (user_email, task_name, description, due_date, priority) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $email, $taskName, $description, $dueDate, $priority);
        $stmt->execute();
        $stmt->close();
        
        // Redirect back to section
        header("Location: dashboard.php?section=" . ($_POST['section'] ?? 'today'));
        exit;
    }
    
    /*-- TASK EDIT REQUEST HANDLER --*/
    if (isset($_POST['edit_task_id'])) {
        $taskId = $_POST['edit_task_id'];
        $section = $_POST['section'] ?? 'today';
        
        // Store edit data in session
        $_SESSION['edit_task_id'] = $taskId;
        $_SESSION['edit_section'] = $section;
        
        // Redirect to edit mode
        header("Location: dashboard.php?section=$section&edit=1");
        exit;
    }

    /*-- TASK COMPLETION HANDLER --*/
    if (isset($_POST['complete_task_id'])) {
        $taskId = $_POST['complete_task_id'];
        $section = $_GET['section'] ?? 'today';
        $action = $_POST['action'] ?? 'complete';

        if ($action === 'complete') {
            // Get task details
            $selectStmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_email = ?");
            $selectStmt->bind_param("is", $taskId, $email);
            $selectStmt->execute();
            $task = $selectStmt->get_result()->fetch_assoc();
            $selectStmt->close();

            if ($task) {
                // Check if task already exists in completed_tasks
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM completed_tasks WHERE user_email = ? AND task_name = ? AND description = ? AND due_date = ?");
                $checkStmt->bind_param("ssss", $email, $task['task_name'], $task['description'], $task['due_date']);
                $checkStmt->execute();
                $checkStmt->bind_result($count);
                $checkStmt->fetch();
                $checkStmt->close();

                // Add to completed tasks if not exists
                if ($count == 0) {
                    $insertStmt = $conn->prepare("INSERT INTO completed_tasks (user_email, task_name, description, due_date, completed_at) VALUES (?, ?, ?, ?, NOW())");
                    $insertStmt->bind_param("ssss", $email, $task['task_name'], $task['description'], $task['due_date']);
                    $insertStmt->execute();
                    $insertStmt->close();
                }

                // Handle based on section
                if ($section === 'upcoming') {
                    $deleteStmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
                    $deleteStmt->bind_param("i", $taskId);
                    $deleteStmt->execute();
                    $deleteStmt->close();
                } else {
                    $updateStmt = $conn->prepare("UPDATE tasks SET completed = 1 WHERE id = ?");
                    $updateStmt->bind_param("i", $taskId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
        } elseif ($action === 'incomplete') {
            // Remove from completed tasks
            $deleteStmt = $conn->prepare("DELETE FROM completed_tasks WHERE id = (SELECT id FROM completed_tasks WHERE user_email = ? AND task_name = (SELECT task_name FROM tasks WHERE id = ?) LIMIT 1)");
            $deleteStmt->bind_param("si", $email, $taskId);
            $deleteStmt->execute();
            $deleteStmt->close();

            // Mark as incomplete
            $updateStmt = $conn->prepare("UPDATE tasks SET completed = 0 WHERE id = ? AND user_email = ?");
            $updateStmt->bind_param("is", $taskId, $email);
            $updateStmt->execute();
            $updateStmt->close();
        }

        // Redirect back to section
        header("Location: dashboard.php?section=$section");
        exit;
    }
    
    /*-- TASK DELETION HANDLER --*/
    if (isset($_POST['delete_task_id'])) {
        $taskId = $_POST['delete_task_id'];
        
        // Delete task from database
        $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_email = ?");
        $stmt->bind_param("is", $taskId, $email);
        $stmt->execute();
        $stmt->close();
        
        // Redirect back to section
        header("Location: dashboard.php?section=" . ($_GET['section'] ?? 'today'));
        exit;
    }
}

/*------------------------------------------
  SORTING CONFIGURATION
------------------------------------------*/
$sort = $_GET['sort'] ?? '';  // Get sorting parameter from URL

/*------------------------------------------
  USER INFORMATION FETCH
------------------------------------------*/
$stmt = $conn->prepare("SELECT name FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $userName = htmlspecialchars($row['name']);  // Sanitize user name
}
$stmt->close();

/*------------------------------------------
  COUNT HELPER FUNCTION
------------------------------------------*/
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

/*------------------------------------------
  TASK COUNTS
------------------------------------------*/
$countToday = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date = CURDATE()");
$countUpcoming = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date > CURDATE()");
$countCompleted = getCount($conn, $email, "SELECT COUNT(*) as count FROM completed_tasks WHERE user_email = ?");
$countDueTasks = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date >= CURDATE() AND completed = 0");

/*------------------------------------------
  ACTIVE SECTION DETERMINATION
------------------------------------------*/
$section = $_GET['section'] ?? 'today';  // Get current section from URL
$validSections = ['today', 'upcoming', 'completed', 'due_tasks']; // Add 'due_tasks' to valid sections
if (!in_array($section, $validSections)) {
    $section = 'today'; // Fallback to today if invalid section
}

/*------------------------------------------
  TASK FETCHING LOGIC
------------------------------------------*/
$todayTasks = null;
$upcomingTasks = null;
$completedTasks = null;
$dueTasks = null;
$totalTasks = 0;

// TODAY TASKS
if ($section === 'today') {
    // Get total count for pagination
    $totalTasks = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date = CURDATE()");
    
    $query = "SELECT * FROM tasks WHERE user_email = ? AND due_date = CURDATE()";
    
    // Apply sorting
    switch ($sort) {
        case 'priority': $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')"; break;
        case 'priority_desc': $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')"; break;
        case 'completed': $query .= " ORDER BY completed DESC"; break;
        case 'incomplete': $query .= " ORDER BY completed ASC"; break;
        default: $query .= " ORDER BY id DESC";  // Default sorting
    }
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $email, $perPage, $offset);
    $stmt->execute();
    $todayTasks = $stmt->get_result();
    $stmt->close();
} 
// UPCOMING TASKS
elseif ($section === 'upcoming') {
    $todayDate = date('Y-m-d');
    // Get total count for pagination
    $totalTasks = getCount($conn, $email, "SELECT COUNT(*) as count FROM tasks WHERE user_email = ? AND due_date > ?", [$email, $todayDate]);
    
    $query = "SELECT * FROM tasks WHERE user_email = ? AND due_date > ?";
    
    // Apply sorting
    switch ($sort) {
        case 'due_date': $query .= " ORDER BY due_date ASC"; break;
        case 'due_date_desc': $query .= " ORDER BY due_date DESC"; break;
        case 'priority': $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')"; break;
        case 'priority_desc': $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')"; break;
        default: $query .= " ORDER BY due_date ASC";  // Default sorting
    }
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $email, $todayDate, $perPage, $offset);
    $stmt->execute();
    $upcomingTasks = $stmt->get_result();
    $stmt->close();
} 
// COMPLETED TASKS
elseif ($section === 'completed') {
    // Get total count for pagination
    $totalTasks = getCount($conn, $email, "SELECT COUNT(*) as count FROM completed_tasks WHERE user_email = ?");
    
    $query = "SELECT * FROM completed_tasks WHERE user_email = ?";
    
    // Apply sorting
    switch ($sort) {
        case 'due_date': $query .= " ORDER BY due_date ASC"; break;
        case 'due_date_desc': $query .= " ORDER BY due_date DESC"; break;
        case 'completed_at': $query .= " ORDER BY completed_at DESC"; break;
        case 'completed_at_desc': $query .= " ORDER BY completed_at ASC"; break;
        default: $query .= " ORDER BY completed_at DESC";  // Default sorting
    }
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $email, $perPage, $offset);
    $stmt->execute();
    $completedTasks = $stmt->get_result();
    $stmt->close();
}


// DUE TASKS (TODAY + UPCOMING) - ONLY INCOMPLETE
elseif ($section === 'due_tasks') {
    // Get total count for pagination
    $totalTasks = $countDueTasks;
    
    $query = "SELECT * FROM tasks WHERE user_email = ? AND due_date >= CURDATE() AND completed = 0";
    
    // Apply sorting
    switch ($sort) {
        case 'due_date': $query .= " ORDER BY due_date ASC"; break;
        case 'due_date_desc': $query .= " ORDER BY due_date DESC"; break;
        case 'priority': $query .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')"; break;
        case 'priority_desc': $query .= " ORDER BY FIELD(priority, 'Low', 'Medium', 'High')"; break;
        default: $query .= " ORDER BY due_date ASC";  // Default sorting (soonest first)
    }
    
    // Add pagination
    $query .= " LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sii", $email, $perPage, $offset);
    $stmt->execute();
    $dueTasks = $stmt->get_result();
    $stmt->close();
}


// Calculate total pages
$totalPages = ceil($totalTasks / $perPage);
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <!-- META TAGS AND RESOURCES -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Dashboard</title>
</head>
<ody>

<!-- MOBILE MENU TOGGLE BUTTON -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <span></span>
    <span></span>
    <span></span>
</button>

<!-- SIDEBAR NAVIGATION -->
<div class="sidebar collapsed" style="background-color: #FFFDD0; color: brown;">
    <!-- USER PROFILE SECTION -->
    <div class="profile">
        <?php
        // Get user's profile picture path from database
        $stmt = $conn->prepare("SELECT profile_picture FROM user WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();
        $profilePic = !empty($userData['profile_picture']) ? $userData['profile_picture'] : '/taskroom/img/profile.jpeg';
        ?>
        <img src="<?= htmlspecialchars($profilePic) ?>" alt="User Image" style="height: 160px; width: 170px; object-fit: cover; border-radius: 50%;"/>
        <p>Hello, <?= $userName ?> 🙂</p>
        <small style="color: brown"><?= htmlspecialchars($email) ?></small>
    </div>

    <!-- NAVIGATION LINKS -->
    <nav class="sidebar-nav" style="padding-top: 60px;">
        <a onclick="openAddTaskModal()" style="background-color: brown; color: #FFFDD0; ">+ Create Task</a>
        <a href="dashboard.php?section=due_tasks" style="background-color: brown; color: #FFFDD0; ">Due Tasks</a>
    </nav>
    
    <!-- ACTION BUTTONS -->
    <form method="" action="" style="padding-top: 75px;">
        <button class="logout-btn" type="button" id="editProfileBtn">Edit Profile</button>
    </form>
    <form method="POST" action="logout.php">
        <button class="logout-btn" type="submit">Logout</button>
    </form>
</div>

<!-- MAIN CONTENT AREA -->
<div class="main-content">
    <!-- HEADER SECTION -->
    <header>
        <h1 style="color: #FFFDD0;">Welcome back, <?= $userName ?> 👋</h1>
        <div class="header-actions">
            <span id="date-time" style="color:#FFFDD0"></span>
        </div>
    </header>

    <!-- SECTION TABS -->
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
        <a href="dashboard.php?section=due_tasks" class="tab <?= $section === 'due_tasks' ? 'active' : '' ?>">
            All Due Tasks <span class="count"><?= $countDueTasks ?></span>
        </a>
        <button id="generate-summary-btn" class="tab" style="background-color: #4CAF50; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
        Generate Daily Summary
        </button>
    </div>

    <!-- ACTION BUTTONS ROW -->
    <div style="margin-bottom: 20px; display: flex; gap: 10px;">
        <?php if ($section === 'today' || $section === 'upcoming'): ?>
            <button onclick="openAddTaskModal()" style="background: #4CAF50; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em;">+ Add Task</button>
        <?php endif; ?>
        
        <!-- Modified Sort Button that shows dropdown immediately -->
        <div style="position: relative;">
            <select id="sort-by" class="form-select" onchange="applySort()" 
                    style="background: #FFFDD0; color: brown; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 0.9em; appearance: none; -webkit-appearance: none; -moz-appearance: none; padding-right: 30px; width: 150px;">
                <option value="">Sort Tasks</option>
                <?php if ($section === 'today'): ?>
                    <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority (High > Medium > Low)</option>
                    <option value="priority_desc" <?= $sort === 'priority_desc' ? 'selected' : '' ?>>Priority (Low > Medium > High)</option>
                    <option value="completed" <?= $sort === 'completed' ? 'selected' : '' ?>>Completed</option>
                    <option value="incomplete" <?= $sort === 'incomplete' ? 'selected' : '' ?>>Incomplete</option>
                <?php elseif ($section === 'upcoming'): ?>
                    <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due Date (Soonest)</option>
                    <option value="due_date_desc" <?= $sort === 'due_date_desc' ? 'selected' : '' ?>>Due Date (Latest)</option>
                    <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Priority (High > Medium > Low)</option>
                    <option value="priority_desc" <?= $sort === 'priority_desc' ? 'selected' : '' ?>>Priority (Low > Medium > High)</option>
                <?php elseif ($section === 'completed'): ?>
                    <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due Date (Soonest)</option>
                    <option value="due_date_desc" <?= $sort === 'due_date_desc' ? 'selected' : '' ?>>Due Date (Latest)</option>
                    <option value="completed_at" <?= $sort === 'completed_at' ? 'selected' : '' ?>>Completed Date (Newest First)</option>
                    <option value="completed_at_desc" <?= $sort === 'completed_at_desc' ? 'selected' : '' ?>>Completed Date (Oldest First)</option>
                <?php endif; ?>
            </select>
            <!-- Custom dropdown arrow -->
            <div style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); pointer-events: none;">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="brown">
                    <path d="M7 10l5 5 5-5z"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- TASK LIST CONTAINER -->
    <div class="task-list" style="background-color: #FFFDD0;">
        <?php if ($section === 'today'): ?>
            <!-- TODAY'S TASKS SECTION -->
            <?php if ($todayTasks && $todayTasks->num_rows > 0): ?>
                <table class="task-table">
                    <!-- TABLE HEADER -->
                    <thead>
                        <tr>
                            <th width="5%" style="text-align: center;">#</th>
                            <th width="50%" style="text-align: center;">Task Details</th>
                            <th width="10%" style="text-align: center;">Priority</th>
                            <th width="10%" style="text-align: center;">Edit</th>
                            <th width="10%" style="text-align: center;">Delete</th>
                            <th width="10%" style="text-align: center;">Complete</th>
                        </tr>
                    </thead>
                    <!-- TABLE BODY -->
                    <tbody>
                        <?php $counter = ($page - 1) * $perPage + 1; ?>
                        <?php while ($task = $todayTasks->fetch_assoc()): ?>
                        <tr class="<?= $task['completed'] ? 'completed-task' : '' ?>" style="text-align: center;">
                            <td><?= $counter ?></td>
                            <td>
                                <div class="task-name" style="text-align: center;"><?= htmlspecialchars($task['task_name']) ?></div>
                                <div class="task-desc" style="text-align: center;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td style="text-align: center;">
                                <span class="priority-<?= strtolower($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- EDIT BUTTON FORM -->
                                <form method="POST" action="dashboard.php" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="edit_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="section" value="<?= $section ?>">
                                    <button class="action-btn edit-btn" type="submit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- DELETE BUTTON FORM -->
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="delete_task_id" value="<?= $task['id'] ?>">
                                    <button class="action-btn delete-btn" type="submit" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- COMPLETION TOGGLE FORM -->
                                <form method="POST" class="complete-form" id="complete-form-<?= $task['id'] ?>">
                                    <input type="hidden" name="complete_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $task['completed'] ? 'incomplete' : 'complete' ?>">
                                    <input type="checkbox" 
                                        id="complete-<?= $task['id'] ?>" 
                                        class="complete-checkbox" 
                                        <?= $task['completed'] ? 'checked' : '' ?>
                                        onchange="this.form.submit()">
                                    <label for="complete-<?= $task['id'] ?>" class="complete-label">
                                        <i class="fas fa-check-circle checked-icon"></i>
                                        <i class="fas fa-times-circle unchecked-icon"></i>
                                    </label>
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
            <!-- UPCOMING TASKS SECTION -->
            <?php if ($upcomingTasks && $upcomingTasks->num_rows > 0): ?>
                <table class="task-table">
                    <!-- TABLE HEADER -->
                    <thead>
                        <tr>
                            <th width="5%" style="text-align: center;">#</th>
                            <th width="50%" style="text-align: center;">Task Details</th>
                            <th width="10%" style="text-align: center;">Due Date</th>
                            <th width="10%" style="text-align: center;">Priority</th>
                            <th width="10%" style="text-align: center;">Edit</th>
                            <th width="10%" style="text-align: center;">Delete</th>
                            <th width="10%" style="text-align: center;">Complete</th>
                        </tr>
                    </thead>
                    <!-- TABLE BODY -->
                    <tbody>
                        <?php $counter = ($page - 1) * $perPage + 1; ?>
                        <?php while ($task = $upcomingTasks->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: center;"><?= $counter ?></td>
                            <td>
                                <div class="task-name" style="text-align: center;"><?= htmlspecialchars($task['task_name']) ?></div>
                                <div class="task-desc" style="text-align: center;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td class="task-date" style="text-align: center;"><?= date("M j, Y", strtotime($task['due_date'])) ?></td>
                            <td style="text-align: center;">
                                <span class="priority-<?= strtolower($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- EDIT BUTTON FORM -->
                                <form method="POST" action="dashboard.php" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="edit_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="section" value="<?= $section ?>">
                                    <button class="action-btn edit-btn" type="submit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- DELETE BUTTON FORM -->
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="delete_task_id" value="<?= $task['id'] ?>">
                                    <button class="action-btn delete-btn" type="submit" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- COMPLETION TOGGLE FORM -->
                                <form method="POST" class="complete-form" id="complete-form-<?= $task['id'] ?>">
                                    <input type="hidden" name="complete_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $task['completed'] ? 'incomplete' : 'complete' ?>">
                                    <input type="checkbox" 
                                        id="complete-<?= $task['id'] ?>" 
                                        class="complete-checkbox" 
                                        <?= $task['completed'] ? 'checked' : '' ?>
                                        onchange="this.form.submit()">
                                    <label for="complete-<?= $task['id'] ?>" class="complete-label">
                                        <i class="fas fa-check-circle checked-icon"></i>
                                        <i class="fas fa-times-circle unchecked-icon"></i>
                                    </label>
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
            <!-- COMPLETED TASKS SECTION -->
            <?php if ($completedTasks && $completedTasks->num_rows > 0): ?>
                <table class="task-table">
                    <!-- TABLE HEADER -->
                    <thead>
                        <tr>
                            <th width="5%" style="text-align: center;">#</th>
                            <th width="60%" style="text-align: center;">Task Details</th>
                            <th width="35%" style="text-align: center;">Completed On</th>
                        </tr>
                    </thead>
                    <!-- TABLE BODY -->
                    <tbody>
                        <?php $counter = ($page - 1) * $perPage + 1; ?>
                        <?php while ($task = $completedTasks->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: center;"><?= $counter ?></td>
                            <td>
                                <div class="task-name" style="text-align: center;"><?= htmlspecialchars($task['task_name']) ?></div>
                                <div class="task-desc" style="text-align: center;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td class="task-date" style="text-align: center;">
                                <?= date("M j, Y g:i A", strtotime($task['completed_at'])) ?>
                            </td>
                        </tr>
                        <?php $counter++; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>            
            <?php else: ?>
                <div class="no-tasks">No tasks completed yet. Get started! 💪</div>
            <?php endif; ?>


        <?php elseif ($section === 'due_tasks'): ?>
            <!-- DUE TASKS SECTION (ONLY INCOMPLETE) -->
            <?php if ($dueTasks && $dueTasks->num_rows > 0): ?>
                <table class="task-table">
                    <thead>
                        <tr>
                            <th width="5%" style="text-align: center;">#</th>
                            <th width="40%" style="text-align: center;">Task Details</th>
                            <th width="15%" style="text-align: center;">Due Date</th>
                            <th width="10%" style="text-align: center;">Priority</th>
                            <th width="10%" style="text-align: center;">Edit</th>
                            <th width="10%" style="text-align: center;">Delete</th>
                            <th width="10%" style="text-align: center;">Complete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = ($page - 1) * $perPage + 1; ?>
                        <?php while ($task = $dueTasks->fetch_assoc()): ?>
                        <tr style="text-align: center;">
                            <td><?= $counter ?></td>
                            <td>
                                <div class="task-name" style="text-align: center;"><?= htmlspecialchars($task['task_name']) ?></div>
                                <div class="task-desc" style="text-align: center;"><?= htmlspecialchars($task['description']) ?></div>
                            </td>
                            <td class="task-date" style="text-align: center;">
                                <?= date("M j, Y", strtotime($task['due_date'])) ?>
                                <?= $task['due_date'] == date('Y-m-d') ? '<span style="color:red;"> (Today)</span>' : '' ?>
                            </td>
                            <td style="text-align: center;">
                                <span class="priority-<?= strtolower($task['priority']) ?>">
                                    <?= htmlspecialchars($task['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <!-- EDIT BUTTON FORM -->
                                <form method="POST" action="dashboard.php" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="edit_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="section" value="<?= $section ?>">
                                    <button class="action-btn edit-btn" type="submit" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- DELETE BUTTON FORM -->
                                <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');" style="display: flex;justify-content: center;">
                                    <input type="hidden" name="delete_task_id" value="<?= $task['id'] ?>">
                                    <button class="action-btn delete-btn" type="submit" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <!-- COMPLETION TOGGLE FORM -->
                                <form method="POST" class="complete-form" id="complete-form-<?= $task['id'] ?>">
                                    <input type="hidden" name="complete_task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="action" value="complete">
                                    <input type="checkbox" 
                                        id="complete-<?= $task['id'] ?>" 
                                        class="complete-checkbox" 
                                        onchange="this.form.submit()">
                                    <label for="complete-<?= $task['id'] ?>" class="complete-label">
                                        <i class="fas fa-check-circle checked-icon"></i>
                                        <i class="fas fa-times-circle unchecked-icon"></i>
                                    </label>
                                </form>
                            </td>
                        </tr>
                        <?php $counter++; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-tasks">No due tasks found. You're all caught up! 😊</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>


    


        <!-- PAGINATION -->
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="dashboard.php?section=<?= $section ?>&sort=<?= $sort ?>&page=1" class="pagination-link" title="First Page">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="dashboard.php?section=<?= $section ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>" class="pagination-link" title="Previous">
                <i class="fas fa-angle-left"></i>
            </a>
        <?php else: ?>
            <span class="pagination-link disabled" title="First Page">
                <i class="fas fa-angle-double-left"></i>
            </span>
            <span class="pagination-link disabled" title="Previous">
                <i class="fas fa-angle-left"></i>
            </span>
        <?php endif; ?>
        
        <div class="pagination-pages">
            <?php 
            // Show page numbers
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            if ($startPage > 1) {
                echo '<span class="pagination-ellipsis">...</span>';
            }
            
            for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="dashboard.php?section=<?= $section ?>&sort=<?= $sort ?>&page=<?= $i ?>" 
                class="pagination-link <?= $i == $page ? 'active' : '' ?>" 
                title="Page <?= $i ?>">
                    <?= $i ?>
                </a>
            <?php endfor; 
            
            if ($endPage < $totalPages) {
                echo '<span class="pagination-ellipsis">...</span>';
            }
            ?>
        </div>
        
        <?php if ($page < $totalPages): ?>
            <a href="dashboard.php?section=<?= $section ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>" class="pagination-link" title="Next">
                <i class="fas fa-angle-right"></i>
            </a>
            <a href="dashboard.php?section=<?= $section ?>&sort=<?= $sort ?>&page=<?= $totalPages ?>" class="pagination-link" title="Last Page">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php else: ?>
            <span class="pagination-link disabled" title="Next">
                <i class="fas fa-angle-right"></i>
            </span>
            <span class="pagination-link disabled" title="Last Page">
                <i class="fas fa-angle-double-right"></i>
            </span>
        <?php endif; ?>
    </div>
    



    <!-- <button id="generate-summary-btn" class="btn btn-primary">Generate Daily Summary</button> -->

    <!-- <div id="summary-modal" class="modal">
        <div class="modal-content">
            <span class="close-summary">&times;</span>
            <h3>Your Daily Summary</h3>
            <div id="summary-content"></div>
            <button id="copy-summary" class="btn btn-secondary">Copy Summary</button>
        </div>
    </div> -->
</div>


<!-- Edit Profile Modal -->
<div id="editProfileModal" class="modal">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Edit Profile</h2>
        
        <?php if (isset($_SESSION['profile_error'])): ?>
            <div class="alert alert-error" style="color: red; margin-bottom: 15px;">
                <?= htmlspecialchars($_SESSION['profile_error']) ?>
            </div>
            <?php unset($_SESSION['profile_error']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['profile_success'])): ?>
            <div class="alert alert-success" style="color: green; margin-bottom: 15px;">
                <?= htmlspecialchars($_SESSION['profile_success']) ?>
            </div>
            <?php unset($_SESSION['profile_success']); ?>
        <?php endif; ?>
        
        <form id="profileForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="form-group">
                <label for="profile_picture">Profile Picture:</label>
                <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
                <small>Max 2MB (JPG, PNG, GIF)</small>
            </div>
            <div class="form-group">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($userName) ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" required>
            </div>

            <button type="submit" class="save-btn">Save Changes</button>
        </form>
    </div>
</div>

<!-- ADD TASK MODAL -->
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
      <input type="date" id="task-date" name="due_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; margin-bottom: 10px;"><br>

      <input type="hidden" name="section" value="<?= htmlspecialchars($section) ?>">

      <div style="text-align: right;">
        <button type="button" onclick="closeAddTaskModal()" style="margin-right: 10px;">Cancel</button>
        <button type="submit" style="background: #4CAF50; color: white; border: none; padding: 6px 12px; border-radius: 4px;">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT TASK MODAL -->
<div id="editTaskModal" style="<?= isset($_GET['edit']) ? 'display: flex;' : 'display: none;' ?> position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; justify-content: center; align-items: center;">
  <div style="background: white; padding: 20px; border-radius: 8px; width: 300px;">
    <h3 style="margin-top: 0;">Edit Task</h3>
    <?php
    if (isset($_SESSION['edit_task_id'])) {
        $editStmt = $conn->prepare("SELECT * FROM tasks WHERE id = ? AND user_email = ?");
        $editStmt->bind_param("is", $_SESSION['edit_task_id'], $email);
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

      <label>Priority:</label><br>
      <select name="priority" style="width: 100%; margin-bottom: 10px;">
        <option value="High" <?= ($editTask['priority'] ?? '') === 'High' ? 'selected' : '' ?>>High</option>
        <option value="Medium" <?= ($editTask['priority'] ?? 'Medium') === 'Medium' ? 'selected' : '' ?>>Medium</option>
        <option value="Low" <?= ($editTask['priority'] ?? '') === 'Low' ? 'selected' : '' ?>>Low</option>
      </select><br>

      <label>Due Date:</label><br>
      <input type="date" id="task-date" name="due_date" value="<?= date('Y-m-d') ?>" required style="width: 100%; margin-bottom: 10px;"><br>

      <div style="text-align: right; margin-top: 15px;">
        <button type="button" onclick="closeEditTaskModal()" style="margin-right: 10px;">Cancel</button>
        <button type="submit" name="update_task" style="background: #4CAF50; color: white; padding: 6px 12px; border: none; border-radius: 4px;">Update</button>
      </div>
    </form>
  </div>
</div>

<script src="dashboard.js"></script>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const summaryBtn = document.getElementById('generate-summary-btn');
    
    if (summaryBtn) {
        summaryBtn.addEventListener('click', async function() {
            summaryBtn.disabled = true;
            summaryBtn.textContent = 'Generating...';
            
            try {
                console.log('Making request to generate_summary.php');
                const response = await fetch('generate_summary.php');
                
                console.log('Response status:', response.status);
                
                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Server response error:', errorText);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Response data:', data);
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to generate summary');
                }
                
                // Create modal dynamically if it doesn't exist
                let modal = document.getElementById('summary-modal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'summary-modal';
                    modal.className = 'modal';
                    modal.innerHTML = `
                        <div class="modal-content">
                            <span class="close-summary">&times;</span>
                            <h3>Your Daily Summary</h3>
                            <div id="summary-content"></div>
                            <button id="copy-summary" class="btn btn-secondary">Copy Summary</button>
                        </div>
                    `;
                    document.body.appendChild(modal);
                }
                
                document.getElementById('summary-content').innerText = data.summary;
                modal.style.display = 'block';
                
                // Add close functionality
                document.querySelector('.close-summary').onclick = function() {
                    modal.style.display = 'none';
                };
                
                // Add copy functionality
                document.getElementById('copy-summary').onclick = function() {
                    navigator.clipboard.writeText(data.summary)
                        .then(() => {
                            this.textContent = 'Copied!';
                            setTimeout(() => {
                                this.textContent = 'Copy Summary';
                            }, 2000);
                        });
                };
                
            } catch (error) {
                console.error('Full error:', error);
                alert('Error: ' + error.message + '\nCheck console for details.');
            } finally {
                summaryBtn.disabled = false;
                summaryBtn.textContent = 'Generate Daily Summary';
            }
        });
    }

    
    // Close modal
    const closeModal = document.querySelector('.close-summary');
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            summaryModal.style.display = 'none';
        });
    }
    
    // Copy summary
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const summaryText = document.getElementById('summary-content').innerText;
            navigator.clipboard.writeText(summaryText)
                .then(() => {
                    copyBtn.textContent = 'Copied!';
                    setTimeout(() => {
                        copyBtn.textContent = 'Copy Summary';
                    }, 2000);
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                });
        });
    }
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === summaryModal) {
            summaryModal.style.display = 'none';
        }
    });
});



document.addEventListener('DOMContentLoaded', function() {
    // Get modal elements
    const modal = document.getElementById('editProfileModal');
    const btn = document.getElementById('editProfileBtn');
    const span = document.getElementsByClassName('close')[0];
    
    // When user clicks the button, open the modal
    if (btn) {
        btn.onclick = function() {
            modal.style.display = 'block';
        }
    }
    
    // When user clicks on (x), close the modal
    if (span) {
        span.onclick = function() {
            modal.style.display = 'none';
        }
    }
    
    // When user clicks anywhere outside the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
});


// Request notification permission when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if browser supports notifications
    if (!("Notification" in window)) {
        console.log("This browser does not support desktop notification");
    } else if (Notification.permission !== "denied") {
        // Request permission from user
        Notification.requestPermission().then(permission => {
            if (permission === "granted") {
                console.log("Notification permission granted");
                checkDueTasks(); // Check for due tasks immediately
                setInterval(checkDueTasks, 60000); // Then check every minute
            }
        });
    }
});

// Function to check for due tasks and show notifications
function checkDueTasks() {
    fetch('check_due_tasks.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.tasks.length > 0) {
                data.tasks.forEach(task => {
                    showTaskNotification(task);
                });
            }
        })
        .catch(error => console.error('Error:', error));
}

// Function to display a notification for a task
function showTaskNotification(task) {
    const now = new Date();
    const dueDate = new Date(task.due_date);
    const timeDiff = dueDate - now;
    const hoursDiff = Math.floor(timeDiff / (1000 * 60 * 60));
    
    let notificationText;
    if (hoursDiff <= 0) {
        notificationText = `"${task.task_name}" is due now!`;
    } else if (hoursDiff <= 24) {
        notificationText = `"${task.task_name}" is due in ${hoursDiff} hour${hoursDiff !== 1 ? 's' : ''}`;
    } else {
        const daysDiff = Math.floor(hoursDiff / 24);
        notificationText = `"${task.task_name}" is due in ${daysDiff} day${daysDiff !== 1 ? 's' : ''}`;
    }
    
    new Notification("Task Reminder", {
        body: notificationText,
        icon: "/taskroom/img/notification-icon.png" // Add your icon path
    });
}

</script>



</body>
</html>
