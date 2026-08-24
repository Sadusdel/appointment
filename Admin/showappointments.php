<?php
session_start();
require_once 'dbconfig.php';

$statusFilter = trim($_GET['status'] ?? '');

$allowedStatuses = [
    'Bekliyor',
    'Onaylandı',
    'Tamamlandı',
    'İptal Edildi'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

$sql = "
    SELECT
        b.appointment_id,
        b.Fname,
        b.Username,
        b.DOV,
        b.appointment_time,
        b.Status,
        b.Timestamp,
        d.name AS doctor_name,
        c.name AS clinic_name,
        c.town
    FROM book b
    LEFT JOIN doctor d ON d.did = b.DID
    LEFT JOIN clinic c ON c.CID = b.CID
";

if ($statusFilter !== '') {
    $sql .= " WHERE b.Status = ?";
}

$sql .= " ORDER BY b.DOV DESC, b.appointment_time DESC";

$stmt = $conn->prepare($sql);

if ($statusFilter !== '') {
    $stmt->bind_param('s', $statusFilter);
}

$stmt->execute();
$result = $stmt->get_result();

function statusClass($status)
{
    switch (trim((string)$status)) {

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

function statusText($status)
{
    if ($status === 'Booked' || trim($status) === '') {
        return 'Bekliyor';
    }

    return $status;
}
?>

<!DOCTYPE html>
<html lang="tr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Randevular | Admin</title>

<link rel="stylesheet" href="adminmain.css">

<style>

body {
    margin: 0;
    padding: 30px;
    font-family: Arial, sans-serif;
    background: #f4f6f9;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.page-header h1 {
    margin: 0;
}

.back-button {
    text-decoration: none;
    padding: 10px 16px;
    background: #333;
    color: white;
    border-radius: 6px;
}

.filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.filter-button {
    text-decoration: none;
    padding: 9px 15px;
    border-radius: 7px;
    background: white;
    color: #333;
    border: 1px solid #ddd;
}

.filter-button.active {
    background: #333;
    color: white;
}

.appointment-table-wrapper {
    overflow-x: auto;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,.08);
}

.appointment-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1100px;
}

.appointment-table th {
    background: #222;
    color: white;
    padding: 14px;
    text-align: left;
}

.appointment-table td {
    padding: 13px;
    border-bottom: 1px solid #eee;
}

.appointment-table tr:hover {
    background: #f8f8f8;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
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

.actions {
    white-space: nowrap;
}

.action-btn {
    border: none;
    border-radius: 6px;
    padding: 7px 10px;
    margin: 2px;
    cursor: pointer;
    font-weight: bold;
}

.action-btn.approve {
    background: #28a745;
    color: white;
}

.action-btn.complete {
    background: #007bff;
    color: white;
}

.action-btn.cancel {
    background: #dc3545;
    color: white;
}

.action-btn:hover {
    opacity: 0.85;
}

.action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.empty {
    padding: 30px;
    text-align: center;
    color: #777;
}

</style>

</head>

<body>

<div class="page-header">

<h1>Randevu Yönetimi</h1>

<a href="mainpage.php" class="back-button">
Admin Paneli
</a>

</div>

<div class="filters">

<a href="showappointments.php"
class="filter-button <?php echo $statusFilter === '' ? 'active' : ''; ?>">
Tümü
</a>

<a href="showappointments.php?status=Bekliyor"
class="filter-button <?php echo $statusFilter === 'Bekliyor' ? 'active' : ''; ?>">
Bekliyor
</a>

<a href="showappointments.php?status=Onaylandı"
class="filter-button <?php echo $statusFilter === 'Onaylandı' ? 'active' : ''; ?>">
Onaylandı
</a>

<a href="showappointments.php?status=Tamamlandı"
class="filter-button <?php echo $statusFilter === 'Tamamlandı' ? 'active' : ''; ?>">
Tamamlandı
</a>

<a href="showappointments.php?status=İptal Edildi"
class="filter-button <?php echo $statusFilter === 'İptal Edildi' ? 'active' : ''; ?>">
İptal Edildi
</a>

</div>

<div class="appointment-table-wrapper">

<table class="appointment-table">

<thead>

<tr>
<th>ID</th>
<th>Tarih</th>
<th>Saat</th>
<th>Hasta</th>
<th>Kullanıcı</th>
<th>Doktor</th>
<th>Klinik</th>
<th>Durum</th>
<th>İşlemler</th>
<th>Oluşturulma</th>
</tr>

</thead>

<tbody>

<?php if ($result->num_rows === 0): ?>

<tr>
<td colspan="10" class="empty">
Bu kriterlere uygun randevu bulunamadı.
</td>
</tr>

<?php else: ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>

<td>
<?php echo (int)$row['appointment_id']; ?>
</td>

<td>
<?php
echo !empty($row['DOV'])
    ? date('d.m.Y', strtotime($row['DOV']))
    : '-';
?>
</td>

<td>
<?php
echo !empty($row['appointment_time'])
    ? substr($row['appointment_time'], 0, 5)
    : '-';
?>
</td>

<td>
<?php echo htmlspecialchars($row['Fname']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['Username']); ?>
</td>

<td>
<?php echo htmlspecialchars($row['doctor_name'] ?? 'Doktor'); ?>
</td>

<td>
<?php
echo htmlspecialchars($row['clinic_name'] ?? 'Klinik');

if (!empty($row['town'])) {
    echo ' · ' . htmlspecialchars($row['town']);
}
?>
</td>

<td>

<span class="status <?php echo statusClass($row['Status']); ?>">

<?php echo htmlspecialchars(statusText($row['Status'])); ?>

</span>

</td>

<td class="actions">

<?php if (
    $row['Status'] !== 'Onaylandı' &&
    $row['Status'] !== 'Tamamlandı' &&
    $row['Status'] !== 'İptal Edildi'
): ?>

<button
    type="button"
    class="action-btn approve"
    data-id="<?php echo (int)$row['appointment_id']; ?>"
    data-status="Onaylandı">
    Onayla
</button>

<?php endif; ?>


<?php if ($row['Status'] === 'Onaylandı'): ?>

<button
    type="button"
    class="action-btn complete"
    data-id="<?php echo (int)$row['appointment_id']; ?>"
    data-status="Tamamlandı">
    Tamamlandı
</button>

<?php endif; ?>


<?php if (
    $row['Status'] !== 'Tamamlandı' &&
    $row['Status'] !== 'İptal Edildi'
): ?>

<button
    type="button"
    class="action-btn cancel"
    data-id="<?php echo (int)$row['appointment_id']; ?>"
    data-status="İptal Edildi">
    İptal Et
</button>

<?php endif; ?>

</td>

<td>
<?php echo htmlspecialchars($row['Timestamp']); ?>
</td>

</tr>

<?php endwhile; ?>

<?php endif; ?>

</tbody>

</table>

</div>


<script>

document.querySelectorAll('.action-btn').forEach(function(button) {

    button.addEventListener('click', async function() {

        const appointmentId = this.dataset.id;
        const status = this.dataset.status;

        if (status === 'İptal Edildi') {

            if (!confirm(
                'Bu randevuyu iptal etmek istediğinize emin misiniz?'
            )) {
                return;
            }

        } else {

            if (!confirm(
                'Randevu durumunu "' + status +
                '" olarak değiştirmek istiyor musunuz?'
            )) {
                return;
            }

        }

        this.disabled = true;

        const formData = new FormData();

        formData.append('appointment_id', appointmentId);
        formData.append('status', status);

        try {

            const response = await fetch(
                'admin_update_appointment.php',
                {
                    method: 'POST',
                    body: formData
                }
            );

            const data = await response.json();

            if (!data.success) {
                throw new Error(
                    data.message || 'İşlem başarısız.'
                );
            }

            location.reload();

        } catch (error) {

            alert(error.message);

            this.disabled = false;

        }

    });

});

</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>