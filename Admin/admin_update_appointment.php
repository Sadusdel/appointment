<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['userName']) || $_SESSION['userName'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once 'dbconfig.php';

$appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
$newStatus = trim($_POST['status'] ?? '');
$allowedStatuses = ['Onaylandı', 'Tamamlandı', 'İptal Edildi'];

if (!$appointmentId || !in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz randevu veya durum.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('SELECT Status FROM book WHERE appointment_id = ? LIMIT 1');
$stmt->bind_param('i', $appointmentId);
$stmt->execute();
$current = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$current) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Randevu bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentStatus = trim((string)$current['Status']);
if ($currentStatus === 'Booked' || $currentStatus === '') {
    $currentStatus = 'Bekliyor';
}

$validTransition = ($newStatus === 'Onaylandı' && $currentStatus === 'Bekliyor')
    || ($newStatus === 'Tamamlandı' && $currentStatus === 'Onaylandı')
    || ($newStatus === 'İptal Edildi' && in_array($currentStatus, ['Bekliyor', 'Onaylandı'], true));

if (!$validTransition) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Bu randevu için bu durum değişikliğine izin verilmiyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('UPDATE book SET Status = ? WHERE appointment_id = ?');
$stmt->bind_param('si', $newStatus, $appointmentId);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Randevu durumu güncellenemedi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'appointment_id' => $appointmentId, 'status' => $newStatus], JSON_UNESCAPED_UNICODE);
?>