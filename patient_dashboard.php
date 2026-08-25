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
.patient-dashboard .dashboard-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;margin-bottom:24px}.dashboard-hero-copy{min-width:0}.dashboard-hero h1{margin-bottom:8px}.dashboard-hero p{margin:0}.dashboard-actions{display:flex;gap:10px;flex-wrap:wrap}.dashboard-actions a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:11px;font-weight:800}.dashboard-actions .secondary{background:#eef4fc;color:#185abc;border:1px solid #d7e4f5}.dashboard-actions .secondary:hover{background:#e2edfb}.dashboard-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}.dashboard-stat{padding:17px 18px;border:1px solid #e3e8f0;border-radius:14px;background:#fbfcfe}.dashboard-stat span{display:block;color:#778196;font-size:12px;font-weight:700}.dashboard-stat strong{display:block;margin-top:5px;color:#172033;font-size:24px}.next-appointment{margin-bottom:24px;padding:20px;border:1px solid #cfe0f7;border-radius:16px;background:linear-gradient(135deg,#f3f8ff,#fff)}.next-appointment .next-label{color:#1769e0;font-size:12px;font-weight:850;letter-spacing:.07em}.next-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:18px;margin-top:10px}.next-grid span,.appointment-status-label{display:block;color:#778196;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.04em}.next-grid strong{display:block;margin-top:4px;color:#172033}.appointment-filter{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 14px;padding:5px;border:1px solid #e3e8f0;border-radius:13px;background:#f7f9fc}.appointment-filter button{width:auto;min-width:92px;padding:9px 12px;border-radius:9px;background:transparent;color:#687386;font-size:13px;box-shadow:none}.appointment-filter button:hover{background:#eaf2fd;color:#185abc;box-shadow:none;transform:none}.appointment-filter button.active{background:#1769e0;color:#fff}.appointment-filter-count{margin-left:auto;padding-right:8px;color:#7a8495;font-size:12px;font-weight:700}.appointment-list{display:grid;gap:12px}.appointment-item{display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;align-items:start;padding:20px;border:1px solid #e3e8f0;border-radius:15px;background:#fff;transition:transform .15s,box-shadow .2s,border-color .2s}.appointment-item:hover{border-color:#cddbf0;box-shadow:0 8px 24px rgba(25,45,80,.07);transform:translateY(-1px)}.appointment-item>div{min-width:0}.appointment-item strong{display:block;color:#263148}.appointment-item>div>span{display:block;margin-top:5px;color:#687386;font-size:13px;line-height:1.5}.patient-status-card{margin-top:0}.status-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 12px;margin-top:3px;border-radius:999px;font-size:13px;font-weight:700}.status-approved{background:#e8f7ee;color:#137333}.status-completed{background:#e9f1ff;color:#185abc}.status-cancelled{background:#fdecec;color:#b3261e}.status-pending{background:#fff5d9;color:#8a5a00}.appointment-actions{margin-top:12px;display:flex;justify-content:flex-end}.cancel-button{border:0;border-radius:9px;padding:9px 14px;background:#fff0f0;color:#a51d2d;font-weight:700;cursor:pointer}.cancel-button:hover{background:#ffe0e0}.panel-message{padding:13px 16px;border-radius:10px;margin-bottom:18px;font-weight:600}.panel-message.success{background:#eaf8f0;color:#146c3a}.panel-message.error{background:#fff0f0;color:#a51d2d}.empty-appointments{text-align:center;padding:36px 20px;border:1px dashed #d4dae4;border-radius:15px;background:#fbfcfe;color:#687386}.empty-appointments strong{display:block;color:#263148;margin-bottom:6px}.empty-appointments a{display:inline-flex;margin-top:16px;padding:10px 15px;border-radius:10px;background:#1769e0;color:#fff;font-weight:800}.appointment-hidden{display:none!important}
.detail-button{width:auto!important;margin-top:10px;padding:9px 13px!important;border:1px solid #d7e4f5!important;border-radius:9px!important;background:#eef4fc!important;color:#185abc!important;font-size:13px!important;font-weight:800!important;box-shadow:none!important}.detail-button:hover{background:#e2edfb!important;transform:none!important;box-shadow:none!important}.appointment-detail-overlay{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(16,28,48,.48);backdrop-filter:blur(3px)}.appointment-detail-overlay.open{display:flex}.appointment-detail-modal{width:min(520px,100%);max-height:min(720px,calc(100vh - 40px));overflow:auto;border-radius:20px;background:#fff;box-shadow:0 24px 70px rgba(16,28,48,.25);padding:26px}.detail-header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px}.detail-header h2{margin:0 0 5px;color:#172033;font-size:22px}.detail-header p{margin:0;color:#778196;font-size:13px}.detail-close{width:36px!important;height:36px!important;min-width:36px!important;padding:0!important;border-radius:50%!important;background:#f2f5f9!important;color:#566174!important;font-size:22px!important;line-height:1!important;box-shadow:none!important}.detail-close:hover{background:#e6ebf2!important;color:#172033!important;transform:none!important;box-shadow:none!important}.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.detail-field{padding:14px;border:1px solid #e4e8ef;border-radius:12px;background:#fbfcfe}.detail-field.full{grid-column:1/-1}.detail-field span{display:block;margin-bottom:5px;color:#7a8495;font-size:11px;font-weight:750;text-transform:uppercase;letter-spacing:.04em}.detail-field strong{display:block;color:#263148;font-size:14px;line-height:1.45}.detail-footer{display:flex;justify-content:flex-end;margin-top:18px}.detail-footer button{width:auto;padding:11px 18px;background:#1769e0}.detail-status{display:inline-flex!important;width:max-content!important;padding:6px 10px;border-radius:999px;background:#eaf2fd;color:#185abc!important;font-size:12px!important}@media(max-width:700px){.patient-dashboard .dashboard-hero{display:block}.dashboard-actions{margin-top:16px}.dashboard-actions a{flex:1}.dashboard-stats{grid-template-columns:1fr 1fr}.next-grid{grid-template-columns:1fr 1fr}.appointment-filter{overflow-x:auto;justify-content:flex-start}.appointment-filter button{flex:0 0 auto}.appointment-filter-count{display:none}.appointment-item{grid-template-columns:1fr;gap:14px}.appointment-actions{justify-content:stretch}.appointment-actions form,.cancel-button{width:100%}.appointment-detail-modal{padding:20px;border-radius:16px}.detail-grid{grid-template-columns:1fr}.detail-field.full{grid-column:auto}}@media(max-width:390px){.dashboard-stats,.next-grid{grid-template-columns:1fr}}
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
<div class="appointment-filter" aria-label="Randevu filtreleri"><button type="button" class="active" data-filter="all">Tümü</button><button type="button" data-filter="active">Aktif</button><button type="button" data-filter="approved">Onaylanan</button><button type="button" data-filter="completed">Tamamlanan</button><button type="button" data-filter="cancelled">İptal</button><span class="appointment-filter-count" id="visible-count"><?php echo $totalCount; ?> randevu</span></div>
<div class="appointment-list" id="appointment-list">
<?php if ($totalCount === 0): ?><div class="empty-appointments"><strong>Henüz randevunuz bulunmuyor.</strong><span>Size uygun doktor ve saati seçerek ilk randevunuzu oluşturabilirsiniz.</span><br><a href="book.php">Randevu Al</a></div><?php else: ?>
<?php foreach ($appointments as $row): $statusClass=getStatusClass($row['Status']); $statusText=getStatusText($row['Status']); $filterClass=$statusClass==='status-approved'?'approved':($statusClass==='status-completed'?'completed':($statusClass==='status-cancelled'?'cancelled':'active')); ?>
<article class="appointment-item" data-status="<?php echo $filterClass; ?>"><div><strong><?php echo htmlspecialchars($row['Fname']); ?></strong><span><?php echo htmlspecialchars($row['clinic_name'] ?? 'Klinik'); ?><?php if (!empty($row['town'])): ?> · <?php echo htmlspecialchars($row['town']); ?><?php endif; ?></span></div><div><strong>Dr. <?php echo htmlspecialchars($row['doctor_name'] ?? 'Doktor'); ?></strong><span><?php echo date('d.m.Y', strtotime($row['DOV'])); ?><?php if (!empty($row['appointment_time'])): ?> · <?php echo htmlspecialchars(substr($row['appointment_time'],0,5)); ?><?php endif; ?></span></div><div class="patient-status-card"><span class="appointment-status-label">Randevu Durumu</span><span class="status-pill <?php echo $statusClass; ?>"><?php echo $statusClass==='status-approved'?'●':($statusClass==='status-completed'?'✓':($statusClass==='status-cancelled'?'×':'●')); ?> <?php echo htmlspecialchars($statusText); ?></span><small>Oluşturulma: <?php echo htmlspecialchars($row['Timestamp']); ?></small><button type="button" class="detail-button" data-appointment-id="<?php echo (int)$row['appointment_id']; ?>" data-patient="<?php echo htmlspecialchars($row['Fname'], ENT_QUOTES, 'UTF-8'); ?>" data-doctor="<?php echo htmlspecialchars($row['doctor_name'] ?? 'Doktor', ENT_QUOTES, 'UTF-8'); ?>" data-clinic="<?php echo htmlspecialchars($row['clinic_name'] ?? 'Klinik', ENT_QUOTES, 'UTF-8'); ?>" data-town="<?php echo htmlspecialchars($row['town'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" data-date="<?php echo htmlspecialchars(date('d.m.Y', strtotime($row['DOV'])), ENT_QUOTES, 'UTF-8'); ?>" data-time="<?php echo htmlspecialchars(substr($row['appointment_time'] ?: '00:00',0,5), ENT_QUOTES, 'UTF-8'); ?>" data-status="<?php echo htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8'); ?>" data-created="<?php echo htmlspecialchars($row['Timestamp'], ENT_QUOTES, 'UTF-8'); ?>">Detayları Gör</button><?php if (canCancelAppointment($row)): ?><div class="appointment-actions"><form method="post" onsubmit="return confirm('Bu randevuyu iptal etmek istediğinizden emin misiniz?');"><input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>"><button type="submit" name="cancel_appointment" class="cancel-button">Randevuyu İptal Et</button></form></div><?php endif; ?></div></article>
<?php endforeach; ?>
<?php endif; ?>
</div>
</section></main>
<div class="appointment-detail-overlay" id="appointment-detail-overlay" role="dialog" aria-modal="true" aria-labelledby="detail-title"><div class="appointment-detail-modal"><div class="detail-header"><div><h2 id="detail-title">Randevu Detayları</h2><p>Randevunuza ait ayrıntılı bilgiler</p></div><button type="button" class="detail-close" id="detail-close" aria-label="Kapat">×</button></div><div class="detail-grid"><div class="detail-field"><span>Hasta</span><strong id="detail-patient">-</strong></div><div class="detail-field"><span>Durum</span><strong id="detail-status" class="detail-status">-</strong></div><div class="detail-field"><span>Doktor</span><strong id="detail-doctor">-</strong></div><div class="detail-field"><span>Klinik</span><strong id="detail-clinic">-</strong></div><div class="detail-field"><span>İlçe</span><strong id="detail-town">-</strong></div><div class="detail-field"><span>Tarih</span><strong id="detail-date">-</strong></div><div class="detail-field"><span>Saat</span><strong id="detail-time">-</strong></div><div class="detail-field full"><span>Randevu oluşturulma zamanı</span><strong id="detail-created">-</strong></div></div><div class="detail-footer"><button type="button" id="detail-close-footer">Kapat</button></div></div></div>
<script>
(function(){
    const buttons=document.querySelectorAll('.appointment-filter button');
    const cards=document.querySelectorAll('#appointment-list .appointment-item');
    const count=document.getElementById('visible-count');
    buttons.forEach(function(button){
        button.addEventListener('click',function(){
            const filter=this.dataset.filter;
            let visible=0;
            buttons.forEach(function(item){item.classList.remove('active');});
            this.classList.add('active');
            cards.forEach(function(card){
                const show=filter==='all'||card.dataset.status===filter;
                card.classList.toggle('appointment-hidden',!show);
                if(show) visible++;
            });
            if(count) count.textContent=visible+' randevu';
        });
    });

    const overlay=document.getElementById('appointment-detail-overlay');
    const closeButton=document.getElementById('detail-close');
    const closeFooter=document.getElementById('detail-close-footer');
    const fields={patient:document.getElementById('detail-patient'),status:document.getElementById('detail-status'),doctor:document.getElementById('detail-doctor'),clinic:document.getElementById('detail-clinic'),town:document.getElementById('detail-town'),date:document.getElementById('detail-date'),time:document.getElementById('detail-time'),created:document.getElementById('detail-created')};

    function openDetail(button){
        Object.keys(fields).forEach(function(key){ fields[key].textContent=button.dataset[key] || (key==='town' ? 'Belirtilmemiş' : '-'); });
        overlay.classList.add('open');
        document.body.style.overflow='hidden';
        closeButton.focus();
    }
    function closeDetail(){
        overlay.classList.remove('open');
        document.body.style.overflow='';
    }
    document.querySelectorAll('.detail-button').forEach(function(button){button.addEventListener('click',function(){openDetail(this);});});
    closeButton.addEventListener('click',closeDetail);
    closeFooter.addEventListener('click',closeDetail);
    overlay.addEventListener('click',function(event){if(event.target===overlay) closeDetail();});
    document.addEventListener('keydown',function(event){if(event.key==='Escape'&&overlay.classList.contains('open')) closeDetail();});
})();
</script>
</body></html>
<?php $stmt->close(); ?>
