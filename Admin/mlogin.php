<?php
session_start();
require_once 'dbconfig.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $legacyPassword = false;
        if ($row) {
            $storedPassword = (string)$row['password'];
            $validPassword = password_verify($password, $storedPassword);
            if (!$validPassword && hash_equals($storedPassword, $password)) {
                $validPassword = true;
                $legacyPassword = true;
            }
        }

        if ($row && $validPassword) {
            if ($legacyPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updatePassword = $conn->prepare('UPDATE manager SET password = ? WHERE mid = ? LIMIT 1');
                $updatePassword->bind_param('si', $newHash, $row['mid']);
                $updatePassword->execute();
                $updatePassword->close();
            }

            session_regenerate_id(true);
            $_SESSION['username'] = $row['username'];
            $_SESSION['mgrname'] = $row['name'];
            $_SESSION['mgrid'] = (int)$row['mid'];
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
        <label for="manager-username"><b>Username:</b></label><br>
        <input id="manager-username" type="text" placeholder="Enter Username" name="uname" autocomplete="username" required><br>
        <label for="manager-password"><b>Password:</b></label><br>
        <input id="manager-password" type="password" placeholder="Enter Password" name="pass" autocomplete="current-password" required><br><br>
        <?php if ($message !== ''): ?>
            <p role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="container" style="background-color:grey">
            <button type="submit">Log In</button>
        </div>
    </form>
</div>
</body>
</html>