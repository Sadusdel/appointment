<?php
session_start();
if (empty($_SESSION['username'])) { header('Location: cover.php'); exit; }
$user = $_SESSION['user'] ?? $_SESSION['username'];
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hasta Paneli</title><link rel="stylesheet" href="main.css"></head>
<body class="booking-page">
<header class="site-header"><div class="header-inner"><a href="patient_dashboard.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a><nav><a class="active" href="patient_dashboard.php">Randevularım</a><a href="book.php">Randevu Al</a><a href="ulocateus.php">Klinikler</a><a href="logout.php">Çıkış</a></nav></div></header>
<main class="booking-wrapper"><section class="booking-card"><div class="booking-intro"><span class="eyebrow">HOŞ GELDİNİZ</span><h1><?php echo htmlspecialchars($user); ?></h1><p>Randevularınızı yönetin ve yeni bir randevu oluşturun.</p></div><div class="dashboard-actions"><a class="primary-button" href="patient_dashboard.php">Randevularımı Gör</a><a class="secondary-button" href="book.php">Yeni Randevu Al</a></div></section></main>
</body></html>
