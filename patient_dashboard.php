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

/* Hasta kendi randevusunu iptal ediyor */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {

    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    if ($appointmentId <= 0) {
        $message = 'Geçersiz randevu.';
        $messageType = 'error';
    } else {

        /*
         * Sadece giriş yapan kullanıcıya ait,
         * gelecekteki ve tamamlanmamış randevu iptal edilebilir.
         */
        $cancelStmt = $conn->prepare("
            UPDATE book
            SET Status = 'İptal Edildi'
            WHERE appointment_id = ?
              AND Username = ?
              AND DOV >= CURDATE()
              AND Status NOT IN ('Tamamlandı', 'İptal Edildi')
        ");

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

/* Hastanın randevuları */
$stmt = $conn->prepare("
    SELECT
        b.appointment_id,
        b.Fname,
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
    WHERE b.Username = ?
    ORDER BY b.DOV DESC, b.appointment_time DESC
");

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();

/* Duruma göre CSS sınıfı */
function getStatusClass($status)
{
    $status = trim((string)$status);

    switch ($status) {
        case 'Onaylandı':
            return 'status-approved';

        case 'Tamamlandı':
            return 'status-completed';

        case 'İptal Edildi':
        case 'Cancelled by Patient':
            return 'status-cancelled';

        case 'Bekliyor':
        case 'Booked':
        default:
            return 'status-pending';
    }
}

/* Duruma göre görünen metin */
function getStatusText($status)
{
    $status = trim((string)$status);

    switch ($status) {
        case 'Booked':
            return 'Bekliyor';

        case 'Cancelled by Patient':
            return 'İptal Edildi';

        default:
            return $status !== '' ? $status : 'Bekliyor';
    }
}

/* İptal butonu gösterilebilir mi? */
function canCancelAppointment($row)
{
    $status = trim((string)$row['Status']);

    if (
        $status === 'Tamamlandı' ||
        $status === 'İptal Edildi' ||
        $status === 'Cancelled by Patient'
    ) {
        return false;
    }

    if (empty($row['DOV'])) {
        return false;
    }

    return strtotime($row['DOV']) >= strtotime(date('Y-m-d'));
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
        .patient-status-card {
            margin-top: 10px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 12px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
        }

        .status-approved {
            background: #e8f7ee;
            color: #137333;
        }

        .status-completed {
            background: #e9f1ff;
            color: #185abc;
        }

        .status-cancelled {
            background: #fdecec;
            color: #b3261e;
        }

        .status-pending {
            background: #fff5d9;
            color: #8a5a00;
        }

        .appointment-item {
            position: relative;
        }

        .appointment-actions {
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
        }

        .cancel-button {
            border: 0;
            border-radius: 9px;
            padding: 9px 14px;
            background: #fff0f0;
            color: #a51d2d;
            font-weight: 700;
            cursor: pointer;
        }

        .cancel-button:hover {
            background: #ffe0e0;
        }

        .panel-message {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-weight: 600;
        }

        .panel-message.success {
            background: #eaf8f0;
            color: #146c3a;
        }

        .panel-message.error {
            background: #fff0f0;
            color: #a51d2d;
        }

        .appointment-status-label {
            display: block;
            margin-bottom: 6px;
            font-size: 12px;
            color: #687386;
            font-weight: 600;
        }
    </style>
</head>

<body class="booking-page">

<header class="site-header">
    <div class="header-inner">

        <a href="patient_dashboard.php" class="brand">
            <img src="images/cal.png" alt="Takvim">
            <span>Appointment</span>
        </a>

        <nav>
            <a class="active" href="patient_dashboard.php">Randevularım</a>
            <a href="book.php">Yeni Randevu</a>
            <a href="logout.php">Çıkış</a>
        </nav>

    </div>
</header>

<main class="booking-wrapper">

<section class="booking-card">

    <div class="booking-intro">

        <span class="eyebrow">HASTA PANELİ</span>

        <h1>Randevularım</h1>

        <p>
            Aktif ve geçmiş randevularınızı buradan takip edebilirsiniz.
        </p>

    </div>

    <?php if ($message !== ''): ?>

        <div class="panel-message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

    <?php endif; ?>


    <div class="appointment-list">

        <?php if ($result->num_rows === 0): ?>

            <div class="alert">
                Henüz kayıtlı randevunuz bulunmuyor.
            </div>

        <?php else: ?>

            <?php while ($row = $result->fetch_assoc()): ?>

                <?php
                    $statusClass = getStatusClass($row['Status']);
                    $statusText = getStatusText($row['Status']);
                ?>

                <article class="appointment-item">

                    <!-- Hasta / Klinik -->
                    <div>

                        <strong>
                            <?php echo htmlspecialchars($row['Fname']); ?>
                        </strong>

                        <span>
                            <?php
                            echo htmlspecialchars(
                                $row['clinic_name'] ?? 'Klinik'
                            );
                            ?>

                            <?php if (!empty($row['town'])): ?>
                                · <?php echo htmlspecialchars($row['town']); ?>
                            <?php endif; ?>
                        </span>

                    </div>


                    <!-- Doktor / Tarih -->
                    <div>

                        <strong>
                            Dr. <?php
                            echo htmlspecialchars(
                                $row['doctor_name'] ?? 'Doktor'
                            );
                            ?>
                        </strong>

                        <span>

                            <?php
                            echo date(
                                'd.m.Y',
                                strtotime($row['DOV'])
                            );
                            ?>

                            <?php if (!empty($row['appointment_time'])): ?>

                                · <?php
                                echo htmlspecialchars(
                                    substr($row['appointment_time'], 0, 5)
                                );
                                ?>

                            <?php endif; ?>

                        </span>

                    </div>


                    <!-- Durum -->
                    <div class="patient-status-card">

                        <span class="appointment-status-label">
                            Randevu Durumu
                        </span>

                        <span class="status-pill <?php echo $statusClass; ?>">

                            <?php if ($statusClass === 'status-approved'): ?>
                                ●
                            <?php elseif ($statusClass === 'status-completed'): ?>
                                ✓
                            <?php elseif ($statusClass === 'status-cancelled'): ?>
                                ×
                            <?php else: ?>
                                ●
                            <?php endif; ?>

                            <?php echo htmlspecialchars($statusText); ?>

                        </span>

                        <small>
                            Oluşturulma:
                            <?php echo htmlspecialchars($row['Timestamp']); ?>
                        </small>


                        <?php if (canCancelAppointment($row)): ?>

                            <div class="appointment-actions">

                                <form method="post"
                                      onsubmit="return confirm('Bu randevuyu iptal etmek istediğinizden emin misiniz?');">

                                    <input
                                        type="hidden"
                                        name="appointment_id"
                                        value="<?php echo (int)$row['appointment_id']; ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="cancel_appointment"
                                        class="cancel-button"
                                    >
                                        Randevuyu İptal Et
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </div>

                </article>

            <?php endwhile; ?>

        <?php endif; ?>

    </div>


    <a
        class="primary-button"
        href="book.php"
        style="display:block;text-align:center;margin-top:22px"
    >
        Yeni Randevu Al
    </a>

</section>

</main>

</body>
</html>

<?php
$stmt->close();
?>