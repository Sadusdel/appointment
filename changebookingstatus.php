<?php
session_start();
require_once 'dbconfig.php';

if (!isset($_SESSION['username'])) {
    header('Location: dlogin.php');
    exit;
}

$message = '';

if (isset($_POST['submit2'])) {
    $usernames = $_POST['username'] ?? [];
    $timestamps = $_POST['timestamp'] ?? [];
    $statuses = $_POST['status'] ?? [];
    $firstNames = $_POST['fname'] ?? [];

    $count = min(count($usernames), count($timestamps), count($statuses));
    $update = $conn->prepare("UPDATE book SET Status = ?, active_slot_key = CASE WHEN ? IN ('İptal Edildi', 'Tamamlandı') THEN NULL ELSE active_slot_key END WHERE Username = ? AND Timestamp = ?");

    for ($j = 0; $j < $count; $j++) {
        $status = trim($statuses[$j]);
        $username = $usernames[$j];
        $timestamp = $timestamps[$j];

        $update->bind_param('ssss', $status, $status, $username, $timestamp);
        if ($update->execute()) {
            $name = $firstNames[$j] ?? $username;
            $message .= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ' : Durum başarıyla güncellendi.<br>';
        }
    }
    $update->close();
}

$did = isset($_POST['doctor']) ? (int)$_POST['doctor'] : 0;
$dateselected = $_POST['dateselected'] ?? '';

$doctors = $conn->query('SELECT DID, Name FROM doctor ORDER BY Name ASC');
$rows = [];

if (isset($_POST['submit']) && $did > 0 && $dateselected !== '') {
    $query = $conn->prepare('SELECT Username, Fname, DOV, Timestamp, Status FROM book WHERE DOV = ? AND DID = ? ORDER BY Timestamp ASC');
    $query->bind_param('si', $dateselected, $did);
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
    <?php if ($message !== ''): ?>
        <div><?php echo $message; ?></div>
    <?php endif; ?>

    <form action="changebookingstatus.php" method="post">
        <label style="font-size:20px">Doktor:</label><br>
        <select name="doctor" id="doctor-list" class="demoInputBox" style="width:100%;height:35px;border-radius:9px" required>
            <option value="">Doktor seçin</option>
            <?php while ($doctor = $doctors->fetch_assoc()): ?>
                <option value="<?php echo (int)$doctor['DID']; ?>" <?php echo $did === (int)$doctor['DID'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars('Dr. ' . $doctor['Name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endwhile; ?>
        </select>
        <br>
        <label><b>Tarih:</b></label><br>
        <input type="date" name="dateselected" value="<?php echo htmlspecialchars($dateselected, ENT_QUOTES, 'UTF-8'); ?>" required><br><br>
        <button type="submit" name="submit" value="Submit">Randevuları Getir</button>
    </form>

    <?php if (isset($_POST['submit']) && $did > 0 && $dateselected !== ''): ?>
        <form action="changebookingstatus.php" method="post">
            <table>
                <tr>
                    <th>Kullanıcı</th>
                    <th>Hasta</th>
                    <th>Tarih</th>
                    <th>Timestamp</th>
                    <th>Durum</th>
                </tr>
                <?php if (!$rows): ?>
                    <tr><td colspan="5">Bu tarihte randevu bulunamadı.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><input type="text" name="username[]" value="<?php echo htmlspecialchars($row['Username'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                            <td><input type="text" name="fname[]" value="<?php echo htmlspecialchars($row['Fname'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                            <td><input type="date" name="dov[]" value="<?php echo htmlspecialchars($row['DOV'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                            <td><input type="text" name="timestamp[]" value="<?php echo htmlspecialchars($row['Timestamp'], ENT_QUOTES, 'UTF-8'); ?>" readonly></td>
                            <td>
                                <select name="status[]">
                                    <?php foreach (['Bekliyor', 'Onaylandı', 'Tamamlandı', 'İptal Edildi'] as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $row['Status'] === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </table>
            <?php if ($rows): ?>
                <button type="submit" name="submit2" value="Submit">Değişiklikleri Kaydet</button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
