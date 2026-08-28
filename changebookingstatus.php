<?php
session_start();
require_once 'dbconfig.php';

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
if ($doctorId <= 0) {
    header('Location: Admin/dlogin.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit2'])) {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedToken)) {
        http_response_code(403);
        exit('Geçersiz güvenlik doğrulaması.');
    }

    $appointmentIds = $_POST['appointment_id'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $firstNames = $_POST['fname'] ?? [];
    $allowedStatuses = ['Bekliyor', 'Onaylandı', 'Tamamlandı', 'İptal Edildi'];
    $count = min(count($appointmentIds), count($statuses));

    $update = $conn->prepare("UPDATE book SET Status = ?, active_slot_key = CASE WHEN ? IN ('İptal Edildi', 'Tamamlandı') THEN NULL ELSE active_slot_key END WHERE appointment_id = ? AND DID = ?");

    for ($j = 0; $j < $count; $j++) {
        $appointmentId = (int)$appointmentIds[$j];
        $status = trim((string)$statuses[$j]);
        if ($appointmentId <= 0 || !in_array($status, $allowedStatuses, true)) {
            continue;
        }

        $update->bind_param('ssii', $status, $status, $appointmentId, $doctorId);
        if ($update->execute() && $update->affected_rows > 0) {
            $name = $firstNames[$j] ?? 'Randevu';
            $message .= htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8') . ' : Durum başarıyla güncellendi.<br>';
        }
    }
    $update->close();
}

$did = $doctorId;
$dateselected = $_POST['dateselected'] ?? '';
$doctors = $conn->prepare('SELECT DID, Name FROM doctor WHERE DID = ? LIMIT 1');
$doctors->bind_param('i', $doctorId);
$doctors->execute();
$doctorResult = $doctors->get_result();
$doctorRow = $doctorResult->fetch_assoc();
$doctors->close();
$rows = [];

if (isset($_POST['submit']) && $dateselected !== '') {
    $query = $conn->prepare('SELECT appointment_id, Username, Fname, DOV, Timestamp, Status FROM book WHERE DOV = ? AND DID = ? ORDER BY Timestamp ASC');
    $query->bind_param('si', $dateselected, $doctorId);
    $query->execute();
    $result = $query->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $query->close();
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Randevu Durumu Güncelle</title>
<link rel="stylesheet" href="main.css">
<style>
table { width:100%; border-collapse:collapse; border:2px solid #222; font-size:16px; }
th, td { border:1px solid #222; padding:8px; }
th { background:#4CAF50; color:white; text-align:left; }
td { background:white; color:#111; }
</style>
</head>
<body style="background-color:white">
<div class="header"></div>
<div class="sucontainer">
    <?php if ($message !== ''): ?><div role="alert"><?php echo $message; ?></div><?php endif; ?>

    <form action="changebookingstatus.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <label for="doctor-list" style="font-size:20px">Doktor:</label><br>
        <select name="doctor" id="doctor-list" class="demoInputBox" style="width:100%;height:35px;border-radius:9px" disabled>
            <option value="<?php echo $doctorId; ?>"><?php echo htmlspecialchars('Dr. ' . ($doctorRow['Name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
        </select>
        <br>
        <label for="date-selected"><b>Tarih:</b></label><br>
        <input id="date-selected" type="date" name="dateselected" value="<?php echo htmlspecialchars($dateselected, ENT_QUOTES, 'UTF-8'); ?>" required><br><br>
        <button type="submit" name="submit" value="Submit">Randevuları Getir</button>
    </form>

    <?php if (isset($_POST['submit']) && $dateselected !== ''): ?>
        <form action="changebookingstatus.php" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <table>
                <tr><th>Kullanıcı</th><th>Hasta</th><th>Tarih</th><th>Timestamp</th><th>Durum</th></tr>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">Bu tarihte randevu bulunamadı.</td></tr>
                <?php else: foreach ($rows as $row): ?>
                    <tr>
                        <td><input type="text" name="username[]" value="<?php echo htmlspecialchars($row['Username'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                        <td><input type="text" name="fname[]" value="<?php echo htmlspecialchars($row['Fname'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                        <td><input type="date" name="dov[]" value="<?php echo htmlspecialchars($row['DOV'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                        <td><input type="text" name="timestamp[]" value="<?php echo htmlspecialchars($row['Timestamp'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                        <td>
                            <input type="hidden" name="appointment_id[]" value="<?php echo (int)$row['appointment_id']; ?>">
                            <select name="status[]">
                                <?php foreach (['Bekliyor', 'Onaylandı', 'Tamamlandı', 'İptal Edildi'] as $option): ?>
                                    <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $row['Status'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </table>
            <?php if ($rows): ?><button type="submit" name="submit2" value="Submit">Değişiklikleri Kaydet</button><?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
