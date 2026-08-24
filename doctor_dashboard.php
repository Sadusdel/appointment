<?php
session_start();
require_once 'dbconfig.php';

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);

if ($doctorId <= 0) {
    header('Location: Admin/dlogin.php');
    exit;
}

/* Doktor bilgisi */
$stmtDoctor = $conn->prepare("
    SELECT did, name, username
    FROM doctor
    WHERE did = ?
    LIMIT 1
");
$stmtDoctor->bind_param('i', $doctorId);
$stmtDoctor->execute();
$doctor = $stmtDoctor->get_result()->fetch_assoc();
$stmtDoctor->close();

if (!$doctor) {
    session_destroy();
    die('Doktor bulunamadı.');
}

/* Tarih */
$selectedDate = $_GET['date'] ?? date('Y-m-d');

$dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);

if (!$dateObj || $dateObj->format('Y-m-d') !== $selectedDate) {
    $selectedDate = date('Y-m-d');
    $dateObj = new DateTime($selectedDate);
}

/* Randevular */
$stmt = $conn->prepare("
    SELECT
        b.appointment_id,
        b.Fname,
        b.Gender,
        b.appointment_time,
        b.DOV,
        b.Status,
        b.Timestamp,
        c.name AS clinic_name,
        c.town
    FROM book b
    LEFT JOIN clinic c ON c.CID = b.CID
    WHERE b.DID = ?
      AND b.DOV = ?
    ORDER BY b.appointment_time ASC, b.Timestamp ASC
");

$stmt->bind_param(
    'is',
    $doctorId,
    $selectedDate
);

$stmt->execute();
$appointments = $stmt->get_result();

/* Haftalık randevu sayıları */
$weekStart = new DateTime($selectedDate);
$weekStart->modify('monday this week');

$weekEnd = clone $weekStart;
$weekEnd->modify('+6 days');

$weekStartSql = $weekStart->format('Y-m-d');
$weekEndSql = $weekEnd->format('Y-m-d');

$stmtWeek = $conn->prepare("
    SELECT DOV, COUNT(*) AS total
    FROM book
    WHERE DID = ?
      AND DOV BETWEEN ? AND ?
    GROUP BY DOV
");

$stmtWeek->bind_param(
    'iss',
    $doctorId,
    $weekStartSql,
    $weekEndSql
);

$stmtWeek->execute();

$weekCounts = [];

$weekResult = $stmtWeek->get_result();

while ($row = $weekResult->fetch_assoc()) {
    $weekCounts[$row['DOV']] = (int)$row['total'];
}

$stmtWeek->close();

/* Durum yardımcıları */
function normalizeStatus($status)
{
    $status = trim((string)$status);

    if ($status === '' || $status === 'Booked') {
        return 'Bekliyor';
    }

    return $status;
}

function statusClass($status)
{
    switch (normalizeStatus($status)) {

        case 'Onaylandı':
            return 'approved';

        case 'Tamamlandı':
            return 'completed';

        case 'İptal Edildi':
            return 'cancelled';

        default:
            return 'pending';
    }
}

function statusLabel($status)
{
    return normalizeStatus($status);
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

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f7fb;
    color: #172033;
}

/* HEADER */

.topbar {
    height: 70px;
    background: #ffffff;
    border-bottom: 1px solid #e5e9f0;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 35px;
}

.logo {
    font-size: 21px;
    font-weight: 800;
    color: #1769e0;
}

.logo span {
    color: #172033;
}

.doctor-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.doctor-avatar {
    width: 42px;
    height: 42px;

    border-radius: 50%;

    background: #1769e0;
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-weight: bold;
    font-size: 17px;
}

.doctor-name {
    font-weight: 700;
}

.doctor-role {
    font-size: 12px;
    color: #7a8495;
    margin-top: 3px;
}

/* PAGE */

.container {
    width: min(1250px, calc(100% - 40px));
    margin: 30px auto;
}

/* WELCOME */

.welcome {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 25px;
}

.welcome h1 {
    margin: 0;
    font-size: 28px;
}

.welcome p {
    margin: 7px 0 0;
    color: #748094;
}

/* DATE */

.date-box {
    background: white;
    border-radius: 14px;
    padding: 20px;

    border: 1px solid #e5e9f0;

    margin-bottom: 20px;
}

.date-form {
    display: flex;
    align-items: end;
    gap: 12px;
}

.field {
    flex: 1;
}

.field label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    margin-bottom: 7px;
}

.field input {
    width: 100%;
    height: 43px;

    border: 1px solid #d9dfe8;
    border-radius: 8px;

    padding: 0 12px;
    font-size: 14px;
}

.primary-button {
    height: 43px;

    border: none;
    border-radius: 8px;

    padding: 0 20px;

    background: #1769e0;
    color: white;

    font-weight: 700;
    cursor: pointer;
}

.primary-button:hover {
    background: #0e55bd;
}

/* WEEK */

.week {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;

    margin-bottom: 25px;
}

.day {
    text-decoration: none;

    background: white;
    border: 1px solid #e2e7ef;

    border-radius: 12px;

    padding: 14px 8px;

    text-align: center;

    color: #4f5c70;

    transition: .15s;
}

.day:hover {
    border-color: #1769e0;
}

.day.active {
    background: #1769e0;
    color: white;
    border-color: #1769e0;
}

.day-name {
    font-size: 12px;
    font-weight: 700;
}

.day-date {
    font-size: 18px;
    font-weight: 800;
    margin-top: 5px;
}

.day-count {
    font-size: 11px;
    margin-top: 5px;
    opacity: .8;
}

/* APPOINTMENT HEADER */

.appointment-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    margin-bottom: 14px;
}

.appointment-header h2 {
    margin: 0;
    font-size: 20px;
}

.count {
    background: #eaf2ff;
    color: #1769e0;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 13px;
    font-weight: 700;
}

/* APPOINTMENT */

.appointments {
    display: grid;
    gap: 12px;
}

.appointment {
    background: white;

    border: 1px solid #e4e9f0;

    border-radius: 14px;

    padding: 18px 20px;

    display: grid;

    grid-template-columns: 90px 1fr auto;

    gap: 20px;

    align-items: center;
}

.appointment:hover {
    box-shadow: 0 4px 18px rgba(20,40,80,.07);
}

/* TIME */

.time {
    font-size: 22px;
    font-weight: 800;
    color: #1769e0;
}

/* PATIENT */

.patient-name {
    font-size: 16px;
    font-weight: 800;
}

.patient-details {
    margin-top: 6px;

    font-size: 13px;

    color: #7a8495;
}

/* STATUS */

.status-area {
    display: flex;
    align-items: center;
    gap: 10px;

    justify-content: flex-end;

    flex-wrap: wrap;
}

.status {
    padding: 7px 11px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}

.status.pending {
    background: #fff3cd;
    color: #856404;
}

.status.approved {
    background: #d4edda;
    color: #155724;
}

.status.completed {
    background: #dbeafe;
    color: #1e40af;
}

.status.cancelled {
    background: #f8d7da;
    color: #721c24;
}

/* BUTTONS */

.actions {
    display: flex;
    gap: 6px;
}

.action {
    border: none;

    border-radius: 7px;

    padding: 8px 11px;

    font-size: 12px;

    font-weight: 700;

    cursor: pointer;
}

.action.approve {
    background: #e7f6ec;
    color: #14733b;
}

.action.complete {
    background: #e7f0ff;
    color: #145ac2;
}

.action.cancel {
    background: #fff0f0;
    color: #b42332;
}

.action:hover {
    filter: brightness(.96);
}

.action:disabled {
    opacity: .5;
    cursor: wait;
}

/* EMPTY */

.empty {
    background: white;

    border: 1px dashed #ccd4e0;

    border-radius: 14px;

    padding: 55px 20px;

    text-align: center;

    color: #7a8495;
}

.empty strong {
    display: block;

    color: #374151;

    font-size: 17px;

    margin-bottom: 7px;
}

/* LOGOUT */

.logout {
    text-decoration: none;

    font-size: 13px;

    color: #687386;
}

.logout:hover {
    color: #1769e0;
}

/* MOBILE */

@media(max-width: 850px) {

    .topbar {
        padding: 0 18px;
    }

    .container {
        width: calc(100% - 24px);
    }

    .welcome {
        align-items: flex-start;
    }

    .week {
        grid-template-columns: repeat(4, 1fr);
    }

    .appointment {
        grid-template-columns: 70px 1fr;
    }

    .status-area {
        grid-column: 1 / -1;
        justify-content: flex-start;
    }

}

@media(max-width: 550px) {

    .topbar {
        height: auto;
        padding: 15px;
    }

    .doctor-info {
        display: none;
    }

    .welcome h1 {
        font-size: 23px;
    }

    .date-form {
        flex-direction: column;
        align-items: stretch;
    }

    .week {
        grid-template-columns: repeat(2, 1fr);
    }

    .appointment {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .status-area {
        display: block;
    }

    .actions {
        margin-top: 8px;
        flex-wrap: wrap;
    }

}

</style>

</head>

<body>

<header class="topbar">

<div class="logo">
    Appointment <span>· Doktor</span>
</div>

<div class="doctor-info">

<div class="doctor-avatar">
<?php echo strtoupper(substr($doctor['name'], 0, 1)); ?>
</div>

<div>
<div class="doctor-name">
<?php echo htmlspecialchars($doctor['name']); ?>
</div>

<div class="doctor-role">
Doktor Paneli
</div>
</div>

</div>

</header>


<main class="container">


<section class="welcome">

<div>

<h1>Merhaba, Dr. <?php echo htmlspecialchars($doctor['name']); ?></h1>

<p>
<?php echo $dateObj->format('d.m.Y'); ?> tarihli randevularınız
</p>

</div>

<a class="logout" href="Admin/dlogin.php">
Doktor girişine dön
</a>

</section>


<section class="date-box">

<form class="date-form" method="get">

<div class="field">

<label for="date">
Randevu tarihi
</label>

<input
    id="date"
    type="date"
    name="date"
    value="<?php echo htmlspecialchars($selectedDate); ?>"
>

</div>

<button class="primary-button" type="submit">
Takvimi Göster
</button>

</form>

</section>


<section class="week">

<?php

$days = [
    'Mon' => 'Pzt',
    'Tue' => 'Sal',
    'Wed' => 'Çar',
    'Thu' => 'Per',
    'Fri' => 'Cum',
    'Sat' => 'Cmt',
    'Sun' => 'Paz'
];

for ($i = 0; $i < 7; $i++):

    $day = clone $weekStart;
    $day->modify("+$i day");

    $daySql = $day->format('Y-m-d');

?>

<a
    href="doctor_dashboard.php?date=<?php echo $daySql; ?>"
    class="day <?php echo $daySql === $selectedDate ? 'active' : ''; ?>"
>

<div class="day-name">
<?php echo $days[$day->format('D')]; ?>
</div>

<div class="day-date">
<?php echo $day->format('d.m'); ?>
</div>

<div class="day-count">
<?php echo $weekCounts[$daySql] ?? 0; ?> randevu
</div>

</a>

<?php endfor; ?>

</section>


<div class="appointment-header">

<h2>
Randevular
</h2>

<span class="count">
<?php echo $appointmentCount; ?> randevu
</span>

</div>


<section class="appointments">

<?php if ($appointmentCount === 0): ?>

<div class="empty">

<strong>
Bugün için randevu bulunmuyor
</strong>

Seçtiğiniz tarihte kayıtlı herhangi bir randevu bulunmamaktadır.

</div>

<?php else: ?>


<?php while ($row = $appointments->fetch_assoc()): ?>

<?php

$status = normalizeStatus($row['Status']);

?>

<article
    class="appointment"
    data-id="<?php echo (int)$row['appointment_id']; ?>"
>


<div class="time">

<?php

echo !empty($row['appointment_time'])
    ? substr($row['appointment_time'], 0, 5)
    : '--:--';

?>

</div>


<div>

<div class="patient-name">

<?php echo htmlspecialchars($row['Fname']); ?>

</div>

<div class="patient-details">

<?php

$details = [];

if (!empty($row['Gender'])) {
    $details[] = $row['Gender'];
}

if (!empty($row['clinic_name'])) {
    $clinic = $row['clinic_name'];

    if (!empty($row['town'])) {
        $clinic .= ' · ' . $row['town'];
    }

    $details[] = $clinic;
}

echo htmlspecialchars(implode(' · ', $details));

?>

</div>

</div>


<div class="status-area">

<span class="status <?php echo statusClass($status); ?>">

<?php echo htmlspecialchars(statusLabel($status)); ?>

</span>


<div class="actions">


<?php if (
    $status !== 'Onaylandı' &&
    $status !== 'Tamamlandı' &&
    $status !== 'İptal Edildi'
): ?>

<button
    type="button"
    class="action approve"
    data-status="Onaylandı"
>
Onayla
</button>

<?php endif; ?>


<?php if ($status === 'Onaylandı'): ?>

<button
    type="button"
    class="action complete"
    data-status="Tamamlandı"
>
Tamamlandı
</button>

<?php endif; ?>


<?php if (
    $status !== 'Tamamlandı' &&
    $status !== 'İptal Edildi'
): ?>

<button
    type="button"
    class="action cancel"
    data-status="İptal Edildi"
>
İptal Et
</button>

<?php endif; ?>


</div>

</div>

</article>

<?php endwhile; ?>


<?php endif; ?>

</section>

</main>


<script>

document.querySelectorAll('.appointment').forEach(function (appointment) {

    const buttons =
        appointment.querySelectorAll('.action');

    buttons.forEach(function (button) {

        button.addEventListener('click', async function () {

            const status = this.dataset.status;

            const messages = {
                'Onaylandı':
                    'Bu randevuyu onaylamak istediğinize emin misiniz?',

                'Tamamlandı':
                    'Bu randevuyu tamamlandı olarak işaretlemek istediğinize emin misiniz?',

                'İptal Edildi':
                    'Bu randevuyu iptal etmek istediğinize emin misiniz?'
            };

            if (!confirm(messages[status])) {
                return;
            }

            buttons.forEach(function (btn) {
                btn.disabled = true;
            });

            const formData = new FormData();

            formData.append(
                'appointment_id',
                appointment.dataset.id
            );

            formData.append(
                'status',
                status
            );

            try {

                const response = await fetch(
                    'doctor_update_status.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                );

                const data = await response.json();

                if (!data.success) {
                    throw new Error(
                        data.message || 'İşlem gerçekleştirilemedi.'
                    );
                }

                /*
                 * Durum değiştikten sonra sayfayı yeniliyoruz.
                 * Böylece hangi butonların görünmesi gerektiği
                 * PHP tarafından tekrar hesaplanıyor.
                 */

                location.reload();

            } catch (error) {

                alert(error.message);

                buttons.forEach(function (btn) {
                    btn.disabled = false;
                });

            }

        });

    });

});

</script>

</body>

</html>

<?php

$stmt->close();
$conn->close();

?>