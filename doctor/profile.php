<?php

require_once "../config/db.php";
require_once "../includes/auth.php";
requireRole('doctor');
require_once "../config/specialties.php";

$pageTitle = "My Profile";

$profileErrors = [];
$profileSuccess = false;

$passwordErrors = [];
$passwordSuccess = false;



$userId = $_SESSION['user_id'];

$stmt = $connection->prepare("
    SELECT 
        u.id,
        u.name,
        u.email,
        u.phone,
        u.address,
        d.id AS doctor_id,
        d.specialty
    FROM users u
    JOIN doctors d ON d.user_id = u.id
    WHERE u.id = ?
");

$stmt->execute([$userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Doctor profile could not be found.");
}



if (isset($_POST['update_profile'])) {

    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $specialty = trim($_POST['specialty'] ?? '');

    // Validation
    if ($name === '') {
        $profileErrors[] = "Name is required.";
    }

    if ($phone === '') {
        $profileErrors[] = "Phone number is required.";
    }

    if ($address === '') {
        $profileErrors[] = "Address is required.";
    }

    if ($specialty === '') {
        $profileErrors[] = "Specialty is required.";
    }

    // Only update if there are no errors
    if (empty($profileErrors)) {

        try {

            // Start a transaction because we are updating two tables
            $connection->beginTransaction();

            // Update users table
            $updateUser = $connection->prepare("
                UPDATE users
                SET name = ?, phone = ?, address = ?
                WHERE id = ?
            ");

            $updateUser->execute([
                $name,
                $phone,
                $address,
                $userId
            ]);


            // Update doctors table
            $updateDoctor = $connection->prepare("
                UPDATE doctors
                SET specialty = ?
                WHERE user_id = ?
            ");

            $updateDoctor->execute([
                $specialty,
                $userId
            ]);


            // Save both changes
            $connection->commit();

            $profileSuccess = true;

            // Update the values shown on the page immediately
            $user['name'] = $name;
            $user['phone'] = $phone;
            $user['address'] = $address;
            $user['specialty'] = $specialty;

        } catch (PDOException $e) {

            // Undo both updates if something went wrong
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            $profileErrors[] = "Unable to update your profile. Please try again.";
        }
    }
}




if (isset($_POST['update_password'])) {

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation

    if ($newPassword === '') {
        $passwordErrors[] = "Please enter a new password.";
    }

    if ($confirmPassword === '') {
        $passwordErrors[] = "Please confirm your new password.";
    }

    if (
        $newPassword !== '' &&
        $confirmPassword !== '' &&
        $newPassword !== $confirmPassword
    ) {
        $passwordErrors[] = "Passwords do not match.";
    }

    if ($newPassword !== '' && strlen($newPassword) < 8) {
        $passwordErrors[] = "Password must be at least 8 characters long.";
    }


    // Update password if there are no errors
    if (empty($passwordErrors)) {

        try {

            // Hash the new password
            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $updatePassword = $connection->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");

            $updatePassword->execute([
                $hashedPassword,
                $userId
            ]);

            $passwordSuccess = true;

        } catch (PDOException $e) {

            $passwordErrors[] = "Unable to update your password. Please try again.";
        }
    }
}


require_once "../includes/header.php";

?>

<div class="page-card mb-4">

```
<h2>My Profile</h2>

<p class="sub">
    Update your personal and professional information below.
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
                value="<?php echo htmlspecialchars($user['name']); ?>"
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
                value="<?php echo htmlspecialchars($user['phone']); ?>"
            >

        </div>

    </div>


    <div class="mb-3">

        <label class="form-label" for="specialty">
            Specialty
        </label>

        <select
            class="form-select"
            id="specialty"
            name="specialty"
        >

            <?php foreach ($specialties as $s): ?>

                <option
                    value="<?php echo htmlspecialchars($s); ?>"
                    <?php echo ($s === $user['specialty']) ? 'selected' : ''; ?>
                >
                    <?php echo htmlspecialchars($s); ?>
                </option>

            <?php endforeach; ?>

        </select>

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
            value="<?php echo htmlspecialchars($user['address']); ?>"
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
            value="<?php echo htmlspecialchars($user['email']); ?>"
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
```

</div>

<div class="page-card">

```
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
```

</div>

<?php require_once "../includes/footer.php"; ?>
