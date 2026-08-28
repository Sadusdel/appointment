<?php
session_start();
require_once 'dbconfig.php';

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

if ($doctorId <= 0) {
    header('Location: Admin/dlogin.php');
    exit;
}

$stmtDoctor = $conn->prepare("SELECT did, name, username FROM doctor WHERE did = ? LIMIT 1");
$stmtDoctor->bind_param('i', $doctorId);
$stmtDoctor->execute();
$doctor = $stmtDoctor->get_result()->fetch_assoc();
$stmtDoctor->close();

if (!$doctor) {
    session_destroy();
    die('Doktor bulunamadı.');
}

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);

if (!$dateObj || $dateObj->format('Y-m-d') !== $selectedDate) {
    $selectedDate = date('Y-m-d');
    $dateObj = new DateTime($selectedDate);
}

$stmt = $conn->prepare("SELECT b.appointment_id, b.Fname, b.Gender, b.appointment_time, b.DOV, b.Status, b.Timestamp, c.name AS clinic_name, c.town FROM book b LEFT JOIN clinic c ON c.CID = b.CID WHERE b.DID = ? AND b.DOV = ? ORDER BY b.appointment_time ASC, b.Timestamp ASC");
$stmt->bind_param('is', $doctorId, $selectedDate);
$stmt->execute();
$appointments = $stmt->get_result();

$weekStart = new DateTime($selectedDate);
$weekStart->modify('monday this week');
$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');
$weekStartSql = $weekStart->format('Y-m-d');
$weekEndSql = $weekEnd->format('Y-m-d');

$stmtWeek = $conn->prepare("SELECT DOV, COUNT(*) AS total FROM book WHERE DID = ? AND DOV BETWEEN ? AND ? GROUP BY DOV");
$stmtWeek->bind_param('iss', $doctorId, $weekStartSql, $weekEndSql);
$stmtWeek->execute();
$weekResult = $stmtWeek->get_result();
$weekCounts = [];
while ($row = $weekResult->fetch_assoc()) {
    $weekCounts[$row['DOV']] = (int)$row['total'];
}
$stmtWeek->close();

function normalizeStatus($status) {
    switch ($status) {
        case 'Onaylandı': return 'approved';
        case 'Tamamlandı': return 'completed';
        case 'İptal Edildi': return 'cancelled';
        default: return 'pending';
    }
}

function statusLabel($status) {
    $status = trim((string)$status);
    if ($status === '' || $status === 'Booked') return 'Bekliyor';
    return $status;
}

$appointmentCount = $appointments->num_rows;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doktor Paneli</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#f4f7fb;color:#172033}.topbar{height:70px;background:#fff;border-bottom:1px solid #e5e9f0;display:flex;align-items:center;justify-content:space-between;padding:0 35px}.logo{font-size:21px;font-weight:800;color:#1769e0}.logo span{color:#172033}.doctor-info{display:flex;align-items:center;gap:15px}.doctor-avatar{width:42px;height:42px;border-radius:50%;background:#1769e0;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:bold;font-size:17px}.doctor-name{font-weight:700}.doctor-role{font-size:12px;color:#7a8495;margin-top:3px}.container{width:min(1250px,calc(100% - 40px));margin:30px auto}.welcome{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px}.welcome h1{margin:0;font-size:28px}.welcome p{margin:7px 0 0;color:#748094}.logout{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;font-size:13px;font-weight:700;color:#b42332;background:#fff0f0;border:1px solid #ffd5d9;border-radius:9px;padding:10px 15px;transition:.15s}.logout:hover{background:#ffe2e5;color:#941f2c}.date-box{background:#fff;border-radius:14px;padding:20px;border:1px solid #e5e9f0;margin-bottom:20px}.date-form{display:flex;align-items:end;gap:12px}.field{flex:1}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:7px}.field input{width:100%;height:43px;border:1px solid #d9dfe8;border-radius:8px;padding:0 12px;font-size:14px}.primary-button{height:43px;border:none;border-radius:8px;padding:0 20px;background:#1769e0;color:#fff;font-weight:700;cursor:pointer}.primary-button:hover{background:#0e55bd}.week{display:grid;grid-template-columns:repeat(7,1fr);gap:10px;margin-bottom:25px}.day{text-decoration:none;background:#fff;border:1px solid #e2e7ef;border-radius:12px;padding:14px 8px;text-align:center;color:#4f5c70;transition:.15s}.day:hover{border-color:#1769e0;transform:translateY(-1px)}.day.active{background:#1769e0;color:#fff;border-color:#1769e0}.day-name{font-size:12px;font-weight:700}.day-date{font-size:18px;font-weight:800;margin-top:5px}.day-count{font-size:11px;margin-top:5px;opacity:.8}.appointment-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}.appointment-header h2{margin:0;font-size:20px}.count{background:#eaf2ff;color:#1769e0;padding:7px 12px;border-radius:20px;font-size:13px;font-weight:700}.appointments{display:grid;gap:12px}.appointment{background:#fff;border:1px solid #e4e9f0;border-radius:14px;padding:18px 20px;display:grid;grid-template-columns:90px minmax(0,1fr) auto;gap:20px;align-items:center}.appointment:hover{box-shadow:0 4px 18px rgba(20,40,80,.07)}.time{font-size:22px;font-weight:800;color:#1769e0}.patient-name{font-size:16px;font-weight:800}.patient-details{margin-top:6px;font-size:13px;color:#7a8495}.status-area{display:flex;align-items:center;gap:10px;justify-content:flex-end;flex-wrap:wrap}.status{padding:7px 11px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}.status.pending{background:#fff3cd;color:#856404}.status.approved{background:#d4edda;color:#155724}.status.completed{background:#dbeafe;color:#1e40af}.status.cancelled{background:#f8d7da;color:#721c24}.actions{display:flex;gap:6px;flex-wrap:wrap}.action{border:none;border-radius:7px;padding:8px 11px;font-size:12px;font-weight:700;cursor:pointer;transition:.15s}.action.approve{background:#e7f6ec;color:#14733b}.action.complete{background:#e7f0ff;color:#145ac2}.action.cancel{background:#fff0f0;color:#b42332}.action:hover{filter:brightness(.96);transform:translateY(-1px)}.action:disabled{opacity:.55;cursor:wait;transform:none}.action-message{width:100%;font-size:12px;color:#b42332;margin-top:2px}.empty{background:#fff;border:1px dashed #ccd4e0;border-radius:14px;padding:55px 20px;text-align:center;color:#7a8495}.empty strong{display:block;color:#374151;font-size:17px;margin-bottom:7px}.toast{position:fixed;right:20px;bottom:20px;z-index:50;max-width:min(420px,calc(100% - 40px));padding:13px 16px;border-radius:10px;background:#172033;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,.18);font-size:13px;opacity:0;transform:translateY(10px);pointer-events:none;transition:.2s}.toast.show{opacity:1;transform:translateY(0)}@media(max-width:950px){.topbar{padding:0 18px}.container{width:calc(100% - 24px)}.appointment{grid-template-columns:75px 1fr}.status-area{grid-column:1/-1;justify-content:flex-start;border-top:1px solid #edf0f4;padding-top:12px}}@media(max-width:650px){.topbar{height:auto;padding:15px}.doctor-info{display:none}.welcome{align-items:flex-start;gap:12px}.welcome h1{font-size:23px}.date-form{flex-direction:column;align-items:stretch}.week{grid-template-columns:repeat(2,1fr)}.appointment{grid-template-columns:1fr;gap:10px}.status-area{display:flex}.logout{padding:9px 12px}.time{font-size:20px}}
</style>
</head>
<body>
<header class="topbar"><div class="logo">Appointment <span>· Doktor</span></div><div class="doctor-info"><div class="doctor-avatar"><?php echo strtoupper(substr($doctor['name'],0,1)); ?></div><div><div class="doctor-name"><?php echo htmlspecialchars($doctor['name'],ENT_QUOTES,'UTF-8'); ?></div><div class="doctor-role">Doktor Paneli</div></div></div></header>
<main class="container">
<section class="welcome"><div><h1>Merhaba, Dr. <?php echo htmlspecialchars($doctor['name'],ENT_QUOTES,'UTF-8'); ?></h1><p><?php echo $dateObj->format('d.m.Y'); ?> tarihli randevularınız</p></div><a class="logout" href="doctor_logout.php">Çıkış Yap</a></section>
<section class="date-box"><form class="date-form" method="get"><div class="field"><label for="date">Randevu tarihi</label><input id="date" type="date" name="date" value="<?php echo htmlspecialchars($selectedDate,ENT_QUOTES,'UTF-8'); ?>"></div><button class="primary-button" type="submit">Takvimi Göster</button></form></section>
<section class="week">
<?php $days=['Mon'=>'Pzt','Tue'=>'Sal','Wed'=>'Çar','Thu'=>'Per','Fri'=>'Cum','Sat'=>'Cmt','Sun'=>'Paz']; for($i=0;$i<7;$i++): $day=clone $weekStart; $day->modify("+$i day"); $daySql=$day->format('Y-m-d'); ?>
<a href="doctor_dashboard.php?date=<?php echo $daySql; ?>" class="day <?php echo $daySql===$selectedDate?'active':''; ?>"><div class="day-name"><?php echo $days[$day->format('D')]; ?></div><div class="day-date"><?php echo $day->format('d.m'); ?></div><div class="day-count"><?php echo $weekCounts[$daySql]??0; ?> randevu</div></a>
<?php endfor; ?>
</section>
<section><div class="appointment-header"><h2>Randevular</h2><div class="count"><?php echo $appointmentCount; ?> randevu</div></div><div class="appointments">
<?php if($appointmentCount===0): ?><div class="empty"><strong>Randevu bulunmuyor</strong>Seçtiğiniz tarihte planlanmış bir randevu yok.</div>
<?php else: while($row=$appointments->fetch_assoc()): $displayStatus=statusLabel($row['Status']); $statusClass=normalizeStatus($displayStatus); ?>
<article class="appointment" data-appointment-id="<?php echo (int)$row['appointment_id']; ?>">
<div class="time"><?php echo htmlspecialchars(substr((string)$row['appointment_time'],0,5),ENT_QUOTES,'UTF-8'); ?></div>
<div><div class="patient-name"><?php echo htmlspecialchars($row['Fname'],ENT_QUOTES,'UTF-8'); ?></div><div class="patient-details"><?php echo htmlspecialchars($row['Gender'],ENT_QUOTES,'UTF-8'); ?> · <?php echo htmlspecialchars($row['clinic_name']??'Klinik',ENT_QUOTES,'UTF-8'); ?><?php if(!empty($row['town'])): ?> · <?php echo htmlspecialchars($row['town'],ENT_QUOTES,'UTF-8'); ?><?php endif; ?></div></div>
<div class="status-area"><span class="status <?php echo $statusClass; ?>" data-status><?php echo htmlspecialchars($displayStatus,ENT_QUOTES,'UTF-8'); ?></span><div class="actions" data-actions>
<?php if($displayStatus==='Bekliyor'): ?><button type="button" class="action approve" data-status-action="Onaylandı">Onayla</button><button type="button" class="action cancel" data-status-action="İptal Edildi">İptal Et</button>
<?php elseif($displayStatus==='Onaylandı'): ?><button type="button" class="action complete" data-status-action="Tamamlandı">Tamamlandı</button><button type="button" class="action cancel" data-status-action="İptal Edildi">İptal Et</button><?php endif; ?>
</div></div>
</article>
<?php endwhile; endif; ?>
</div></section>
</main>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script>
(function(){
 const toast=document.getElementById('toast');
 let toastTimer;
 function showToast(message){toast.textContent=message;toast.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toast.classList.remove('show'),3200);}
 function escapeHtml(value){const d=document.createElement('div');d.textContent=value;return d.innerHTML;}
 async function updateStatus(button){
   const card=button.closest('.appointment');
   const id=card.dataset.appointmentId;
   const status=button.dataset.statusAction;
   const original=button.textContent;
   if(!id||!status)return;
   if(status==='İptal Edildi' && !confirm('Bu randevuyu iptal etmek istediğinize emin misiniz?'))return;
   const buttons=card.querySelectorAll('[data-status-action]');
   buttons.forEach(b=>b.disabled=true);
   button.textContent='Kaydediliyor...';
   try{
     const body=new URLSearchParams({appointment_id:id,status:status});
     const response=await fetch('doctor_update_status.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},body:body.toString(),credentials:'same-origin'});
     const data=await response.json().catch(()=>({success:false,message:'Sunucudan geçersiz yanıt alındı.'}));
     if(!response.ok||!data.success)throw new Error(data.message||'Randevu durumu güncellenemedi.');
     card.querySelector('[data-status]').textContent=data.status;
     const statusEl=card.querySelector('[data-status]');
     statusEl.className='status '+({Onaylandı:'approved',Tamamlandı:'completed','İptal Edildi':'cancelled',Bekliyor:'pending'}[data.status]||'pending');
     const actions=card.querySelector('[data-actions]');
     if(data.status==='Onaylandı')actions.innerHTML='<button type="button" class="action complete" data-status-action="Tamamlandı">Tamamlandı</button><button type="button" class="action cancel" data-status-action="İptal Edildi">İptal Et</button>';
     else actions.innerHTML='';
     showToast('Randevu durumu güncellendi: '+data.status);
   }catch(error){button.textContent=original;buttons.forEach(b=>b.disabled=false);showToast(error.message||'Bir hata oluştu.');}
 }
 document.addEventListener('click',function(e){const button=e.target.closest('[data-status-action]');if(button)updateStatus(button);});
})();
</script>
</body>
</html>