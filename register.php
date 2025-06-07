<?php
include 'connect.php';

if (isset($_POST['signUp'])) {
    $Name = $_POST['name'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hash password securely
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $stmt = $conn->prepare("SELECT * FROM user WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<script>
                alert('Email Address Already Exists!');
                window.location = 'index.php';
              </script>";
    } else {
        // Insert new user
        $stmt = $conn->prepare("INSERT INTO user(name,email,password) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $Name, $email, $passwordHash);

        if ($stmt->execute()) {
            header("location: dashboard.php");
            exit();
        } else {
            echo "<script>alert('Error during registration.');</script>";
        }
    }
}

if (isset($_POST['signIn'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Fetch user record
    $stmt = $conn->prepare("SELECT * FROM user WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            session_start();
            $_SESSION['email'] = $row['email'];
            header("Location: dashboard.php");
            exit();
        } else {
            echo "<script>
                    alert('Incorrect password!');
                    window.location = 'index.php';
                  </script>";
        }
    } else {
        echo "<script>
                alert('User not found!');
                window.location = 'index.php';
              </script>";
    }
}
?>
