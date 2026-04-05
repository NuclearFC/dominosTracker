<?php
// ============================================================
// login.php — Login form
// ============================================================

session_start();
require_once 'includes/db.php';
require_once 'config.php';

// Already logged in? Go straight to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /tracker/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        // Look up the user by username
        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Correct credentials — start the session
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $username;

            header('Location: /tracker/dashboard.php');
            exit;
        } else {
            $error = 'Incorrect username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/tracker/assets/style.css">
</head>
<body class="login-page">

<div class="login-card">
    <h1><?= SITE_NAME ?></h1>
    <p class="login-subtitle">Delivery Shift Tracker</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" action="/tracker/login.php">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   autocomplete="username" autofocus required>
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-full">Log In</button>
    </form>
</div>

</body>
</html>
