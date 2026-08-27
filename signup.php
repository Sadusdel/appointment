<?php
$message = '';
$messageType = '';

if (isset($_POST['signup'])) {
    $required = ['fname', 'dob', 'gender', 'contact', 'username', 'email', 'pwd', 'pwdr'];
    $valid = true;
    foreach ($required as $field) {
        if (empty($_POST[$field])) { $valid = false; break; }
    }

    if (!$valid) {
        $message = 'Lütfen tüm zorunlu alanları doldurun.';
        $messageType = 'error';
    } elseif ($_POST['pwd'] !== $_POST['pwdr']) {
        $message = 'Şifreler eşleşmiyor.';
        $messageType = 'error';
    } else {
        include 'dbconfig.php';
        $username = $_POST['username'];
        $check = $conn->prepare('SELECT Username FROM Patient WHERE Username = ? LIMIT 1');
        $check->bind_param('s', $username);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();

        if ($exists) {
            $message = 'Bu kullanıcı adı zaten kullanılıyor.';
            $messageType = 'error';
        } else {
            $name = $_POST['fname'];
            $gender = $_POST['gender'];
            $dob = $_POST['dob'];
            $contact = $_POST['contact'];
            $email = $_POST['email'];
            $password = $_POST['pwd'];

            $stmt = $conn->prepare('INSERT INTO Patient (Name, Gender, DOB, Contact, Email, Username, Password) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('sssssss', $name, $gender, $dob, $contact, $email, $username, $password);
            if ($stmt->execute()) {
                $message = 'Hesabınız başarıyla oluşturuldu. Giriş sayfasına yönlendiriliyorsunuz.';
                $messageType = 'success';
                header('Refresh:2; url=cover.php');
            } else {
                $message = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
                $messageType = 'error';
            }
            $stmt->close();
        }
        mysqli_close($conn);
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
        .benefit { display: flex; gap: 10px; align-items: flex-start; color: rgba(255,255,255,.92); font-size: 14px; line-height: 1.5; }
        .benefit span { width: 23px; height: 23px; flex: 0 0 23px; display: grid; place-items: center; border-radius: 50%; background: rgba(255,255,255,.16); font-size: 12px; }
        .register-form { padding: 42px; background: #fff; }
        .register-form-header { margin-bottom: 24px; }
        .register-form-header h2 { font-size: 27px; }
        .gender-options { display: flex; gap: 8px; flex-wrap: wrap; }
        .gender-option { display: inline-flex; align-items: center; gap: 7px; padding: 10px 13px; border: 1px solid var(--border-strong); border-radius: 10px; color: #566174; cursor: pointer; }
        .gender-option:has(input:checked) { color: #1557b0; border-color: #8db8f2; background: #eef5ff; }
        .gender-option input { margin: 0; }
        .terms { margin: 18px 0 0; font-size: 12px; color: var(--muted); }
        .terms a { color: var(--primary); font-weight: 700; }
        .form-actions { display: flex; gap: 10px; margin-top: 22px; }
        .form-actions .button { flex: 1; }
        @media(max-width:760px) {
            .register-layout { width: min(100% - 20px, 900px); margin-top: 20px; }
            .register-card { grid-template-columns: 1fr; }
            .register-info { padding: 26px 22px; }
            .register-info h1 { font-size: 28px; }
            .register-benefits { margin-top: 20px; }
            .register-form { padding: 24px 18px; }
            .register-header nav a:not(.active) { display: none; }
        }
    </style>
</head>
<body class="register-page">
<header class="site-header register-header">
    <div class="header-inner">
        <a href="cover.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a>
        <nav><a href="cover.php">Ana Sayfa</a><a class="active" href="signup.php">Kayıt Ol</a></nav>
    </div>
</header>

<main class="register-layout">
    <section class="card register-card">
        <aside class="register-info">
            <span class="eyebrow" style="color:#d9e9ff">APPOINTMENT</span>
            <h1>Randevu yönetimini kolaylaştırın.</h1>
            <p>Ücretsiz hasta hesabınızı oluşturun ve size uygun doktor ile zamanı birkaç adımda seçin.</p>
            <div class="register-benefits">
                <div class="benefit"><span>✓</span><div>Randevularınızı tek ekrandan takip edin.</div></div>
                <div class="benefit"><span>✓</span><div>Uygun tarih ve saatleri kolayca görüntüleyin.</div></div>
                <div class="benefit"><span>✓</span><div>Randevu durumunuzu anlık olarak takip edin.</div></div>
            </div>
        </aside>
        <div class="register-form">
            <div class="register-form-header">
                <span class="eyebrow">HESAP OLUŞTUR</span>
                <h2>Hasta kaydı</h2>
                <p>Bilgilerinizi eksiksiz doldurarak hesabınızı oluşturun.</p>
            </div>
            <?php if ($message !== ''): ?><div class="alert <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
            <form action="signup.php" method="post">
                <div class="form-grid">
                    <div class="field field-full"><label for="fname">Ad Soyad</label><input id="fname" type="text" name="fname" placeholder="Adınızı ve soyadınızı girin" required value="<?php echo htmlspecialchars($_POST['fname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="field"><label for="dob">Doğum tarihi</label><input id="dob" type="date" name="dob" required value="<?php echo htmlspecialchars($_POST['dob'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="field"><label for="contact">Telefon</label><input id="contact" type="tel" name="contact" placeholder="05XX XXX XX XX" required value="<?php echo htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="field field-full"><label>Cinsiyet</label><div class="gender-options"><label class="gender-option"><input type="radio" name="gender" value="female" required> Kadın</label><label class="gender-option"><input type="radio" name="gender" value="male"> Erkek</label><label class="gender-option"><input type="radio" name="gender" value="other"> Belirtmek istemiyorum</label></div></div>
                    <div class="field"><label for="username">Kullanıcı adı</label><input id="username" type="text" name="username" placeholder="Kullanıcı adınız" autocomplete="username" required></div>
                    <div class="field"><label for="email">E-posta</label><input id="email" type="email" name="email" placeholder="ornek@mail.com" autocomplete="email" required></div>
                    <div class="field"><label for="pwd">Şifre</label><input id="pwd" type="password" name="pwd" placeholder="Şifreniz" autocomplete="new-password" required></div>
                    <div class="field"><label for="pwdr">Şifre tekrar</label><input id="pwdr" type="password" name="pwdr" placeholder="Şifrenizi tekrar girin" autocomplete="new-password" required></div>
                </div>
                <p class="terms">Kayıt olarak kullanım koşullarını kabul etmiş olursunuz.</p>
                <div class="form-actions"><a href="cover.php" class="button secondary">Geri Dön</a><button type="submit" name="signup" class="button">Hesap Oluştur</button></div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
