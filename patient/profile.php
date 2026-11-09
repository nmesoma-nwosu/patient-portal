
<?php

require_once "../config/db.php";
require_once "../includes/auth.php";

requireRole('patient');

$pageTitle = "My Profile";

$userId = $_SESSION['user_id'];


$stmt = $connection->prepare(
    "SELECT name, email, phone, dob, gender, address
     FROM users
     WHERE id = ?"
);

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);



$profileErrors = [];
$profileSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {

    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $dob = trim($_POST['dob']);
    $gender = trim($_POST['gender']);
    $address = trim($_POST['address']);


  
    if (empty($name)) {
        $profileErrors[] = "Name is required.";
    }


    
    if (empty($phone)) {
        $profileErrors[] = "Phone number is required.";
    }


    
    if (empty($dob)) {
        $profileErrors[] = "Date of birth is required.";
    }


    
    if (!in_array($gender, ['male', 'female', 'other'])) {
        $profileErrors[] = "Please select a valid gender.";
    }


    if (empty($profileErrors)) {

        $stmt = $connection->prepare(
            "UPDATE users
             SET name = ?, phone = ?, dob = ?, gender = ?, address = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $name,
            $phone,
            $dob,
            $gender,
            $address,
            $userId
        ]);

        $profileSuccess = true;


        
        $stmt = $connection->prepare(
            "SELECT name, email, phone, dob, gender, address
             FROM users
             WHERE id = ?"
        );

        $stmt->execute([$userId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}



$passwordErrors = [];
$passwordSuccess = false;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_password'])) {

    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];


  
    if (empty($newPassword)) {
        $passwordErrors[] = "Please enter a new password.";
    }

    if (empty($confirmPassword)) {
        $passwordErrors[] = "Please confirm your new password.";
    }


    
    if (
        !empty($newPassword) &&
        !empty($confirmPassword) &&
        $newPassword !== $confirmPassword
    ) {
        $passwordErrors[] = "Passwords do not match.";
    }


    
    if (empty($passwordErrors)) {

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $connection->prepare(
            "UPDATE users
             SET password = ?
             WHERE id = ?"
        );

        $stmt->execute([
            $hashedPassword,
            $userId
        ]);

        $passwordSuccess = true;
    }
}


require_once "../includes/header.php";

?>

<div class="page-card mb-4">

    <h2>My Profile</h2>

    <p class="sub">
        Update your personal information below.
    </p>


    <?php if (!empty($profileErrors)): ?>

        <?php foreach ($profileErrors as $error): ?>

            <div class="alert-care mb-3" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>


    <?php if ($profileSuccess): ?>

        <div
            class="alert-care mb-3"
            style="background:var(--mint); border-color:var(--teal); color:var(--teal-dark);"
            role="alert"
        >
            Profile updated successfully.
        </div>

    <?php endif; ?>


    <form method="post" novalidate>

        <input type="hidden" name="update_profile" value="1">


        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label" for="name">
                    Full name
                </label>

                <input
                    type="text"
                    class="form-control"
                    id="name"
                    name="name"
                    value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label" for="phone">
                    Phone number
                </label>

                <input
                    type="tel"
                    class="form-control"
                    id="phone"
                    name="phone"
                    value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                >

            </div>

        </div>


        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label" for="dob">
                    Date of birth
                </label>

                <input
                    type="date"
                    class="form-control"
                    id="dob"
                    name="dob"
                    value="<?php echo htmlspecialchars($user['dob'] ?? ''); ?>"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label" for="gender">
                    Gender
                </label>

                <select
                    class="form-select"
                    id="gender"
                    name="gender"
                >

                    <option
                        value="male"
                        <?php echo ($user['gender'] ?? '') === 'male' ? 'selected' : ''; ?>
                    >
                        Male
                    </option>

                    <option
                        value="female"
                        <?php echo ($user['gender'] ?? '') === 'female' ? 'selected' : ''; ?>
                    >
                        Female
                    </option>

                    <option
                        value="other"
                        <?php echo ($user['gender'] ?? '') === 'other' ? 'selected' : ''; ?>
                    >
                        Other
                    </option>

                </select>

            </div>

        </div>


        <div class="mb-3">

            <label class="form-label" for="address">
                Address
            </label>

            <input
                type="text"
                class="form-control"
                id="address"
                name="address"
                value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>"
            >

        </div>


        <div class="mb-3">

            <label class="form-label" for="email">
                Email address
            </label>

            <input
                type="email"
                class="form-control"
                id="email"
                value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                disabled
            >

            <div
                class="form-text"
                style="font-size:0.78rem; color:var(--ink-soft); margin-top:4px;"
            >
                Email can't be changed here.
            </div>

        </div>


        <button type="submit" class="btn btn-care">
            Save changes
        </button>

    </form>

</div>


<div class="page-card">

    <h2>Change Password</h2>

    <p class="sub">
        Enter a new password below.
    </p>


    <?php if (!empty($passwordErrors)): ?>

        <?php foreach ($passwordErrors as $error): ?>

            <div class="alert-care mb-3" role="alert">
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>


    <?php if ($passwordSuccess): ?>

        <div
            class="alert-care mb-3"
            style="background:var(--mint); border-color:var(--teal); color:var(--teal-dark);"
            role="alert"
        >
            Password updated successfully.
        </div>

    <?php endif; ?>


    <form method="post" novalidate>

        <input type="hidden" name="update_password" value="1">


        <div class="row">

            <div class="col-md-6 mb-3">

                <label class="form-label" for="new_password">
                    New password
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="new_password"
                    name="new_password"
                >

            </div>


            <div class="col-md-6 mb-3">

                <label class="form-label" for="confirm_password">
                    Confirm new password
                </label>

                <input
                    type="password"
                    class="form-control"
                    id="confirm_password"
                    name="confirm_password"
                >

            </div>

        </div>


        <button type="submit" class="btn btn-care">
            Update password
        </button>

    </form>

</div>


<?php require_once "../includes/footer.php"; ?>

