<?php

    require_once "config/db.php"; 
    require_once "config/specialties.php";

    $name = '';
    $email = '';
    $role = 'patient';
    $phone = '';
    $dob = '';
    $gender = '';
    $address = '';
    $specialty = '';
    $errors = [];
    $step2_error = false;

    if($_SERVER["REQUEST_METHOD"] == "POST"){
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $role = trim($_POST['role']);
        $phone = trim($_POST['phone']);
        $dob = trim($_POST['dob']);
        $gender = trim($_POST['gender'] ?? '');
        $address = trim($_POST['address']);
        $specialty = trim($_POST['specialty'] ?? '');

        if (empty($name) || empty($email) || empty($password)){
            $errors[]= "Name, email, and password are required";
            if (!empty($name)) $step2_error = true;
        }

        if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
            $errors[]= "Invalid email format";
            $step2_error = true;
        }

        if(strlen($password)< 8){
            $errors[]= "Password must be at least 8 characters";
            $step2_error = true;
        }

        if(empty($errors)){
            $checkEmail = $connection-> prepare("SELECT id FROM users WHERE email =?");
            $checkEmail-> execute([$email]);
            if($checkEmail->fetch()){
                $errors[]= "This email is already registered";
                $step2_error = true;
            }
        }

        if(empty($errors)){
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $connection -> prepare (
                "INSERT INTO users (name, email, password, role, phone, dob, gender, address)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?)" 
            );

            $insertStmt->execute([
                $name,
                $email,
                $hashedPassword,
                $role,
                $phone,
                $dob,
                $gender,
                $address
            ]);

            $userId = $connection->lastInsertId();

            if ($role === 'doctor') {

                $doctorStmt = $connection->prepare(
                    "INSERT INTO doctors (user_id, specialty) VALUES (?, ?)"
                );

                $doctorStmt->execute([
                    $userId,
                    $specialty
                ]);
            }
             

         
            header("Location: index.php?registered=1");
            exit;
        }
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CareTime — Register</title>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;1,9..144,500&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="plain-auth auth-body">

<div class="auth-card">

  <div class="wordmark-plain">Care<span>Time</span></div>

  <div class="eyebrow text-center">Get started</div>
  <h1 class="text-center">Create your account</h1>
  <p class="sub text-center">Join CareTime as a patient or doctor.</p>

  <?php if (!empty($errors) && !$step2_error): ?>
    <?php foreach ($errors as $error): ?>
      <div class="alert-care mb-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Step indicator -->
  <div class="step-indicator">
    <div class="step-dot <?php echo $step2_error ? '' : 'active'; ?>" id="dot-1">1</div>
    <div class="step-line"></div>
    <div class="step-dot <?php echo $step2_error ? 'active' : ''; ?>" id="dot-2">2</div>
  </div>

  <form method="post" novalidate>

    <!-- ===== STEP 1: Personal info ===== -->
    <div id="step-1" style="<?php echo $step2_error ? 'display:none;' : ''; ?>">

      <div class="role-toggle">
        <input type="radio" name="role" id="role-patient" value="patient" <?php echo $role === 'patient' ? 'checked' : ''; ?>>
        <label for="role-patient">I'm a patient</label>
        <input type="radio" name="role" id="role-doctor" value="doctor" <?php echo $role === 'doctor' ? 'checked' : ''; ?>>
        <label for="role-doctor">I'm a doctor</label>
      </div>

      <div class="mb-2" id="specialty-group" style="<?php echo $role === 'doctor' ? '' : 'display:none;'; ?>">
        <label class="form-label" for="specialty">Specialty</label>
        <select class="form-select" id="specialty" name="specialty">
          <option value="" <?php echo $specialty === '' ? 'selected' : ''; ?>>Select…</option>
          <?php foreach ($specialties as $s): ?>
            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $specialty === $s ? 'selected' : ''; ?>><?php echo htmlspecialchars($s); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="form-label" for="name">Full name</label>
          <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label" for="phone">Phone number</label>
          <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-2">
          <label class="form-label" for="dob">Date of birth</label>
          <input type="date" class="form-control" id="dob" name="dob" value="<?php echo htmlspecialchars($dob); ?>">
        </div>
        <div class="col-md-6 mb-2">
          <label class="form-label" for="gender">Gender</label>
          <select class="form-select" id="gender" name="gender">
            <option value="" <?php echo $gender === '' ? 'selected' : ''; ?>>Select…</option>
            <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
            <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
            <option value="other" <?php echo $gender === 'other' ? 'selected' : ''; ?>>Other</option>
          </select>
        </div>
      </div>

      <div class="mb-2">
        <label class="form-label" for="address">Address</label>
        <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>">
      </div>

      <button type="button" class="btn btn-care w-100 mt-2" id="btn-next">Next →</button>

    </div>

    <!-- ===== STEP 2: Credentials ===== -->
    <div id="step-2" style="<?php echo $step2_error ? '' : 'display:none;'; ?>">

      <?php if (!empty($errors) && $step2_error): ?>
        <?php foreach ($errors as $error): ?>
          <div class="alert-care mb-3" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <p class="sub" style="margin-bottom:18px;">Almost done! Set up your login credentials.</p>

      <div class="mb-3">
        <label class="form-label" for="email">Email address</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label" for="password">Password</label>
        <input type="password" class="form-control" id="password" name="password" required>
        <div class="form-text" style="font-size:0.78rem;color:var(--ink-soft);margin-top:4px;">Must be at least 8 characters</div>
      </div>

      <div class="d-flex gap-2 mt-2">
        <button type="button" class="btn btn-outline-secondary w-50" id="btn-back" style="border-radius:10px;font-weight:600;">← Back</button>
        <button type="submit" class="btn btn-care w-50">Create account</button>
      </div>

    </div>

  </form>

  <div class="divider-text">Already have an account?</div>
  <a href="index.php" class="altlink d-block text-center">Log in →</a>

</div>

<script>
  const step1  = document.getElementById('step-1');
  const step2  = document.getElementById('step-2');
  const dot1   = document.getElementById('dot-1');
  const dot2   = document.getElementById('dot-2');
  const btnNext = document.getElementById('btn-next');
  const btnBack = document.getElementById('btn-back');

  const roleRadios = document.querySelectorAll('input[name="role"]');
  const specialtyGroup = document.getElementById('specialty-group');

  function toggleSpecialty() {
    const selected = document.querySelector('input[name="role"]:checked').value;
    specialtyGroup.style.display = (selected === 'doctor') ? 'block' : 'none';
  }
  roleRadios.forEach(r => r.addEventListener('change', toggleSpecialty));

  btnNext.addEventListener('click', () => {
    // Require at least the name before proceeding
    const name = document.getElementById('name');
    if (!name.value.trim()) {
      name.focus();
      name.classList.add('is-invalid');
      return;
    }
    name.classList.remove('is-invalid');
    step1.style.display = 'none';
    step2.style.display = 'block';
    dot1.classList.remove('active');
    dot2.classList.add('active');
  });

  btnBack.addEventListener('click', () => {
    step2.style.display = 'none';
    step1.style.display = 'block';
    dot2.classList.remove('active');
    dot1.classList.add('active');
  });
</script>

</body>
</html>