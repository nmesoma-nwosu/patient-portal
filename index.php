<?php
    require_once "config/db.php";
    require_once "includes/auth.php";

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $email= trim($_POST['email']);
        $password= trim($_POST['password']);

        $errors= [];

       // if(empty($email) || empty($password)){
       //     $errors[]= "Please enter email and password";
        //}

        $checkEmail= $connection -> prepare ("SELECT id, name, password, role FROM users WHERE email= ? ");
        $checkEmail-> execute([$email]);
        $user = $checkEmail->fetch(PDO::FETCH_ASSOC);

        if(!$user){
            $errors[]= "No account exists with this email";
        }

        if(empty($errors)){
            if (!password_verify($password, $user['password'])) {
            $errors[] = "Incorrect Password";
        }


        }

        if(empty($errors)){
            loginUser($user);

            if($_SESSION['role']== 'patient'){
                header("Location:patient/dashboard.php");
                exit;
            }
            if($_SESSION['role']== 'doctor'){
                header("Location:doctor/dashboard.php");
                exit;
            }
            if($_SESSION['role']== 'admin'){
                header("Location:admin/dashboard.php");
                exit;
            }

        }
    }

    
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareTime — Log In</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="shell">



    <div class="panel">

        <img src="assets/images/stethoscope.png" class="care-image" alt="">


        <div class="wordmark">
            Care<span>Time</span>
        </div>

        <p class="caption">
            Healthcare at your own time.
            <span class="rot" id="rotcap">Book in minutes, not phone calls.</span>
        </p>

        <div class="dots">
            <div class="dot active"></div>
            <div class="dot"></div>
            <div class="dot"></div>
        </div>

    </div>


  <div class="formside">
    <div class="eyebrow">Welcome back</div>
    <h1>Log in to CareTime</h1>
    <p class="sub">Enter your details to reach your dashboard.</p>

    <?php if (!empty($errors)): ?>
      <?php foreach ($errors as $error): ?>
        <div class="alert-care mb-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" novalidate>
      <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-care w-100 mt-2">Log in</button>
    </form>

    <div class="divider-text">Don't have an account?</div>
    <a href="register.php" class="altlink d-block text-center">Create an account →</a>

  </div>

</div>


<script>
    const dots = document.querySelectorAll('.dot');

    const captions = [
        "Book in minutes, not phone calls.",
        "Your care team, always on hand.",
        "Confirmed visits, no guesswork."
    ];

    const rotcap = document.getElementById('rotcap');

    let i = 0;

    setInterval(() => {

        // Remove active from current dot
        dots[i].classList.remove('active');

        // Move to next message
        i = (i + 1) % captions.length;

        // Change the message
        rotcap.textContent = captions[i];

        // Activate the corresponding dot
        dots[i].classList.add('active');

    }, 3200);
</script>



</body>
</html>
