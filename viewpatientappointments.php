<?php
session_start();
if (!isset($_SESSION['user']) || trim((string)$_SESSION['user']) === '') {
    header('Location: cover.php');
    exit;
}
require_once 'dbconfig.php';

$username = trim((string)($_SESSION['username'] ?? $_SESSION['user']));
$stmt = $conn->prepare('SELECT b.DOV, b.Fname, b.Status, b.Timestamp, d.Name AS DoctorName, c.Name AS ClinicName, c.Town FROM book b LEFT JOIN doctor d ON d.DID = b.DID LEFT JOIN clinic c ON c.CID = b.CID WHERE b.username = ? ORDER BY b.DOV DESC, b.Timestamp DESC');
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
function statusClass($status): string {
    return match (trim((string)$status)) {
        'Onaylandı', 'Onayland─▒' => 'status-approved',
        'Tamamlandı', 'Tamamland─▒' => 'status-completed',
        'İptal Edildi', '─░ptal Edildi' => 'status-cancelled',
        default => 'status-pending',
    };
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Randevularım | Appointment</title>
<link rel="stylesheet" href="main.css">
<style>
.appointments-page{min-height:100vh;background:radial-gradient(circle at top right,#e9f2ff 0,transparent 34%),var(--page);}
.appointments-container{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:42px 0 72px}
.appointments-hero{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:24px}
.appointments-hero h1{margin:6px 0 8px}
.appointments-hero p{margin:0;max-width:650px}
.back-button{white-space:nowrap}
.appointments-card{background:#fff;border:1px solid var(--border);border-radius:22px;box-shadow:var(--shadow);overflow:hidden}
.card-top{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:22px 24px;border-bottom:1px solid var(--border);background:#fbfcfe}
.card-top strong{font-size:16px}
.card-top span{color:var(--muted);font-size:13px}
.appointment-table{width:100%;border-collapse:collapse;min-width:760px}
.appointment-table th{padding:15px 18px;background:#f7f9fc;color:#667085;border-bottom:1px solid var(--border);font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;text-align:left}
.appointment-table td{padding:17px 18px;border-bottom:1px solid var(--border);color:#344054;font-size:14px;vertical-align:middle}
.appointment-table tr:last-child td{border-bottom:0}
.appointment-table tbody tr{transition:background .18s}
.appointment-table tbody tr:hover{background:#f8fbff}
.date-main{display:block;color:var(--text);font-weight:800}
.date-time{display:block;margin-top:3px;color:var(--muted);font-size:12px}
.patient-name,.doctor-name{font-weight:750;color:#263148}
.clinic-name{font-weight:700;color:#344054}
.clinic-town{display:block;margin-top:3px;color:var(--muted);font-size:12px}
.empty-appointments{margin:24px}
@media(max-width:700px){
 .appointments-container{width:min(100% - 20px,1120px);padding:24px 0 42px}
 .appointments-hero{align-items:stretch;flex-direction:column;gap:16px}
 .back-button{width:100%}
 .appointments-card{border-radius:16px}
 .card-top{padding:18px 16px}
 .appointment-table th,.appointment-table td{padding:13px 14px}
}
</style>
</head>
<body class="appointments-page">
<header class="site-header">
  <div class="header-inner">
    <a href="ulogin.php" class="brand"><img src="images/cal.png" alt="Appointment"><span>Appointment</span></a>
    <nav><a href="ulogin.php">Hasta Paneli</a><a class="active" href="viewpatientappointments.php">Randevularım</a></nav>
  </div>
</header>
<main class="appointments-container">
  <section class="appointments-hero">
    <div>
      <span class="eyebrow">HASTA PANELİ</span>
      <h1>Randevularım</h1>
      <p>Geçmiş ve yaklaşan randevularınızı tek ekrandan takip edin.</p>
    </div>
    <a class="button secondary back-button" href="book.php">+ Yeni Randevu</a>
  </section>

  <section class="appointments-card">
    <div class="card-top">
      <strong>Randevu geçmişi</strong>
      <span><?php echo $result->num_rows; ?> kayıt</span>
    </div>
    <?php if ($result->num_rows === 0): ?>
      <div class="empty-appointments">
        <strong>Henüz randevunuz bulunmuyor.</strong>
        <span>Yeni bir randevu oluşturmak için aşağıdaki butonu kullanabilirsiniz.</span>
        <div style="margin-top:18px"><a class="button" href="book.php">Yeni Randevu Oluştur</a></div>
      </div>
    <?php else: ?>
      <div class="data-table-wrap" style="border:0;border-radius:0">
        <table class="appointment-table">
          <thead><tr><th>Tarih</th><th>Hasta</th><th>Klinik</th><th>Doktor</th><th>Durum</th><th>Oluşturulma</th></tr></thead>
          <tbody>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td><span class="date-main"><?php echo e($row['DOV']); ?></span></td>
              <td><span class="patient-name"><?php echo e($row['Fname']); ?></span></td>
              <td><span class="clinic-name"><?php echo e($row['ClinicName'] ?? '—'); ?></span><span class="clinic-town"><?php echo e($row['Town'] ?? ''); ?></span></td>
              <td><span class="doctor-name"><?php echo e($row['DoctorName'] ?? '—'); ?></span></td>
              <td><span class="status-pill <?php echo e(statusClass($row['Status'])); ?>"><?php echo e($row['Status']); ?></span></td>
              <td><span class="date-time"><?php echo e($row['Timestamp']); ?></span></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
<?php $stmt->close(); $conn->close(); ?>
