<?php
session_start();
if(!isset($_SESSION['user'])&&!isset($_SESSION['username'])){http_response_code(403);exit;}
require_once 'dbconfig.php';
$date=trim($_POST['date']??'');$did=filter_input(INPUT_POST,'didval',FILTER_VALIDATE_INT);$cid=filter_input(INPUT_POST,'cidval',FILTER_VALIDATE_INT);
header('Content-Type: text/plain; charset=utf-8');
if(!$did||!$cid||$date===''){echo 'Doktor, klinik ve tarihi seçin.';exit;}
$timestamp=strtotime($date);if($timestamp===false){echo 'Geçersiz tarih.';exit;}
$day=date('l',$timestamp);
$stmt=$conn->prepare('SELECT 1 FROM doctor_availability WHERE DID=? AND CID=? AND Day=? LIMIT 1');
$stmt->bind_param('iis',$did,$cid,$day);$stmt->execute();$available=$stmt->get_result()->fetch_assoc();$stmt->close();
echo $available?'Doktor '.$day.' günü müsait.':'Doktor '.$day.' günü müsait değil.';
?>