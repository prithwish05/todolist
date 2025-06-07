<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login Register Form</title>

<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
<link rel="stylesheet" href="login.css">
</head>

<body>
<div class="wrapper" style="height: 600px;">
  <span class="rotate-bg"></span>
  <span class="rotate-bg2"></span>

  <div class="form-box login">
    <h2 class="title animation" style="--i:0; --j:21">Login</h2>
    <form action="register.php" method="POST">
      <div class="input-box animation" style="margin-top: 0; --i:1; --j:22">
        <input type="email" name="email" id="loginEmail" required>
        <label for="loginEmail">Email</label>
        <i class='bx bxs-envelope'></i>
      </div>

      <div class="input-box animation" style="--i:2; --j:23">
        <input type="password" name="password" id="loginPassword" required>
        <label for="loginPassword">Password</label>
        <i class='bx bxs-lock-alt'></i>
      </div>

      <button type="submit" name="signIn" class="btn animation" style="--i:3; --j:24">Login</button>

      <div class="linkTxt animation" style="--i:5; --j:25">
        <p>Don't have an account? <a href="#" class="register-link">Sign Up</a></p>
      </div>
    </form>
  </div>

  <div class="info-text login">
    <h2 class="animation" style="--i:0; --j:20">Welcome Back!</h2>
  </div>

  <div class="form-box register">
    <h2 class="title animation" style="--i:17; --j:0">Sign Up</h2>
    <form id="registerForm" action="register.php" method="POST">
      <div class="input-box animation" style="--i:18; --j:1">
        <input type="text" name="name" id="registerName" required>
        <label for="registerName">Name</label>
        <i class='bx bxs-user'></i>
      </div>

      <div class="input-box animation" style="--i:19; --j:2">
        <input type="email" name="email" id="registerEmail" required>
        <label for="registerEmail">Email</label>
        <i class='bx bxs-envelope'></i>
      </div>

      <div class="input-box animation" style="--i:24; --j:3">
        <input type="password" name="password" id="registerPassword" required>
        <label for="registerPassword">Password</label>
        <i class='bx bxs-lock-alt'></i>
      </div>

      <button type="submit" name="signUp" class="btn animation" style="--i:22; --j:5">Sign Up</button>

      <div class="linkTxt animation" style="--i:25; --j:6">
        <p>Already have an account? <a href="#" class="login-link">Login</a></p>
      </div>
    </form>
  </div>

  <div class="info-text register">
    <h2 class="animation" style="--i:17; --j:0;">Welcome Back!</h2>
  </div>
</div>

<script src="login.js"></script>
</body>
</html>
