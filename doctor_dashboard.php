<?php
session_start();
require_once 'dbconfig.php';

// Temporary doctor selection for the legacy system. A proper doctor login will replace this.
$doctorId = isset($_GET['did']) ? (int)$_GET['did'] : (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    $doctorResult = $conn->query('SELECT did, name FROM doctor ORDER BY name ASC LIMIT 1');
    $doctor = $doctorResult ? $doctorResult->fetch_assoc() : null;
    $doctorId = $doctor ? (int)$doctor['did'] : 0;
} else {
    $stmtDoctor = $conn->prepare('SELECT did, name FROM doctor WHERE did=? LIMIT 1');
    $stmtDoctor->bind_param('i', $doctorId);
    $stmtDoctor->execute();
    $doctor = $stmtDoctor->get_result()->fetch_assoc();
    $stmtDoctor->close();
}
if (!$doctorId || !$doctor) { die('Doktor bulunamadı.'); }

$_SESSION['doctor_id'] = $doctorId;
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);
if (!$dateObj) $selectedDate = date('Y-m-d');

$stmt = $conn->prepare("SELECT b.appointment_id,b.Fname,b.Gender,b.appointment_time,b.DOV,b.Status,b.Timestamp,c.name AS clinic_name,c.town FROM book b LEFT JOIN clinic c ON c.CID=b.CID WHERE b.DID=? AND b.DOV=? ORDER BY b.appointment_time ASC, b.Timestamp ASC");
$stmt->bind_param('is', $doctorId, $selectedDate);
$stmt->execute();
$appointments = $stmt->get_result();

$weekStart = new DateTime($selectedDate);
$weekStart->modify('monday this week');
$weekEnd = (clone $weekStart)->modify('+6 days');
$stmtWeek = $conn->prepare("SELECT DOV, COUNT(*) total FROM book WHERE DID=? AND DOV BETWEEN ? AND ? GROUP BY DOV ORDER BY DOV");
$ws = $weekStart->format('Y-m-d'); $we = $weekEnd->format('Y-m-d');
$stmtWeek->bind_param('iss', $doctorId, $ws, $we);
$stmtWeek->execute();
$weekCounts = [];
$resWeek = $stmtWeek->get_result();
while ($r = $resWeek->fetch_assoc()) $weekCounts[$r['DOV']] = (int)$r['total'];
$stmtWeek->close();
?>
<!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Doktor Paneli | Appointment</title><link rel="stylesheet" href="main.css">
<style>
.doctor-toolbar{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin:0 0 24px}.doctor-toolbar .field{flex:1;min-width:190px}.doctor-toolbar .primary-button{width:auto;display:inline-block}.doctor-title{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:22px}.doctor-title h2{margin:0}.doctor-title p{margin:5px 0 0;color:#687386}.schedule{display:grid;gap:12px}.schedule-row{display:grid;grid-template-columns:90px 1fr auto;gap:16px;align-items:center;border:1px solid #e4e9f0;border-radius:14px;padding:16px;background:#fff}.schedule-time{font-size:20px;font-weight:800;color:#1769e0}.patient-name{font-weight:800;color:#202a3b}.patient-meta{display:block;color:#687386;font-size:13px;margin-top:5px}.status-pill{display:inline-flex;align-items:center;max-width:230px;padding:7px 10px;border-radius:999px;background:#eef4ff;color:#1769e0;font-size:12px;font-weight:750}.week-strip{display:grid;grid-template-columns:repeat(7,1fr);gap:8px;margin-bottom:24px}.day-card{padding:12px 8px;border:1px solid #e2e7ef;border-radius:12px;text-align:center;color:#566174;background:#f9fbfd}.day-card.active{border-color:#1769e0;background:#eef4ff;color:#1769e0}.day-card strong{display:block;font-size:13px}.day-card span{display:block;margin-top:5px;font-size:12px}.empty-state{text-align:center;padding:35px 15px;border:1px dashed #d8dee8;border-radius:14px;color:#687386}@media(max-width:650px){.schedule-row{grid-template-columns:70px 1fr}.schedule-row .status-pill{grid-column:2}.week-strip{grid-template-columns:repeat(4,1fr)}.doctor-title{align-items:flex-start;flex-direction:column}}
</style></head>
<body class="booking-page">
<header class="site-header"><div class="header-inner"><a href="doctor_dashboard.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment · Doktor</span></a><nav><a class="active" href="doctor_dashboard.php?did=<?php echo $doctorId; ?>">Takvim</a><a href="book.php">Randevu Al</a></nav></div></header>
<main class="booking-wrapper"><section class="booking-card">
<div class="doctor-title"><div><span class="eyebrow">DOKTOR PANELİ</span><h1><?php echo htmlspecialchars($doctor['name']); ?></h1><p><?php echo $dateObj->format('d.m.Y'); ?> tarihli randevular</p></div><span class="status-pill"><?php echo $appointments->num_rows; ?> randevu</span></div>
<form class="doctor-toolbar" method="get"><input type="hidden" name="did" value="<?php echo $doctorId; ?>"><div class="field"><label for="date">Tarih seçin</label><input id="date" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate); ?>"></div><button class="primary-button" type="submit">Takvimi Göster</button></form>
<div class="week-strip">
<?php for($i=0;$i<7;$i++): $day=(clone $weekStart)->modify("+$i day"); $ds=$day->format('Y-m-d'); ?>
<a class="day-card <?php echo $ds===$selectedDate?'active':''; ?>" href="doctor_dashboard.php?did=<?php echo $doctorId; ?>&date=<?php echo $ds; ?>"><strong><?php echo $day->format('D'); ?></strong><span><?php echo $day->format('d.m'); ?> · <?php echo $weekCounts[$ds] ?? 0; ?></span></a>
<?php endfor; ?></div>
<div class="schedule">
<?php if($appointments->num_rows===0): ?><div class="empty-state">Bu tarihte kayıtlı randevu bulunmuyor.</div>
<?php else: while($row=$appointments->fetch_assoc()): ?><article class="schedule-row"><div class="schedule-time"><?php echo !empty($row['appointment_time']) ? substr($row['appointment_time'],0,5) : '--:--'; ?></div><div><span class="patient-name"><?php echo htmlspecialchars($row['Fname']); ?></span><span class="patient-meta"><?php echo htmlspecialchars($row['Gender']); ?> · <?php echo htmlspecialchars(($row['clinic_name'] ?? 'Klinik').(!empty($row['town'])?' · '.$row['town']:'')); ?></span></div><span class="status-pill"><?php echo htmlspecialchars($row['Status']); ?></span></article><?php endwhile; endif; ?>
</div></section></main></body></html>
<?php $stmt->close(); ?>
