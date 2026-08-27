<?php
session_start();
include 'dbconfig.php';

$username = $_SESSION['username'] ?? '';
if ($username === '') {
    header('Location: ulogin.php');
    exit;
}

$message = '';
$date = date('Y-m-d');

if (isset($_POST['submit'])) {
    $timestamp = $_POST['appointment'] ?? '';

    $update = $conn->prepare("UPDATE book SET Status = 'İptal Edildi', active_slot_key = NULL WHERE Username = ? AND Timestamp = ? AND DOV >= ? AND Status NOT IN ('İptal Edildi', 'Tamamlandı')");
    $update->bind_param('sss', $username, $timestamp, $date);

    if ($update->execute() && $update->affected_rows > 0) {
        $message = 'Randevunuz başarıyla iptal edildi.';
    } else {
        $message = 'Randevu iptal edilemedi veya randevu zaten pasif durumda.';
    }
    $update->close();
}

$appointments = [];
$list = $conn->prepare("SELECT appointment_id, Fname, DID, CID, DOV, appointment_time, Timestamp FROM book WHERE Username = ? AND DOV >= ? AND Status NOT IN ('İptal Edildi', 'Tamamlandı') ORDER BY DOV ASC, appointment_time ASC");
$list->bind_param('ss', $username, $date);
$list->execute();
$result = $list->get_result();
while ($row = $result->fetch_assoc()) {
    $appointments[] = $row;
}
$list->close();
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Randevu İptali</title>
<link rel="stylesheet" href="main.css">
</head>
<body style="background-image:url(images/cancelback.jpg)">
<div class="header">
    <ul>
        <li style="float:left;border-right:none"><a href="ulogin.php" class="logo"><img src="images/cal.png" width="30" height="30" alt="Takvim"><strong> Appointment </strong></a></li>
        <li><a href="ulogin.php">Ana Sayfa</a></li>
    </ul>
</div>

<div class="sucontainer">
    <form action="cancelbookingpatient.php" method="post">
        <label style="font-size:20px">İptal edilecek randevuyu seçin:</label><br>
        <select name="appointment" id="appointment-list" class="demoInputBox" style="width:100%;height:35px;border-radius:9px" required>
            <option value="">Randevu seçin</option>
            <?php foreach ($appointments as $appointment): ?>
                <option value="<?php echo htmlspecialchars($appointment['Timestamp'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php echo htmlspecialchars('Hasta: ' . $appointment['Fname'] . ' Tarih: ' . $appointment['DOV'] . ' Saat: ' . substr($appointment['appointment_time'], 0, 5) . ' - Randevu No: ' . $appointment['appointment_id'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button type="submit" name="submit" value="Submit">Randevuyu İptal Et</button>
    </form>

    <?php if ($message !== ''): ?>
        <p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
</div>
</body>
</html>
