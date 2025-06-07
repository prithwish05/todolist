<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Success Mantra</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link href="style.css" rel="stylesheet">
</head>
<body>
<?php session_start(); ?>
  <header class=" text-white" style="background-color: #FDFBD4 !important;">
    <div class="container">
      <nav class="navbar navbar-expand-lg navbar-dark">
        <a class="navbar-brand " href="#"><img src="img/Logo-.png" alt="Logo" height="40" width="180"></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav" style="flex-direction: row-reverse;">
          <div class="d-flex" >
                  <!-- Guest user (not logged in) -->
                  <button class="btn btn-outline-light me-2" onclick="window.location.href='login.php';" style="background-color: brown;">Sign in</button>
                  <button class="btn btn-dark" onclick="window.location.href='login.php';" style="margin-left: 30px;">Register</button>
          
          </div>

        </div>
      </nav>
    </div>
  </header>

  <main>
    <section id="home" class="hero py-5" style="background: linear-gradient(rgba(255, 255, 255, 0.5), rgba(255, 255, 255, 0.5)), url('https://images.pexels.com/photos/2736499/pexels-photo-2736499.jpeg?cs=srgb&dl=pexels-content-pixie-1405717-2736499.jpg&fm=jpg');background-size: cover; background-position: center;min-height: 550px; ">
      <div class="container text-center">
        <h1 class="display-2 fw-bold">
          <span>CREATE YOUR <span class="highlight" style="color:brown; font-size: 7rem;" >FREE</span></span><br>
          <span>TO-DO LIST</span>
        </h1>
        <pre class="lead fs-3" style=" font-size: 1.1rem; margin-top: 1rem; margin-bottom: 1.5rem;  font-weight:bold;     color: #840101; "> When you write it down, you take control. 
That’s the power of a to-do list.
        </pre>
        <div class="mt-4">
          <button class="btn btn-dark"onclick="window.location.href='login.php';" >Free Sign-In</button>
        </div>
      </div>
    </section>

    <section id="features" class="py-5">
      <div class="container">
        <div class="row">
          <div class="col-md-3 mb-3">
            <div class="card bg-light-pink">
              <div class="card-body">
                <img src="img/define.png" class="card-img">
                <h5 class="card-title">Define</h5>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card bg-light-pink">
              <div class="card-body">
                <img src="img/priority.png" class="card-img">
                <h5 class="card-title">Prioritize</h5>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card bg-light-pink">
              <div class="card-body">
                <img src="img/schedule.png" class="card-img">
                <h5 class="card-title">Schedule</h5>
              </div>
            </div>
          </div>
          <div class="col-md-3 mb-3">
            <div class="card bg-light-pink">
              <div class="card-body">
                <img src="img/track.png" class="card-img">
                <h5 class="card-title">Track</h5>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
  <script src="index.js"></script>
</body>
</html>
