<?php
require_once '../reusable/db_setup.php';
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $user = authenticateUser($username, $password);

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        header("Location: ../index.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../reusable/head.php'; ?>
    <title>Login - Glamour Salon</title>
    <link rel="stylesheet" href="/assets/css/login.css">
</head>

<body>
    <?php include '../reusable/header.php'; ?>

    <div class="container">
        <div class="login-container">
            <i class="fas fa-user-circle login-icon"></i>
            <h2 class="login-title">Login to Your Account</h2>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-login">Login</button>
            </form>

            <div class="signup-link">
                <p>Don't have an account? <a href="signUp.php">Sign up</a></p>
            </div>
        </div>
    </div>

    <?php include '../reusable/footer.php'; ?>
    <?php include '../reusable/scripts.php'; ?>

</body>
</html>