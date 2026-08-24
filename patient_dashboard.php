<?php
session_start();
require_once 'dbconfig.php';

if (empty($_SESSION['username'])) {
    header('Location: cover.php');
    exit;
}

$username = $_SESSION['username'];
$stmt = $conn->prepare("SELECT b.appointment_id, b.Fname, b.DOV, b.appointment_time, b.Status, b.Timestamp, d.name AS doctor_name, c.name AS clinic_name, c.town FROM book b LEFT JOIN doctor d ON d.did=b.DID LEFT JOIN clinic c ON c.CID=b.CID WHERE b.Username=? ORDER BY b.DOV DESC, b.appointment_time DESC");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hasta Paneli | Appointment</title><link rel="stylesheet" href="main.css">
</head>
<body class="booking-page">
<header class="site-header"><div class="header-inner">
<a href="patient_dashboard.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a>
<nav><a class="active" href="patient_dashboard.php">Randevularım</a><a href="book.php">Yeni Randevu</a><a href="logout.php">Çıkış</a></nav>
</div></header>
<main class="booking-wrapper">
<section class="booking-card">
<div class="booking-intro"><span class="eyebrow">HASTA PANELİ</span><h1>Randevularım</h1><p>Aktif ve geçmiş randevularınızı buradan takip edebilirsiniz.</p></div>
<div class="appointment-list">
<?php if ($result->num_rows === 0): ?>
<div class="alert">Henüz kayıtlı randevunuz bulunmuyor.</div>
<?php else: while ($row = $result->fetch_assoc()): ?>
<article class="appointment-item">
<div><strong><?php echo htmlspecialchars($row['Fname']); ?></strong><span><?php echo htmlspecialchars($row['clinic_name'] ?? 'Klinik'); ?><?php echo !empty($row['town']) ? ' · '.htmlspecialchars($row['town']) : ''; ?></span></div>
<div><strong><?php echo htmlspecialchars($row['doctor_name'] ?? 'Doktor'); ?></strong><span><?php echo date('d.m.Y', strtotime($row['DOV'])); ?><?php if (!empty($row['appointment_time'])) echo ' · '.substr($row['appointment_time'],0,5); ?></span></div>
<div><span class="status-pill"><?php echo htmlspecialchars($row['Status']); ?></span><small>Oluşturulma: <?php echo htmlspecialchars($row['Timestamp']); ?></small></div>
</article>
<?php endwhile; endif; ?>
</div>
<a class="primary-button" href="book.php" style="display:block;text-align:center;margin-top:22px">Yeni Randevu Al</a>
</section></main>
</body></html>
<?php $stmt->close(); ?>
