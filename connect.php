<?php

$host="localhost";
$user="root";
$pass="";
$db="taskroom";
$conn=new mysqli($host,$user,$pass,$db);
if($conn->connect_error){
    echo "Failed to connect DB".$conn->connect_error;
}
?>
<?php
include 'load_env.php';  // include the loader

loadEnv(__DIR__ . '/.env');  // load env variables

$host = getenv('DB_HOST');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
   // echo "Failed to connect DB: " . $conn->connect_error;
} else {
   // echo "Connected successfully!";
}
