<?php
setcookie('jwt', '', [
  'expires' => time() - 3600,
  'path' => '/',
  'secure' => true,
  'httponly' => true
]);
header("Location: index.php");
?>
