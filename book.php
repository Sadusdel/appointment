<?php
session_start();
require_once 'dbconfig.php';

if (empty($_SESSION['username'])) {
    header('Location: ulogin.php');
    exit;
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $fname = trim($_POST['fname'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $cid = (int)($_POST['Clinic'] ?? 0);
    $did = (int)($_POST['Doctor'] ?? 0);
    $dov = $_POST['dov'] ?? '';
    $username = $_SESSION['username'];

    $allowedGender = ['female', 'male', 'other'];
    $dateObject = DateTime::createFromFormat('Y-m-d', $dov);
    $today = new DateTime('today');
    $lastAllowedDay = (new DateTime('today'))->modify('+7 days');

    if ($fname === '' || strlen($fname) > 120 || !in_array($gender, $allowedGender, true) || $cid <= 0 || $did <= 0 || !$dateObject) {
        $message = 'Lütfen tüm alanları geçerli şekilde doldurun.';
        $messageType = 'error';
    } elseif ($dateObject < $today || $dateObject > $lastAllowedDay) {
        $message = 'Randevu tarihi bugün ile 7 gün sonrası arasında olmalıdır.';
        $messageType = 'error';
    } else {
        $dayName = $dateObject->format('l');
        $availability = $conn->prepare('SELECT 1 FROM doctor_availability WHERE DID = ? AND CID = ? AND day = ? LIMIT 1');
        $availability->bind_param('iis', $did, $cid, $dayName);
        $availability->execute();
        $isAvailable = $availability->get_result()->num_rows > 0;
        $availability->close();

        if (!$isAvailable) {
            $message = 'Seçtiğiniz doktor bu tarihte klinikte çalışmıyor. Lütfen başka bir tarih seçin.';
            $messageType = 'error';
        } else {
            // Prevent accidental duplicate active bookings for the same patient/doctor/clinic/date.
            $duplicate = $conn->prepare('SELECT 1 FROM book WHERE Username = ? AND CID = ? AND DID = ? AND DOV = ? AND Status NOT LIKE ? LIMIT 1');
            $cancelledPattern = '%cancel%';
            $duplicate->bind_param('siiss', $username, $cid, $did, $dov, $cancelledPattern);
            $duplicate->execute();
            $hasDuplicate = $duplicate->get_result()->num_rows > 0;
            $duplicate->close();

            if ($hasDuplicate) {
                $message = 'Bu doktor için seçtiğiniz tarihte zaten aktif bir randevunuz bulunuyor.';
                $messageType = 'error';
            } else {
                $status = 'Booking Registered.Wait for the update';
                $timestamp = date('Y-m-d H:i:s');
                $insert = $conn->prepare('INSERT INTO book (Username, Fname, Gender, CID, DID, DOV, Timestamp, Status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $insert->bind_param('sssiisss', $username, $fname, $gender, $cid, $did, $dov, $timestamp, $status);

                if ($insert->execute()) {
                    $message = 'Randevunuz başarıyla oluşturuldu. Yönetici onayından sonra durumunuzu hesabınızdan takip edebilirsiniz.';
                    $messageType = 'success';
                } else {
                    $message = 'Randevu oluşturulurken bir hata oluştu. Lütfen tekrar deneyin.';
                    $messageType = 'error';
                }
                $insert->close();
            }
        }
    }
}

$cities = $conn->query('SELECT DISTINCT city FROM clinic ORDER BY city ASC');
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Randevu Al | Appointment Booking</title>
    <link rel="stylesheet" href="main.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
</head>
<body class="booking-page">
<header class="site-header">
    <div class="header-inner">
        <a href="ulogin.php" class="brand"><img src="images/cal.png" alt="Takvim"><span>Appointment</span></a>
        <nav>
            <a href="ulogin.php">Ana Sayfa</a>
            <a class="active" href="book.php">Randevu Al</a>
        </nav>
    </div>
</header>

<main class="booking-wrapper">
    <section class="booking-card">
        <div class="booking-intro">
            <span class="eyebrow">ONLINE RANDEVU</span>
            <h1>Randevunuzu kolayca oluşturun</h1>
            <p>Şehir, klinik, doktor ve uygun tarihi seçerek randevunuzu birkaç adımda tamamlayın.</p>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="book.php" method="post" id="booking-form">
            <div class="form-grid">
                <div class="field field-full">
                    <label for="fname">Hasta adı soyadı</label>
                    <input id="fname" type="text" name="fname" maxlength="120" placeholder="Ad Soyad" required>
                </div>

                <div class="field field-full">
                    <label>Cinsiyet</label>
                    <div class="radio-row">
                        <label><input type="radio" name="gender" value="female" required> Kadın</label>
                        <label><input type="radio" name="gender" value="male"> Erkek</label>
                        <label><input type="radio" name="gender" value="other"> Belirtmek istemiyorum</label>
                    </div>
                </div>

                <div class="field">
                    <label for="city-list">Şehir</label>
                    <select name="city" id="city-list" required>
                        <option value="">Şehir seçin</option>
                        <?php while ($row = $cities->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="town-list">İlçe</label>
                    <select id="town-list" name="Town" disabled required>
                        <option value="">Önce şehir seçin</option>
                    </select>
                </div>

                <div class="field">
                    <label for="clinic-list">Klinik</label>
                    <select id="clinic-list" name="Clinic" disabled required>
                        <option value="">Önce ilçe seçin</option>
                    </select>
                </div>

                <div class="field">
                    <label for="doctor-list">Doktor</label>
                    <select id="doctor-list" name="Doctor" disabled required>
                        <option value="">Önce klinik seçin</option>
                    </select>
                </div>

                <div class="field field-full">
                    <label for="dov">Randevu tarihi</label>
                    <input id="dov" type="date" name="dov" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+7 day')); ?>" required>
                    <small>Randevular en fazla 7 gün ileriye alınabilir.</small>
                </div>
            </div>

            <div id="datestatus" class="availability-box" aria-live="polite"></div>
            <button class="primary-button" type="submit" name="submit" value="Submit">Randevuyu Oluştur</button>
        </form>
    </section>
</main>

<script>
function setLoading(selector, text) {
    $(selector).prop('disabled', true).html('<option value="">' + text + '</option>');
}

$('#city-list').on('change', function () {
    const value = this.value;
    setLoading('#town-list', 'İlçeler yükleniyor...');
    setLoading('#clinic-list', 'Önce ilçe seçin');
    setLoading('#doctor-list', 'Önce klinik seçin');
    $('#datestatus').empty();
    if (!value) return;
    $.post('get_town.php', { countryid: value })
        .done(function (data) { $('#town-list').html(data).prop('disabled', false); })
        .fail(function () { setLoading('#town-list', 'İlçeler yüklenemedi'); });
});

$('#town-list').on('change', function () {
    const value = this.value;
    setLoading('#clinic-list', 'Klinikler yükleniyor...');
    setLoading('#doctor-list', 'Önce klinik seçin');
    $('#datestatus').empty();
    if (!value) return;
    $.post('getclinic.php', { townid: value })
        .done(function (data) { $('#clinic-list').html(data).prop('disabled', false); })
        .fail(function () { setLoading('#clinic-list', 'Klinikler yüklenemedi'); });
});

$('#clinic-list').on('change', function () {
    const value = this.value;
    setLoading('#doctor-list', 'Doktorlar yükleniyor...');
    $('#datestatus').empty();
    if (!value) return;
    $.post('getdoctordaybooking.php', { cid: value })
        .done(function (data) { $('#doctor-list').html(data).prop('disabled', false); })
        .fail(function () { setLoading('#doctor-list', 'Doktorlar yüklenemedi'); });
});

$('#doctor-list, #dov').on('change', function () {
    const doctor = $('#doctor-list').val();
    const clinic = $('#clinic-list').val();
    const date = $('#dov').val();
    if (!doctor || !clinic || !date) {
        $('#datestatus').empty();
        return;
    }
    $('#datestatus').html('<span class="loading">Müsaitlik kontrol ediliyor...</span>');
    $.post('getDay.php', { date: date, cidval: clinic, didval: doctor })
        .done(function (data) { $('#datestatus').html(data); })
        .fail(function () { $('#datestatus').html('<span class="status-error">Müsaitlik bilgisi alınamadı.</span>'); });
});
</script>
</body>
</html>
