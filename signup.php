<?php
session_start();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signup'])) {
    $required = ['fname', 'dob', 'gender', 'contact', 'username', 'email', 'pwd', 'pwdr'];
    $valid = true;
    foreach ($required as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') { $valid = false; break; }
    }

    $password = (string)($_POST['pwd'] ?? '');
    $passwordRepeat = (string)($_POST['pwdr'] ?? '');
    $username = trim((string)($_POST['username'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $name = trim((string)($_POST['fname'] ?? ''));

    if (!$valid) {
        $message = 'Lütfen tüm zorunlu alanları doldurun.';
        $messageType = 'error';
    } elseif ($password !== $passwordRepeat) {
        $message = 'Şifreler eşleşmiyor.';
        $messageType = 'error';
    } elseif (strlen($password) < 8) {
        $message = 'Şifre en az 8 karakter olmalıdır.';
        $messageType = 'error';
    } elseif (strlen($username) > 20 || !preg_match('/^[A-Za-z0-9_.-]+$/', $username)) {
        $message = 'Kullanıcı adı yalnızca harf, rakam, nokta, alt çizgi ve tire içerebilir.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Geçerli bir e-posta adresi girin.';
        $messageType = 'error';
    } elseif (strlen($name) > 30) {
        $message = 'Ad soyad çok uzun.';
        $messageType = 'error';
    } else {
        include 'dbconfig.php';
        $check = $conn->prepare('SELECT Username FROM Patient WHERE Username = ? LIMIT 1');
        $check->bind_param('s', $username);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = 'Bu kullanıcı adı zaten kullanılıyor.';
            $messageType = 'error';
        } else {
            $gender = trim((string)$_POST['gender']);
            $dob = trim((string)$_POST['dob']);
            $contact = trim((string)$_POST['contact']);
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare('INSERT INTO Patient (Name, Gender, DOB, Contact, Email, Username, Password) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $name, $gender, $dob, $contact, $email, $username, $passwordHash);
            try {
                if ($stmt->execute()) {
                    $message = 'Hesabınız başarıyla oluşturuldu. Giriş sayfasına yönlendiriliyorsunuz.';
                    $messageType = 'success';
                    header('Refresh:2; url=cover.php');
                } else {
                    $message = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
                    $messageType = 'error';
                }
            } catch (mysqli_sql_exception $e) {
                $message = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
                $messageType = 'error';
            }
            $stmt->close();
        }
        $conn->close();
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Kayıt Ol | Appointment</title>
    <link rel="stylesheet" href="main.css">
    <style>
        .register-page { min-height: 100vh; background: radial-gradient(circle at top left, #e9f2ff 0, transparent 34%), var(--page); }
        .register-header .header-inner { min-height: 72px; }
        .register-layout { width: min(900px, calc(100% - 32px)); margin: 38px auto 70px; }
        .register-card { display: grid; grid-template-columns: .82fr 1.18fr; overflow: hidden; padding: 0; }
        .register-info { padding: 42px; background: linear-gradient(145deg, #1769e0, #0f57bf); color: #fff; }
        .register-info h1, .register-info p { color: #fff; }
        .register-info h1 { margin-top: 14px; font-size: 34px; }
        .register-info p { color: rgba(255,255,255,.84); }
        .register-benefits { margin-top: 28px; display: grid; gap: 13px; }
        .register-benefit { padding: 13px 15px; border: 1px solid rgba(255,255,255,.2); border-radius: 12px; background: rgba(255,255,255,.08); }
        .register-form { padding: 42px; background: #fff; }
        .register-form h2 { margin-top: 0; }
        .register-form .form-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 16px; }
        .register-form .field-full { grid-column: 1/-1; }
        .register-form button { margin-top: 8px; width: 100%; }
        .register-back { display: inline-block; margin-top: 18px; font-size: 13px; font-weight: 700; }
        @media(max-width:760px){.register-card{grid-template-columns:1fr}.register-info,.register-form{padding:28px 22px}.register-form .form-grid{grid-template-columns:1fr}.register-form .field-full{grid-column:auto}}
    </style>
</head>
<body class="register-page">
<header class="site-header register-header"><div class="header-inner"><a href="cover.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a><nav><a href="cover.php">Ana Sayfa</a></nav></div></header>
<main class="register-layout"><section class="card register-card"><aside class="register-info"><span class="eyebrow">YENİ HESAP</span><h1>Randevularınızı tek yerden yönetin.</h1><p>Güvenli hesabınızı oluşturun ve uygun doktor ile zamanı kolayca seçin.</p><div class="register-benefits"><div class="register-benefit">Doktor ve klinik seçimi</div><div class="register-benefit">Gerçek zamanlı uygun saatler</div><div class="register-benefit">Randevuları görüntüleme ve iptal</div></div></aside><div class="register-form"><h2>Hesap oluştur</h2><?php if($message!==''): ?><div class="alert <?php echo htmlspecialchars($messageType,ENT_QUOTES,'UTF-8'); ?>" role="alert"><?php echo htmlspecialchars($message,ENT_QUOTES,'UTF-8'); ?></div><?php endif; ?><form method="post" action="signup.php"><div class="form-grid"><div class="field field-full"><label for="fname">Ad soyad</label><input id="fname" type="text" name="fname" maxlength="30" autocomplete="name" required></div><div class="field"><label for="dob">Doğum tarihi</label><input id="dob" type="date" name="dob" required></div><div class="field"><label for="gender">Cinsiyet</label><select id="gender" name="gender" required><option value="">Seçin</option><option value="female">Kadın</option><option value="male">Erkek</option><option value="other">Belirtmek istemiyorum</option></select></div><div class="field"><label for="contact">Telefon</label><input id="contact" type="tel" name="contact" autocomplete="tel" required></div><div class="field"><label for="email">E-posta</label><input id="email" type="email" name="email" maxlength="30" autocomplete="email" required></div><div class="field"><label for="username">Kullanıcı adı</label><input id="username" type="text" name="username" maxlength="20" autocomplete="username" required></div><div class="field"><label for="pwd">Şifre</label><input id="pwd" type="password" name="pwd" minlength="8" autocomplete="new-password" required></div><div class="field"><label for="pwdr">Şifre tekrar</label><input id="pwdr" type="password" name="pwdr" minlength="8" autocomplete="new-password" required></div></div><button class="button" type="submit" name="signup" value="1">Hesap Oluştur</button></form><a class="register-back" href="cover.php">← Giriş sayfasına dön</a></div></section></main>
</body></html>