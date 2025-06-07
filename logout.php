<?php
session_start();  // Start the session
session_destroy();  // Destroy all session data
header("Location: index.php");  // Redirect to home page
exit();  // Ensure the script stops here
?>
