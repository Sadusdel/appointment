<?php
require_once 'dbconfig.php';

$cid = filter_input(INPUT_POST, 'cid', FILTER_VALIDATE_INT);
if (!$cid || $cid < 1) {
    echo '<option value="">Select Doctor</option>';
    exit;
}

$stmt = $conn->prepare('SELECT DISTINCT d.DID, d.Name FROM doctor_availability da INNER JOIN doctor d ON d.DID = da.DID WHERE da.CID = ? ORDER BY d.Name');
$stmt->bind_param('i', $cid);
$stmt->execute();
$result = $stmt->get_result();

echo '<option value="">Select Doctor</option>';
while ($row = $result->fetch_assoc()) {
    echo '<option value="' . (int)$row['DID'] . '">' . htmlspecialchars($row['DID'] . ':' . $row['Name'], ENT_QUOTES, 'UTF-8') . '</option>';
}
$stmt->close();
