<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
if(!isset($_SESSION['user'])&&!isset($_SESSION['username'])){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Oturumunuz bulunmuyor.'],JSON_UNESCAPED_UNICODE);exit;}
require_once 'dbconfig.php';
$cid=(int)($_POST['cid']??0);$did=(int)($_POST['did']??0);$date=trim($_POST['date']??'');
$parsed=DateTime::createFromFormat('!Y-m-d',$date);$errors=DateTime::getLastErrors();
$validDate=$parsed!==false&&($errors===false||($errors['warning_count']===0&&$errors['error_count']===0))&&$parsed->format('Y-m-d')===$date;
$today=new DateTime('today');$maxDate=(clone $today)->modify('+7 days');
if($cid<=0||$did<=0||!$validDate||$parsed<$today||$parsed>$maxDate){http_response_code(400);echo json_encode(['ok'=>false,'message'=>'Geçersiz randevu bilgisi. Tarih bugün ile 7 gün sonrası arasında olmalıdır.'],JSON_UNESCAPED_UNICODE);exit;}
$day=$parsed->format('l');
$stmt=$conn->prepare('SELECT starttime,endtime FROM doctor_availability WHERE CID=? AND DID=? AND day=? ORDER BY starttime LIMIT 1');$stmt->bind_param('iis',$cid,$did,$day);$stmt->execute();$availability=$stmt->get_result()->fetch_assoc();$stmt->close();
if(!$availability){echo json_encode(['ok'=>true,'available'=>false,'slots'=>[],'message'=>'Doktor bu gün çalışmıyor.'],JSON_UNESCAPED_UNICODE);exit;}
$booked=[];
$stmt=$conn->prepare("SELECT appointment_time FROM book WHERE CID=? AND DID=? AND DOV=? AND (Status IS NULL OR (Status NOT LIKE '%cancel%' AND Status NOT LIKE '%İptal%' AND Status NOT LIKE '%iptal%')) AND appointment_time IS NOT NULL");$stmt->bind_param('iis',$cid,$did,$date);$stmt->execute();$result=$stmt->get_result();while($row=$result->fetch_assoc()){$booked[substr($row['appointment_time'],0,8)]=true;}$stmt->close();
$start=new DateTime($date.' '.substr($availability['starttime'],0,8));$end=new DateTime($date.' '.substr($availability['endtime'],0,8));$now=new DateTime();$slots=[];
while($start<$end){$value=$start->format('H:i:s');$slots[]=['value'=>$value,'label'=>$start->format('H:i'),'available'=>!isset($booked[$value])&&!($date===$now->format('Y-m-d')&&$start<=$now)];$start->modify('+30 minutes');}
echo json_encode(['ok'=>true,'available'=>true,'slots'=>$slots,'start'=>$availability['starttime'],'end'=>$availability['endtime']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
?>