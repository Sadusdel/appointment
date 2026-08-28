<?php
session_start();
require_once 'dbconfig.php';

if (isset($_SESSION['doctor_id']) && (int)$_SESSION['doctor_id'] > 0) {
    header('Location: ../doctor_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['uname'] ?? '');
    $password = (string)($_POST['pass'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $stmt = $conn->prepare('SELECT did, username, password, name FROM doctor WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $doctor = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $validPassword = false;
        $legacyPassword = false;

        if ($doctor) {
            $storedPassword = (string)$doctor['password'];
            $validPassword = password_verify($password, $storedPassword);

            if (!$validPassword && hash_equals($storedPassword, $password)) {
                $validPassword = true;
                $legacyPassword = true;
            }
        }

        if ($doctor && $validPassword) {
            if ($legacyPassword) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updatePassword = $conn->prepare('UPDATE doctor SET password = ? WHERE did = ? LIMIT 1');
                $updatePassword->bind_param('si', $newHash, $doctor['did']);
                $updatePassword->execute();
                $updatePassword->close();
            }

            session_regenerate_id(true);
            $_SESSION['doctor_id'] = (int)$doctor['did'];
            $_SESSION['doctor_username'] = $doctor['username'];
            $_SESSION['doctor_name'] = $doctor['name'];
            header('Location: ../doctor_dashboard.php');
            exit;
        }

        $error = 'Kullanıcı adı veya şifre hatalı.';
    }
}

$conn->close();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Doktor Girişi | Appointment</title>
<link rel="stylesheet" href="../main.css">
<style>body{min-height:100vh;margin:0;background:linear-gradient(135deg,rgba(15,31,58,.88),rgba(23,105,224,.72)),url('doctordesk.jpg') center/cover fixed;font-family:Arial,Helvetica,sans-serif;display:flex;align-items:center;justify-content:center;padding:24px}.login-card{width:min(430px,100%);background:#fff;border-radius:20px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.2)}.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#172033;font-size:20px;font-weight:800;margin-bottom:28px}.brand img{width:38px;height:38px;object-fit:contain}.eyebrow{display:inline-block;color:#1769e0;font-size:11px;font-weight:800;letter-spacing:1.2px;margin-bottom:8px}.login-card h1{margin:0;font-size:28px;color:#172033}.intro{margin:8px 0 24px;color:#748094;font-size:14px}.field{margin-bottom:16px}.field label{display:block;font-size:13px;font-weight:700;color:#374151;margin-bottom:7px}.field input{width:100%;height:46px;border:1px solid #d9dfe8;border-radius:9px;padding:0 13px;font-size:14px;box-sizing:border-box;outline:none}.field input:focus{border-color:#1769e0;box-shadow:0 0 0 3px rgba(23,105,224,.1)}.alert{padding:12px 14px;border-radius:9px;margin-bottom:18px;font-size:13px}.alert.error{background:#fff0f0;color:#a61b2b;border:1px solid #ffd4d8}.actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:22px}.button{height:45px;border:0;border-radius:9px;font-weight:700;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;font-size:14px}.button.primary{background:#1769e0;color:#fff}.button.primary:hover{background:#0e55bd}.button.secondary{background:#f1f4f8;color:#4f5c70}.button.secondary:hover{background:#e7ebf1}.back{display:block;text-align:center;margin-top:20px;color:#748094;font-size:13px;text-decoration:none}.back:hover{color:#1769e0}@media(max-width:480px){body{padding:14px}.login-card{padding:25px 20px;border-radius:16px}.actions{grid-template-columns:1fr}}</style>
</head>
<body>
<main class="login-card">
<a class="brand" href="../cover.php"><img src="../images/cal.png" alt="Appointment"><span>Appointment</span></a>
<span class="eyebrow">DOKTOR PANELİ</span>
<h1>Hoş geldiniz</h1>
<p class="intro">Doktor hesabınızla randevu panelinize giriş yapın.</p>
<?php if ($error): ?><div class="alert error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<form action="dlogin.php" method="post">
<div class="field"><label for="uname">Kullanıcı adı</label><input id="uname" type="text" name="uname" autocomplete="username" required></div>
<div class="field"><label for="pass">Şifre</label><input id="pass" type="password" name="pass" autocomplete="current-password" required></div>
<div class="actions"><a class="button secondary" href="../cover.php">Geri Dön</a><button class="button primary" type="submit">Giriş Yap</button></div>
</form>
<a class="back" href="../cover.php">Ana sayfaya dön</a>
</main>
</body>
</html>