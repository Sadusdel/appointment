<?php
session_start();
$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['uname'] ?? '');
    $password = (string)($_POST['psw'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        require 'dbconfig.php';
        $stmt = $conn->prepare('SELECT username,name,password FROM patient WHERE username=? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $patient = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $valid = false;
        if ($patient) {
            $storedPassword = (string)$patient['password'];
            $valid = password_verify($password, $storedPassword);

            // One-time migration for existing plaintext passwords.
            if (!$valid && hash_equals($storedPassword, $password)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $upgrade = $conn->prepare('UPDATE patient SET password=? WHERE username=? LIMIT 1');
                $upgrade->bind_param('ss', $newHash, $patient['username']);
                $upgrade->execute();
                $upgrade->close();
                $valid = true;
            }
        }

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['username'] = $patient['username'];
            $_SESSION['user'] = $patient['name'];
            header('Location: ulogin.php');
            exit;
        }

        $error = 'Kullanıcı adı veya şifre geçersiz.';
        $conn->close();
    }
}
?>
<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Appointment | Randevu Sistemi</title><link rel="stylesheet" href="main.css"><style>.landing{min-height:100vh;display:flex;flex-direction:column;background:linear-gradient(135deg,rgba(244,247,251,.97),rgba(232,241,255,.94)),url('images/p2.jpg') center/cover}.landing-header .header-inner{min-height:76px}.landing-main{flex:1;display:grid;place-items:center;padding:60px 20px}.landing-content{width:min(760px,100%);text-align:center}.landing-logo{width:68px;height:68px;margin:0 auto 20px;padding:13px;border-radius:20px;background:#fff;box-shadow:0 14px 34px rgba(25,45,80,.12)}.landing-logo img{width:100%;height:100%;object-fit:contain}.landing-content h1{margin-bottom:14px;font-size:clamp(38px,7vw,64px);letter-spacing:-.03em}.landing-content .lead{max-width:620px;margin:0 auto 28px;color:#536075;font-size:clamp(17px,2.5vw,21px)}.landing-actions{display:flex;justify-content:center;gap:12px;flex-wrap:wrap}.landing-actions .button{min-width:150px}.landing-footer{padding:20px;text-align:center}.landing-footer a{color:#687386;font-size:13px;font-weight:700;margin:0 10px}.landing-footer a:hover{color:var(--primary)}.modal{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(16,28,48,.52);backdrop-filter:blur(4px)}.modal.open{display:flex}.modal-content{width:min(460px,100%);max-height:calc(100vh - 40px);overflow:auto;border:1px solid var(--border);border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(16,28,48,.25)}.modal-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:24px 26px 0}.modal-head h2{margin:0;font-size:23px}.modal-body{padding:24px 26px}.modal-footer{display:flex;gap:10px;justify-content:flex-end;padding:16px 26px 24px;border-top:1px solid var(--border)}.modal-footer .button{width:auto}.close{display:grid;place-items:center;width:36px;height:36px;flex:0 0 36px;border:0;border-radius:50%;background:#f2f5f9;color:#566174;font-size:24px;line-height:1;cursor:pointer}.close:hover{background:#e6ebf2}.login-note{margin:0 0 20px;color:var(--text-soft);font-size:14px}.login-error{margin-bottom:18px}.signup-modal{width:min(560px,100%)}.steps-image{display:block;width:min(280px,80%);max-height:180px;object-fit:contain;margin:0 auto 18px}.signup-copy{text-align:center}.signup-copy strong{color:var(--text)}@media(max-width:600px){.landing-main{padding:36px 16px}.landing-actions{flex-direction:column;width:min(320px,100%);margin:0 auto}.landing-actions .button{width:100%}.modal{padding:10px}.modal-content{border-radius:16px}.modal-head,.modal-body{padding-left:18px;padding-right:18px}.modal-footer{padding-left:18px;padding-right:18px;flex-direction:column-reverse}.modal-footer .button{width:100%}}</style></head><body class="landing"><header class="site-header landing-header"><div class="header-inner"><a href="cover.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a><nav><a class="active" href="cover.php">Ana Sayfa</a><a href="locateus.php">Bize Ulaşın</a></nav></div></header><main class="landing-main"><section class="landing-content"><div class="landing-logo"><img src="images/cal.png" alt="Appointment"></div><span class="eyebrow">ONLINE RANDEVU SİSTEMİ</span><h1>Sağlık randevunuzu kolayca yönetin.</h1><p class="lead">Doktorunuzu, kliniğinizi ve size uygun zamanı seçin. Randevunuzu tek bir ekrandan oluşturun ve takip edin.</p><div class="landing-actions"><button type="button" class="button" onclick="openModal('loginModal')">Giriş Yap</button><button type="button" class="button secondary" onclick="openModal('signupModal')">Yeni Hesap Oluştur</button></div></section></main><footer class="landing-footer"><a href="admin/alogin.php">Yönetici Girişi</a><a href="admin/mlogin.php">Manager Girişi</a><a href="Admin/dlogin.php">Doktor Girişi</a></footer><div id="loginModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="loginTitle"><form class="modal-content" method="post" action="cover.php"><div class="modal-head"><h2 id="loginTitle">Hoş geldiniz</h2><button type="button" class="close" aria-label="Kapat" onclick="closeModal('loginModal')">&times;</button></div><div class="modal-body"><p class="login-note">Hasta hesabınızla giriş yaparak randevularınızı yönetin.</p><?php if($error!==''):?><div class="alert error login-error"><?php echo htmlspecialchars($error,ENT_QUOTES,'UTF-8');?></div><?php endif;?><div class="field"><label for="uname">Kullanıcı adı</label><input id="uname" type="text" name="uname" placeholder="Kullanıcı adınız" autocomplete="username" required></div><div class="field" style="margin-top:16px"><label for="psw">Şifre</label><input id="psw" type="password" name="psw" placeholder="Şifreniz" autocomplete="current-password" required></div></div><div class="modal-footer"><button type="button" class="button secondary" onclick="closeModal('loginModal')">Vazgeç</button><button type="submit" name="login" class="button">Giriş Yap</button></div></form></div><div id="signupModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="signupTitle"><div class="modal-content signup-modal"><div class="modal-head"><h2 id="signupTitle">Yeni hesap oluştur</h2><button type="button" class="close" aria-label="Kapat" onclick="closeModal('signupModal')">&times;</button></div><div class="modal-body signup-copy"><img class="steps-image" src="images/steps.png" alt="Randevu oluşturma adımları"><p><strong>Hesabınızı oluşturun → Tarihinizi seçin → Randevunuzu oluşturun</strong></p><p>Randevu almak hiç bu kadar kolay olmamıştı.</p></div><div class="modal-footer"><button type="button" class="button secondary" onclick="closeModal('signupModal')">Vazgeç</button><a href="signup.php" class="button">Kayıt Ol</a></div></div></div><script>function openModal(id){document.getElementById(id).classList.add('open');}function closeModal(id){document.getElementById(id).classList.remove('open');}window.addEventListener('click',function(e){if(e.target.classList.contains('modal'))e.target.classList.remove('open');});window.addEventListener('keydown',function(e){if(e.key==='Escape')document.querySelectorAll('.modal.open').forEach(function(m){m.classList.remove('open');});}</script></body></html>