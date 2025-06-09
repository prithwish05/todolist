<?php
require 'vendor/autoload.php';
require 'connect.php';

// Temporary secret if not in environment (remove in production)
if (!isset($_ENV['JWT_SECRET'])) {
    $_ENV['JWT_SECRET'] = '32c55c969d05915ee8fe213243f14ff2b94aea8067fe8bb790ed8ab42c2d3a15';
}

use Firebase\JWT\JWT;

if (isset($_POST['signIn'])) {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, name, password FROM user WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Generate JWT
            $payload = [
                'user_id' => $user['id'],
                'name' => $user['name'],
                'email' => $email,
                'exp' => time() + 3600 // 1 hour expiration
            ];
            
            $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
            
            setcookie('jwt', $jwt, [
                'expires' => time() + 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
            
            // Start session and store user data
            session_start();
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $email;
            $_SESSION['name'] = $user['name'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: index.php?error=invalid_credentials");
            exit();
        }
    } else {
        header("Location: index.php?error=user_not_found");
        exit();
    }
}

if (isset($_POST['signUp'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        header("Location: index.php?error=email_exists");
        exit();
    }

    // Insert user
    $stmt = $conn->prepare("INSERT INTO user (name, email, password) VALUES (?, ?, ?)");
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("sss", $name, $email, $password);
    
    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Generate JWT
        $payload = [
            'user_id' => $user_id,
            'name' => $name,
            'email' => $email,
            'exp' => time() + 3600 // 1 hour
        ];
        
        $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
        
        // Set cookie
        setcookie('jwt', $jwt, [
            'expires' => time() + 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Start session and store user data
        session_start();
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $email;
        $_SESSION['name'] = $name;
        
        header("Location: dashboard.php");
        exit();
    } else {
        header("Location: index.php?error=registration_failed");
        exit();
    }
}
?>
