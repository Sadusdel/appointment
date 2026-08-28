<?php
session_start();
include 'dbconfig.php';

$username = $_SESSION['username'] ?? '';
if ($username === '') {
    header('Location: ulogin.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$message = '';
$messageType = '';
$date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';
    $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

    if (!is_string($postedToken) || !hash_equals($csrfToken, $postedToken)) {
        $message = 'Geçersiz güvenlik doğrulaması. Lütfen sayfayı yenileyip tekrar deneyin.';
        $messageType = 'error';
    } elseif (!$appointmentId || $appointmentId < 1) {
        $message = 'Geçersiz randevu seçimi.';
        $messageType = 'error';
    } else {
        $update = $conn->prepare("UPDATE book SET Status = 'İptal Edildi', active_slot_key = NULL WHERE appointment_id = ? AND Username = ? AND DOV >= ? AND Status NOT IN ('İptal Edildi', 'Tamamlandı')");
        $update->bind_param('iss', $appointmentId, $username, $date);
        if ($update->execute() && $update->affected_rows > 0) {
            $message = 'Randevunuz başarıyla iptal edildi.';
            $messageType = 'success';
        } else {
            $message = 'Randevu iptal edilemedi veya randevu zaten pasif durumda.';
            $messageType = 'error';
        }
        $update->close();
    }
}

$appointments = [];
$list = $conn->prepare("SELECT appointment_id, Fname, DID, CID, DOV, appointment_time FROM book WHERE Username = ? AND DOV >= ? AND Status NOT IN ('İptal Edildi', 'Tamamlandı') ORDER BY DOV ASC, appointment_time ASC");
$list->bind_param('ss', $username, $date);
$list->execute();
$result = $list->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$list->close();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Randevu İptali | Appointment</title>
<link rel="stylesheet" href="main.css">
<style>
body.cancel-page{min-height:100vh;margin:0;background:linear-gradient(135deg,rgba(15,31,58,.88),rgba(23,105,224,.68)),url('images/cancelback.jpg') center/cover fixed;font-family:Arial,Helvetica,sans-serif;color:#172033}
.cancel-header{height:70px;background:rgba(255,255,255,.97);display:flex;align-items:center;box-shadow:0 2px 18px rgba(0,0,0,.12)}
.cancel-header-inner{width:min(1100px,calc(100% - 32px));margin:auto;display:flex;align-items:center;justify-content:space-between;gap:20px}
.cancel-brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:#172033;font-size:19px;font-weight:800}.cancel-brand img{width:36px;height:36px;object-fit:contain}
.cancel-home{color:#526176;text-decoration:none;font-size:14px;font-weight:700}.cancel-home:hover{color:#1769e0}
.cancel-main{width:min(720px,calc(100% - 32px));margin:64px auto}
.cancel-card{background:#fff;border-radius:22px;padding:34px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.cancel-eyebrow{display:inline-block;color:#1769e0;font-size:11px;font-weight:800;letter-spacing:1.2px;margin-bottom:8px}.cancel-card h1{margin:0;color:#172033;font-size:30px}.cancel-intro{margin:9px 0 28px;color:#748094;font-size:14px;line-height:1.6}
.cancel-field label{display:block;margin-bottom:8px;color:#374151;font-size:13px;font-weight:800}.cancel-select{width:100%;height:50px;padding:0 14px;border:1px solid #d9dfe8;border-radius:10px;background:#fff;color:#263148;font-size:14px;box-sizing:border-box;outline:none}.cancel-select:focus{border-color:#1769e0;box-shadow:0 0 0 3px rgba(23,105,224,.1)}
.cancel-actions{display:flex;gap:10px;margin-top:22px}.cancel-button,.cancel-back{height:46px;border:0;border-radius:10px;padding:0 20px;font-size:14px;font-weight:800;cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;box-sizing:border-box}.cancel-button{background:#c6283d;color:#fff;flex:1}.cancel-button:hover{background:#a91f32}.cancel-back{background:#f1f4f8;color:#4f5c70}.cancel-back:hover{background:#e7ebf1}
.cancel-alert{margin-top:20px;padding:13px 15px;border-radius:10px;font-size:13px;font-weight:700}.cancel-alert.success{background:#edf9f1;border:1px solid #c9ecd5;color:#237a42}.cancel-alert.error{background:#fff0f0;border:1px solid #ffd4d8;color:#a61b2b}
.cancel-empty{text-align:center;padding:34px 20px;border:1px dashed #d7dee8;border-radius:14px;background:#fafbfd}.cancel-empty-icon{font-size:30px;margin-bottom:8px}.cancel-empty strong{display:block;color:#263148;margin-bottom:5px}.cancel-empty span{color:#7b8798;font-size:13px}
.cancel-warning{margin-top:18px;padding:13px 15px;border-radius:10px;background:#fff8e8;border:1px solid #f3dfaa;color:#725a1b;font-size:12px;line-height:1.5}
@media(max-width:600px){.cancel-main{margin:28px auto}.cancel-card{padding:25px 20px;border-radius:17px}.cancel-card h1{font-size:26px}.cancel-actions{flex-direction:column}.cancel-button{order:1}.cancel-back{order:2}.cancel-header{height:auto;padding:15px 0}}
</style>
</head>
<body class="cancel-page">
<header class="cancel-header"><div class="cancel-header-inner"><a class="cancel-brand" href="ulogin.php"><img src="images/cal.png" alt="Appointment"><span>Appointment</span></a><a class="cancel-home" href="ulogin.php">Hasta Paneline Dön</a></div></header>
<main class="cancel-main"><section class="cancel-card">
<span class="cancel-eyebrow">RANDEVU YÖNETİMİ</span>
<h1>Randevu İptali</h1>
<p class="cancel-intro">İptal etmek istediğiniz aktif randevuyu seçin. Tamamlanmış randevular ve daha önce iptal edilen randevular burada gösterilmez.</p>
<?php if ($appointments): ?>
<form action="cancelbookingpatient.php" method="post" onsubmit="return confirm('Seçtiğiniz randevuyu iptal etmek istediğinize emin misiniz?');">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
<div class="cancel-field"><label for="appointment-list">İptal edilecek randevu</label><select name="appointment_id" id="appointment-list" class="cancel-select" required><option value="">Randevu seçin</option><?php foreach ($appointments as $appointment): ?><option value="<?php echo (int)$appointment['appointment_id']; ?>"><?php echo htmlspecialchars('Hasta: ' . $appointment['Fname'] . ' • ' . $appointment['DOV'] . ' • ' . substr($appointment['appointment_time'], 0, 5) . ' • Randevu No: ' . $appointment['appointment_id'], ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
<div class="cancel-actions"><a class="cancel-back" href="ulogin.php">Vazgeç</a><button class="cancel-button" type="submit">Randevuyu İptal Et</button></div>
</form>
<div class="cancel-warning">İptal işlemi yalnızca size ait, gelecekteki ve aktif randevular için uygulanır. İşlem tamamlandıktan sonra randevu tekrar aktif hale getirilmez.</div>
<?php else: ?><div class="cancel-empty"><div class="cancel-empty-icon">✓</div><strong>İptal edilebilir randevunuz yok</strong><span>Aktif ve gelecekteki randevularınız burada görünecektir.</span></div><?php endif; ?>
<?php if ($message !== ''): ?><div class="cancel-alert <?php echo $messageType === 'success' ? 'success' : 'error'; ?>" role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
</section></main>
</body>
</html>