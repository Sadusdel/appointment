<?php
session_start();
require_once __DIR__ . '/Admin/csrf_guard.php';
require_once 'dbconfig.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');

if ($doctorId <= 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowedStatuses = ['Onaylandı', 'Tamamlandı', 'İptal Edildi'];

if ($appointmentId <= 0 || !in_array($newStatus, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCurrent = $conn->prepare('SELECT Status FROM book WHERE appointment_id = ? AND DID = ? LIMIT 1');
$stmtCurrent->bind_param('ii', $appointmentId, $doctorId);
$stmtCurrent->execute();
$currentRow = $stmtCurrent->get_result()->fetch_assoc();
$stmtCurrent->close();

if (!$currentRow) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Randevu bulunamadı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentStatus = trim((string)$currentRow['Status']);
if ($currentStatus === '' || $currentStatus === 'Booked') {
    $currentStatus = 'Bekliyor';
}

$validTransition = ($currentStatus === 'Bekliyor' && in_array($newStatus, ['Onaylandı', 'İptal Edildi'], true))
    || ($currentStatus === 'Onaylandı' && $newStatus === 'Tamamlandı');

if (!$validTransition) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Bu randevu için bu durum değişikliğine izin verilmiyor.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $conn->prepare('UPDATE book SET Status = ? WHERE appointment_id = ? AND DID = ?');
$stmt->bind_param('sii', $newStatus, $appointmentId, $doctorId);
$ok = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if (!$ok) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Randevu durumu güncellenemedi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($affected === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Randevu bulunamadı veya durum zaten aynı.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'success' => true,
    'appointment_id' => $appointmentId,
    'status' => $newStatus
], JSON_UNESCAPED_UNICODE);

$conn->close();
