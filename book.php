<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
session_start();
require_once 'dbconfig.php';

$username = $_SESSION['username'] ?? $_SESSION['user'] ?? '';
if ($username === '') {
    header('Location: ulogin.php');
    exit;
}

$message = '';
$messageType = '';

// The submit button is disabled by the JS submit handler immediately before
// native form serialization. Therefore do not depend on $_POST['submit'] here.
// The HTTP method is the reliable indication that this form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fname = trim($_POST['fname'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $cid = (int)($_POST['Clinic'] ?? 0);
        $did = (int)($_POST['Doctor'] ?? 0);
        $dov = $_POST['dov'] ?? '';
        $appointmentTime = $_POST['appointment_time'] ?? '';

        $allowedGender = ['female', 'male', 'other'];
        $dateObject = DateTime::createFromFormat('!Y-m-d', $dov);
        $timeObject = DateTime::createFromFormat('!H:i:s', $appointmentTime);
        $dateErrors = DateTime::getLastErrors();
        if ($dateErrors === false) {
            $dateErrors = ['warning_count' => 0, 'error_count' => 0];
        }
        $today = new DateTime('today');
        $lastAllowedDay = (new DateTime('today'))->modify('+7 days');

        if (
            $fname === '' ||
            strlen($fname) > 30 ||
            !in_array($gender, $allowedGender, true) ||
            $cid <= 0 ||
            $did <= 0 ||
            !$dateObject ||
            !$timeObject ||
            $dateErrors['warning_count'] > 0 ||
            $dateErrors['error_count'] > 0
        ) {
            $message = 'Lütfen tüm alanları geçerli şekilde doldurun.';
            $messageType = 'error';
        } elseif ($dateObject < $today || $dateObject > $lastAllowedDay) {
            $message = 'Randevu tarihi bugün ile 7 gün sonrası arasında olmalıdır.';
            $messageType = 'error';
        } else {
            $dayName = $dateObject->format('l');
            $availability = $conn->prepare('SELECT starttime, endtime FROM doctor_availability WHERE DID = ? AND CID = ? AND day = ? LIMIT 1');
            $availability->bind_param('iis', $did, $cid, $dayName);
            $availability->execute();
            $availabilityResult = $availability->get_result();
            $availabilityRow = $availabilityResult->fetch_assoc();
            $availability->close();

            if (!$availabilityRow) {
                $message = 'Seçtiğiniz doktor bu tarihte çalışmıyor.';
                $messageType = 'error';
            } else {
                $selectedSeconds = strtotime($appointmentTime);
                $startSeconds = strtotime($availabilityRow['starttime']);
                $endSeconds = strtotime($availabilityRow['endtime']);
                $validSlot = $selectedSeconds >= $startSeconds && $selectedSeconds < $endSeconds && (($selectedSeconds - $startSeconds) % 1800 === 0);

                if (!$validSlot) {
                    $message = 'Seçtiğiniz saat geçerli bir randevu slotu değil.';
                    $messageType = 'error';
                } else {
                    $activeSlotKey = $did . '|' . $cid . '|' . $dov . '|' . $appointmentTime;

                    $duplicate = $conn->prepare('SELECT appointment_id FROM book WHERE active_slot_key = ? LIMIT 1');
                    $duplicate->bind_param('s', $activeSlotKey);
                    $duplicate->execute();
                    $occupied = $duplicate->get_result()->num_rows > 0;
                    $duplicate->close();

                    if ($occupied) {
                        $message = 'Bu saat az önce başka bir hasta tarafından alınmış. Lütfen başka bir saat seçin.';
                        $messageType = 'error';
                    } else {
                        $status = 'Bekliyor';
                        $timestamp = date('Y-m-d H:i:s');

                        $insert = $conn->prepare('INSERT INTO book (Username, Fname, Gender, CID, DID, DOV, appointment_time, Timestamp, Status, active_slot_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $insert->bind_param('sssiisssss', $username, $fname, $gender, $cid, $did, $dov, $appointmentTime, $timestamp, $status, $activeSlotKey);

                        try {
                            $insert->execute();
                            $message = 'Randevunuz başarıyla oluşturuldu. ' . $dateObject->format('d.m.Y') . ' ' . substr($appointmentTime, 0, 5) . ' için kaydınız alındı.';
                            $messageType = 'success';
                        } catch (mysqli_sql_exception $e) {
                            if ((int)$e->getCode() === 1062) {
                                $message = 'Bu saat az önce başka bir hasta tarafından alınmış. Lütfen başka bir saat seçin.';
                            } else {
                                error_log('Appointment INSERT failed: ' . $e->getMessage());
                                $message = 'Randevu oluşturulurken veritabanı hatası oluştu. Lütfen tekrar deneyin.';
                            }
                            $messageType = 'error';
                        }
                        $insert->close();
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('Appointment booking error: ' . $e->getMessage());
        $message = 'Randevu işlemi sırasında bir hata oluştu: ' . $e->getMessage();
        $messageType = 'error';
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
        <nav><a href="ulogin.php">Ana Sayfa</a><a class="active" href="book.php">Randevu Al</a></nav>
    </div>
</header>

<main class="booking-wrapper">
<section class="booking-card">
    <div class="booking-intro">
        <span class="eyebrow">ONLINE RANDEVU</span>
        <h1>Randevunuzu kolayca oluşturun</h1>
        <p>Size uygun doktoru, tarihi ve 30 dakikalık boş randevu saatini birkaç adımda seçin.</p>
    </div>

    <?php if ($message !== ''): ?>
        <div class="alert <?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
            <strong><?php echo $messageType === 'success' ? 'Randevu oluşturuldu' : 'İşlem tamamlanamadı'; ?></strong>
            <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
    <?php endif; ?>

    <form action="book.php" method="post" id="booking-form">
        <div class="booking-step">
            <div class="step-heading"><span>1</span><div><strong>Hasta Bilgileri</strong><small>Randevu sahibinin bilgilerini girin.</small></div></div>
            <div class="form-grid">
                <div class="field field-full">
                    <label for="fname">Hasta adı soyadı</label>
                    <input id="fname" type="text" name="fname" maxlength="30" placeholder="Ad Soyad" required>
                </div>
                <div class="field field-full">
                    <label for="gender-female">Cinsiyet</label>
                    <div class="radio-row">
                        <label><input id="gender-female" type="radio" name="gender" value="female" required> Kadın</label>
                        <label><input id="gender-male" type="radio" name="gender" value="male"> Erkek</label>
                        <label><input id="gender-other" type="radio" name="gender" value="other"> Belirtmek istemiyorum</label>
                    </div>
                </div>
            </div>
        </div>

        <div class="booking-step">
            <div class="step-heading"><span>2</span><div><strong>Klinik ve Doktor</strong><small>Size uygun sağlık kuruluşu ve doktoru seçin.</small></div></div>
            <div class="form-grid">
                <div class="field">
                    <label for="city-list">Şehir</label>
                    <select name="city" id="city-list" required><option value="">Şehir seçin</option>
                        <?php while ($row = $cities->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($row['city'], ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="town-list">İlçe</label>
                    <select id="town-list" name="Town" disabled required><option value="">Önce şehir seçin</option></select>
                </div>
                <div class="field">
                    <label for="clinic-list">Klinik</label>
                    <select id="clinic-list" name="Clinic" disabled required><option value="">Önce ilçe seçin</option></select>
                </div>
                <div class="field">
                    <label for="doctor-list">Doktor</label>
                    <select id="doctor-list" name="Doctor" disabled required><option value="">Önce klinik seçin</option></select>
                </div>
            </div>
        </div>

        <div class="booking-step">
            <div class="step-heading"><span>3</span><div><strong>Randevu Zamanı</strong><small>Uygun tarih ve saati seçin.</small></div></div>
            <div class="form-grid">
                <div class="field">
                    <label for="dov">Randevu tarihi</label>
                    <input id="dov" type="date" name="dov" min="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d', strtotime('+7 day')); ?>" required>
                </div>
            </div>
            <div class="field time-field">
                <label for="appointment-time">Uygun saat</label>
                <div id="appointment-time-grid" class="time-grid" aria-live="polite">
                    <div class="time-placeholder">Önce doktor ve tarih seçin.</div>
                </div>
                <select id="appointment-time" name="appointment_time" class="visually-hidden-select" disabled required aria-hidden="true" tabindex="-1"><option value="">Saat seçin</option></select>
            </div>
        </div>

        <div id="datestatus" class="availability-box" aria-live="polite"></div>

        <div class="booking-summary" id="booking-summary" aria-live="polite">
            <div class="summary-title">Randevu Özeti</div>
            <div class="summary-grid">
                <div><span>Doktor</span><strong id="summary-doctor">Seçilmedi</strong></div>
                <div><span>Klinik</span><strong id="summary-clinic">Seçilmedi</strong></div>
                <div><span>Tarih</span><strong id="summary-date">Seçilmedi</strong></div>
                <div><span>Saat</span><strong id="summary-time">Seçilmedi</strong></div>
            </div>
        </div>

        <button class="primary-button" id="submit-button" type="submit" name="submit" value="Submit" disabled>Randevuyu Oluştur</button>
    </form>
</section>
</main>

<script>
function setLoading(selector, text) { $(selector).prop('disabled', true).html('<option value="">' + text + '</option>'); }
function resetSlots(text) {
    setLoading('#appointment-time', 'Saat seçin');
    $('#appointment-time-grid').html('<div class="time-placeholder">' + text + '</div>');
    $('#datestatus').empty();
    updateSummary();
}
function escapeHtml(value) {
    return $('<div>').text(value || '').html();
}
function updateSummary() {
    const doctorText = $('#doctor-list option:selected').text();
    const clinicText = $('#clinic-list option:selected').text();
    const dateValue = $('#dov').val();
    const timeValue = $('#appointment-time').val();
    const dateText = dateValue ? new Date(dateValue + 'T00:00:00').toLocaleDateString('tr-TR') : 'Seçilmedi';
    const doctorValid = $('#doctor-list').val();
    const clinicValid = $('#clinic-list').val();

    $('#summary-doctor').text(doctorValid ? doctorText : 'Seçilmedi');
    $('#summary-clinic').text(clinicValid ? clinicText : 'Seçilmedi');
    $('#summary-date').text(dateText);
    $('#summary-time').text(timeValue ? timeValue.substring(0, 5) : 'Seçilmedi');
    $('#submit-button').prop('disabled', !(doctorValid && clinicValid && dateValue && timeValue));
}

$('#city-list').on('change', function () {
    setLoading('#town-list', 'İlçeler yükleniyor...');
    setLoading('#clinic-list', 'Önce ilçe seçin');
    setLoading('#doctor-list', 'Önce klinik seçin');
    resetSlots('Önce doktor ve tarih seçin.');
    if (!this.value) return;
    $.post('get_town.php', { countryid: this.value }).done(data => $('#town-list').html(data).prop('disabled', false)).fail(() => setLoading('#town-list', 'İlçeler yüklenemedi'));
});

$('#town-list').on('change', function () {
    setLoading('#clinic-list', 'Klinikler yükleniyor...');
    setLoading('#doctor-list', 'Önce klinik seçin');
    resetSlots('Önce doktor ve tarih seçin.');
    if (!this.value) return;
    $.post('getclinic.php', { townid: this.value }).done(data => $('#clinic-list').html(data).prop('disabled', false)).fail(() => setLoading('#clinic-list', 'Klinikler yüklenemedi'));
});

$('#clinic-list').on('change', function () {
    setLoading('#doctor-list', 'Doktorlar yükleniyor...');
    resetSlots('Önce doktor ve tarih seçin.');
    if (!this.value) return;
    $.post('getdoctordaybooking.php', { cid: this.value }).done(data => $('#doctor-list').html(data).prop('disabled', false)).fail(() => setLoading('#doctor-list', 'Doktorlar yüklenemedi'));
});

function selectTime(value) {
    $('#appointment-time').val(value).prop('disabled', false);
    $('#appointment-time-grid .time-option').removeClass('selected');
    $('#appointment-time-grid .time-option[data-value="' + value + '"]').addClass('selected');
    updateSummary();
}

function loadSlots() {
    const doctor = $('#doctor-list').val(), clinic = $('#clinic-list').val(), date = $('#dov').val();
    if (!doctor || !clinic || !date) { resetSlots('Önce doktor ve tarih seçin.'); return; }
    resetSlots('Saatler kontrol ediliyor...');
    $.post('get_slots.php', { date: date, cid: clinic, did: doctor }).done(function (response) {
        if (!response.ok) { resetSlots(response.message || 'Saatler alınamadı.'); return; }
        if (!response.available) { resetSlots('Bu tarihte doktorun çalışma saati bulunmuyor.'); return; }
        const available = response.slots.filter(slot => slot.available);
        if (!available.length) {
            resetSlots('Bu tarih için boş randevu saati kalmadı.');
            $('#datestatus').html('<span class="status-error">Tüm slotlar dolu veya geçmiş.</span>');
            return;
        }
        $('#appointment-time-grid').html(available.map(slot => '<button type="button" class="time-option" data-value="' + escapeHtml(slot.value) + '" onclick="selectTime(\'' + escapeHtml(slot.value) + '\')">' + escapeHtml(slot.label) + '</button>').join(''));
        $('#appointment-time').html('<option value="">Saat seçin</option>' + available.map(slot => '<option value="' + escapeHtml(slot.value) + '">' + escapeHtml(slot.label) + '</option>').join('')).prop('disabled', false);
        $('#datestatus').html('<span class="loading">' + available.length + ' boş randevu slotu bulundu.</span>');
        updateSummary();
    }).fail(function () {
        resetSlots('Saatler alınamadı.');
        $('#datestatus').html('<span class="status-error">Müsaitlik bilgisi alınamadı.</span>');
    });
}

$('#doctor-list, #dov').on('change', function () { loadSlots(); updateSummary(); });
$('#clinic-list, #doctor-list, #dov').on('change', updateSummary);
$('#booking-form').on('submit', function () {
    const button = $('#submit-button');
    if (button.prop('disabled')) {
        return false;
    }
    button.prop('disabled', true);
    button.text('Randevu oluşturuluyor...');
});
</script>
</body>
</html>