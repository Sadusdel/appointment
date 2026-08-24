<?php
session_start();
require_once 'dbconfig.php';

header('Content-Type: application/json; charset=utf-8');

$doctorId = (int)($_SESSION['doctor_id'] ?? 0);
$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$newStatus = trim($_POST['status'] ?? '');

$allowedStatuses = [
    'Onaylandı',
    'Tamamlandı',
    'İptal Edildi'
];

if (
    $doctorId <= 0 ||
    $appointmentId <= 0 ||
    !in_array($newStatus, $allowedStatuses, true)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Geçersiz istek.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt = $conn->prepare("
    UPDATE book
    SET Status = ?
    WHERE appointment_id = ?
    AND DID = ?
");

$stmt->bind_param(
    'sii',
    $newStatus,
    $appointmentId,
    $doctorId
);

if (!$stmt->execute()) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Randevu durumu güncellenemedi.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($stmt->affected_rows === 0) {

    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Randevu bulunamadı veya durum zaten aynı.'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    'success' => true,
    'appointment_id' => $appointmentId,
    'status' => $newStatus
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();