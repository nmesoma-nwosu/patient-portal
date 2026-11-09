<?php
   

    session_start();

    function loginUser($user) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];
    }

    function isLoggedIn() {

        return isset($_SESSION['user_id']);
    }

    function requireLogin() {

        if (!isLoggedIn()) {
            header("Location:". BASE_URL ."index.php?login_error=not_logged_in");
            exit;
        }
    }

    function requireRole($role) {

        requireLogin();

        if ($_SESSION['role'] !== $role) {
            header("Location:". BASE_URL ."index.php?error=unauthorized");
            exit;
        }
    }

    function logoutUser() {

        session_unset();
        session_destroy();

        header("Location:". BASE_URL ."index.php?logout=1");
        exit;
    }

?>
