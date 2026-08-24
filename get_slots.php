<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once 'dbconfig.php';

$cid = (int)($_POST['cid'] ?? 0);
$did = (int)($_POST['did'] ?? 0);
$date = $_POST['date'] ?? '';

$parsed = DateTime::createFromFormat('Y-m-d', $date);
if ($cid <= 0 || $did <= 0 || !$parsed || $parsed->format('Y-m-d') !== $date) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Geçersiz randevu bilgisi.']);
    exit;
}

$day = $parsed->format('l');
$stmt = $conn->prepare('SELECT starttime, endtime FROM doctor_availability WHERE CID = ? AND DID = ? AND day = ? LIMIT 1');
$stmt->bind_param('iis', $cid, $did, $day);
$stmt->execute();
$result = $stmt->get_result();
$availability = $result->fetch_assoc();
$stmt->close();

if (!$availability) {
    echo json_encode(['ok' => true, 'available' => false, 'slots' => [], 'message' => 'Doktor bu gün çalışmıyor.']);
    exit;
}

$booked = [];
$stmt = $conn->prepare("SELECT appointment_time FROM book WHERE CID = ? AND DID = ? AND DOV = ? AND Status NOT LIKE '%cancel%' AND appointment_time IS NOT NULL");
$stmt->bind_param('iis', $cid, $did, $date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $booked[$row['appointment_time']] = true;
}
$stmt->close();

$start = new DateTime($date . ' ' . $availability['starttime']);
$end = new DateTime($date . ' ' . $availability['endtime']);
$now = new DateTime();
$slots = [];

// 30-minute appointment duration.
while ($start < $end) {
    $value = $start->format('H:i:s');
    $label = $start->format('H:i');
    $isPast = ($date === $now->format('Y-m-d') && $start <= $now);
    $slots[] = [
        'value' => $value,
        'label' => $label,
        'available' => !isset($booked[$value]) && !$isPast
    ];
    $start->modify('+30 minutes');
}

echo json_encode(['ok' => true, 'available' => true, 'slots' => $slots, 'start' => $availability['starttime'], 'end' => $availability['endtime']]);
