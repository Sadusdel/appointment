<?php
session_start();
require_once 'dbconfig.php';

if (empty($_SESSION['username'])) {
    header('Location: cover.php');
    exit;
}

$username = $_SESSION['username'];
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);
    if ($appointmentId <= 0) {
        $message = 'Geçersiz randevu.';
        $messageType = 'error';
    } else {
        $cancelStmt = $conn->prepare("UPDATE book SET Status = 'İptal Edildi' WHERE appointment_id = ? AND Username = ? AND DOV >= CURDATE() AND Status NOT IN ('Tamamlandı', 'İptal Edildi')");
        $cancelStmt->bind_param('is', $appointmentId, $username);
        if ($cancelStmt->execute() && $cancelStmt->affected_rows > 0) {
            $message = 'Randevunuz başarıyla iptal edildi.';
            $messageType = 'success';
        } else {
            $message = 'Randevu iptal edilemedi. Randevu zaten iptal edilmiş, tamamlanmış veya size ait olmayabilir.';
            $messageType = 'error';
        }
        $cancelStmt->close();
    }
}

$stmt = $conn->prepare("SELECT b.appointment_id, b.Fname, b.DOV, b.appointment_time, b.Status, b.Timestamp, d.name AS doctor_name, c.name AS clinic_name, c.town FROM book b LEFT JOIN doctor d ON d.did = b.DID LEFT JOIN clinic c ON c.CID = b.CID WHERE b.Username = ? ORDER BY b.DOV DESC, b.appointment_time DESC");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

function getStatusClass($status) {
    switch (trim((string)$status)) {
        case 'Onaylandı': return 'status-approved';
        case 'Tamamlandı': return 'status-completed';
        case 'İptal Edildi':
        case 'Cancelled by Patient': return 'status-cancelled';
        default: return 'status-pending';
    }
}
function getStatusText($status) {
    switch (trim((string)$status)) {
        case 'Booked': return 'Bekliyor';
        case 'Cancelled by Patient': return 'İptal Edildi';
        default: return trim((string)$status) !== '' ? trim((string)$status) : 'Bekliyor';
    }
}
function canCancelAppointment($row) {
    $status = trim((string)$row['Status']);
    if (in_array($status, ['Tamamlandı', 'İptal Edildi', 'Cancelled by Patient'], true)) return false;
    return !empty($row['DOV']) && strtotime($row['DOV']) >= strtotime(date('Y-m-d'));
}

$appointments = [];
while ($row = $result->fetch_assoc()) $appointments[] = $row;

$totalCount = count($appointments);
$activeCount = 0;
$approvedCount = 0;
$nextAppointment = null;
$now = strtotime(date('Y-m-d H:i:s'));

foreach ($appointments as $row) {
    $statusText = getStatusText($row['Status']);
    if ($statusText !== 'İptal Edildi' && $statusText !== 'Tamamlandı') $activeCount++;
    if ($statusText === 'Onaylandı') $approvedCount++;
    if ($statusText !== 'İptal Edildi' && $statusText !== 'Tamamlandı' && !empty($row['DOV'])) {
        $appointmentTimestamp = strtotime($row['DOV'] . ' ' . ($row['appointment_time'] ?: '00:00:00'));
        if ($appointmentTimestamp >= $now && ($nextAppointment === null || $appointmentTimestamp < $nextAppointment['_timestamp'])) {
            $row['_timestamp'] = $appointmentTimestamp;
            $nextAppointment = $row;
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hasta Paneli | Appointment</title>
<link rel="stylesheet" href="main.css">
<style>
.patient-dashboard .dashboard-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:24px}.dashboard-hero-copy{min-width:0}.dashboard-hero h1{margin-bottom:8px}.dashboard-hero p{margin:0}.dashboard-actions{display:flex;gap:10px;flex-wrap:wrap}.dashboard-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:11px;font-weight:800}.dashboard-actions .secondary{background:#eef4fc;color:#185abc;border:1px solid #d7e4f5}.dashboard-actions .secondary:hover{background:#e2edfb}.dashboard-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}.dashboard-stat{padding:17px 18px;border:1px solid #e3e8f0;border-radius:14px;background:#fbfcfe}.dashboard-stat span{display:block;color:#778196;font-size:12px;font-weight:700}.dashboard-stat strong{display:block;margin-top:5px;color:#172033;font-size:24px}.next-appointment{margin-bottom:24px;padding:20px;border:1px solid #cfe0f7;border-radius:16px;background:linear-gradient(135deg,#f3f8ff,#fff)}.next-appointment .next-label{color:#1769e0;font-size:12px;font-weight:850;letter-spacing:.07em}.next-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:18px;margin-top:10px}.next-grid span,.appointment-status-label{display:block;color:#778196;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.04em}.next-grid strong{display:block;margin-top:4px;color:#172033}.appointment-list{display:grid;gap:12px}.appointment-item{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;align-items:start;padding:20px;border:1px solid #e3e8f0;border-radius:15px;background:#fff}.appointment-item>div{min-width:0}.appointment-item strong{display:block;color:#263148}.appointment-item>div>span{display:block;margin-top:5px;color:#687386;font-size:13px;line-height:1.5}.patient-status-card{margin-top:0}.status-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;margin-top:3px;border-radius:999px;font-size:13px;font-weight:700}.status-approved{background:#e8f7ee;color:#137333}.status-completed{background:#e9f1ff;color:#185abc}.status-cancelled{background:#fdecec;color:#b3261e}.status-pending{background:#fff5d9;color:#8a5a00}.appointment-actions{margin-top:12px;display:flex;justify-content:flex-end}.cancel-button{border:0;border-radius:9px;padding:9px 14px;background:#fff0f0;color:#a51d2d;font-weight:700;cursor:pointer}.cancel-button:hover{background:#ffe0e0}.panel-message{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-weight:600}.panel-message.success{background:#eaf8f0;color:#146c3a}.panel-message.error{background:#fff0f0;color:#a51d2d}.empty-appointments{text-align:center;padding:36px 20px;border:1px dashed #d4dae4;border-radius:15px;background:#fbfcfe;color:#687386}.empty-appointments strong{display:block;color:#263148;margin-bottom:6px}.empty-appointments a{display:inline-flex;margin-top:16px;padding:10px 15px;border-radius:10px;background:#1769e0;color:#fff;font-weight:800}@media(max-width:700px){.patient-dashboard .dashboard-hero{display:block}.dashboard-actions{margin-top:16px}.dashboard-actions a{flex:1}.dashboard-stats{grid-template-columns:1fr 1fr}.next-grid{grid-template-columns:1fr 1fr}.appointment-item{grid-template-columns:1fr;gap:14px}.appointment-actions{justify-content:stretch}.appointment-actions form,.cancel-button{width:100%}}@media(max-width:390px){.dashboard-stats,.next-grid{grid-template-columns:1fr}}
</style>
</head>
<body class="booking-page patient-dashboard">
<header class="site-header"><div class="header-inner"><a href="patient_dashboard.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a><nav><a class="active" href="patient_dashboard.php">Randevularım</a><a href="book.php">Yeni Randevu</a><a href="logout.php">Çıkış</a></nav></div></header>
<main class="booking-wrapper">
<section class="booking-card">
<div class="dashboard-hero"><div class="dashboard-hero-copy"><span class="eyebrow">HASTA PANELİ</span><h1>Randevularım</h1><p>Randevularınızı, durumlarını ve yaklaşan ziyaretlerinizi tek ekrandan takip edin.</p></div><div class="dashboard-actions"><a class="primary-button" href="book.php">+ Yeni Randevu</a><a class="secondary" href="ulogin.php">Ana Sayfa</a></div></div>
<?php if ($message !== ''): ?><div class="panel-message <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="dashboard-stats"><div class="dashboard-stat"><span>Toplam Randevu</span><strong><?php echo $totalCount; ?></strong></div><div class="dashboard-stat"><span>Aktif Randevu</span><strong><?php echo $activeCount; ?></strong></div><div class="dashboard-stat"><span>Onaylanan</span><strong><?php echo $approvedCount; ?></strong></div></div>
<?php if ($nextAppointment !== null): ?><div class="next-appointment"><span class="next-label">YAKLAŞAN RANDEVU</span><div class="next-grid"><div><span>Doktor</span><strong>Dr. <?php echo htmlspecialchars($nextAppointment['doctor_name'] ?? 'Doktor'); ?></strong></div><div><span>Tarih</span><strong><?php echo date('d.m.Y', strtotime($nextAppointment['DOV'])); ?></strong></div><div><span>Saat</span><strong><?php echo htmlspecialchars(substr($nextAppointment['appointment_time'] ?: '00:00', 0, 5)); ?></strong></div></div></div><?php endif; ?>
<div class="appointment-list">
<?php if ($totalCount === 0): ?><div class="empty-appointments"><strong>Henüz randevunuz bulunmuyor.</strong><span>Size uygun doktor ve saati seçerek ilk randevunuzu oluşturabilirsiniz.</span><br><a href="book.php">Randevu Al</a></div><?php else: ?>
<?php foreach ($appointments as $row): $statusClass=getStatusClass($row['Status']); $statusText=getStatusText($row['Status']); ?>
<article class="appointment-item"><div><strong><?php echo htmlspecialchars($row['Fname']); ?></strong><span><?php echo htmlspecialchars($row['clinic_name'] ?? 'Klinik'); ?><?php if (!empty($row['town'])): ?> · <?php echo htmlspecialchars($row['town']); ?><?php endif; ?></span></div><div><strong>Dr. <?php echo htmlspecialchars($row['doctor_name'] ?? 'Doktor'); ?></strong><span><?php echo date('d.m.Y', strtotime($row['DOV'])); ?><?php if (!empty($row['appointment_time'])): ?> · <?php echo htmlspecialchars(substr($row['appointment_time'],0,5)); ?><?php endif; ?></span></div><div class="patient-status-card"><span class="appointment-status-label">Randevu Durumu</span><span class="status-pill <?php echo $statusClass; ?>"><?php echo $statusClass==='status-approved'?'●':($statusClass==='status-completed'?'✓':($statusClass==='status-cancelled'?'×':'●')); ?> <?php echo htmlspecialchars($statusText); ?></span><small>Oluşturulma: <?php echo htmlspecialchars($row['Timestamp']); ?></small><?php if (canCancelAppointment($row)): ?><div class="appointment-actions"><form method="post" onsubmit="return confirm('Bu randevuyu iptal etmek istediğinizden emin misiniz?');"><input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>"><button type="submit" name="cancel_appointment" class="cancel-button">Randevuyu İptal Et</button></form></div><?php endif; ?></div></article>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section></main>
</body></html>
<?php $stmt->close(); ?>