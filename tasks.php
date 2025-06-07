<?php
session_start();
include("connect.php");

// Get action (create, edit, delete, complete)
$action = $_POST['action'] ?? '';

switch ($action) {
  case 'create':
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $stmt = $conn->prepare("INSERT INTO tasks (title, description, due_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $due_date);
    $stmt->execute();
    echo "Task created!";
    break;

  case 'edit':
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $due_date = $_POST['due_date'];
    $stmt = $conn->prepare("UPDATE tasks SET title=?, description=?, due_date=? WHERE id=?");
    $stmt->bind_param("sssi", $title, $description, $due_date, $id);
    $stmt->execute();
    echo "Task updated!";
    break;

  case 'delete':
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "Task deleted!";
    break;

  case 'complete':
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("UPDATE tasks SET status='completed' WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo "Task marked as completed!";
    break;

  default:
    echo "No action taken.";
}
