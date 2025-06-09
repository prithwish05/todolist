<?php
session_start();
include("connect.php");

if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_SESSION['email'];
    $newName = trim($_POST['name']);
    $newEmail = trim($_POST['email']);
    
    // Validate inputs
    if (empty($newName) || empty($newEmail)) {
        $_SESSION['profile_error'] = "Name and email cannot be empty";
        header("Location: dashboard.php");
        exit;
    }
    
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['profile_error'] = "Invalid email format";
        header("Location: dashboard.php");
        exit;
    }
    
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
        
        // Handle file upload
        $profilePicture = null;
        if (!empty($_FILES['profile_picture']['name'])) {
            $uploadDir = 'uploads/profile_pics/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedType = finfo_file($fileInfo, $_FILES['profile_picture']['tmp_name']);
            finfo_close($fileInfo);
            
            if (!in_array($detectedType, $allowedTypes)) {
                $_SESSION['profile_error'] = "Invalid file type. Only JPG, PNG, GIF are allowed";
                header("Location: dashboard.php");
                exit;
            }
            
            if ($_FILES['profile_picture']['size'] > 2097152) { // 2MB
                $_SESSION['profile_error'] = "File too large. Max 2MB allowed";
                header("Location: dashboard.php");
                exit;
            }
            
            $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . md5(time() . $email) . '.' . $extension;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $destination)) {
                $profilePicture = $destination;
                
                // Delete old profile picture if it exists
                $stmt = $conn->prepare("SELECT profile_picture FROM user WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $oldPicture = $result->fetch_assoc()['profile_picture'];
                if ($oldPicture && file_exists($oldPicture)) {
                    unlink($oldPicture);
                }
            }
        }
        
        // Update profile
        if ($profilePicture) {
            $stmt = $conn->prepare("UPDATE user SET name = ?, email = ?, profile_picture = ? WHERE email = ?");
            $stmt->bind_param("ssss", $newName, $newEmail, $profilePicture, $email);
        } else {
            $stmt = $conn->prepare("UPDATE user SET name = ?, email = ? WHERE email = ?");
            $stmt->bind_param("sss", $newName, $newEmail, $email);
        }
        
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            $_SESSION['profile_success'] = "Profile updated successfully";
            $_SESSION['email'] = $newEmail; // Update session email
        } else {
            $_SESSION['profile_error'] = "No changes were made";
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['profile_error'] = "Error updating profile: " . $e->getMessage();
    }
    
    header("Location: dashboard.php");
    exit;
}