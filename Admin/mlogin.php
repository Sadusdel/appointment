<?php
session_start();
require_once 'dbconfig.php';

$message = '';

if (isset($_POST['submit'])) {
    $username = trim($_POST['uname'] ?? '');
    $password = (string)($_POST['pass'] ?? '');

    if ($username === '' || $password === '') {
        $message = 'Kullanıcı adı ve parola zorunludur.';
    } else {
        $stmt = $conn->prepare('SELECT mid, username, name, password FROM manager WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $validPassword = false;
        if ($row) {
            $storedPassword = (string)$row['password'];
            $validPassword = password_verify($password, $storedPassword);
            if (!$validPassword && hash_equals($storedPassword, $password)) {
                $validPassword = true;
            }
        }

        if ($row && $validPassword) {
            session_regenerate_id(true);
            $_SESSION['username'] = $row['username'];
            $_SESSION['mgrname'] = $row['name'];
            $_SESSION['mgrid'] = $row['mid'];
            header('Location: mgrmenu.php');
            exit;
        }

        $message = 'Kullanıcı adı veya parola hatalı.';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manager Login | Appointment</title>
<link rel="stylesheet" href="main.css">
</head>
<body style="background-image:url(doctordesk.jpg)">
<div class="header">
    <ul>
        <li style="float:left;border-right:none"><strong>Manager Login</strong></li>
        <li><a href="cover.php">Home</a></li>
    </ul>
</div>
<div class="sucontainer">
    <form action="mlogin.php" method="post">
        <label><b>Username:</b></label><br>
        <input type="text" placeholder="Enter Username" name="uname" autocomplete="username" required><br>
        <label><b>Password:</b></label><br>
        <input type="password" placeholder="Enter Password" name="pass" autocomplete="current-password" required><br><br>
        <?php if ($message !== ''): ?>
            <p role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="container" style="background-color:grey">
            <button type="submit" name="submit">Log In</button>
        </div>
    </form>
</div>
</body>
</html>
