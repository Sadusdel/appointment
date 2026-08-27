<?php
session_start();
if(!isset($_SESSION['user'])&&!isset($_SESSION['username'])){http_response_code(403);exit;}
require_once 'dbconfig.php';
$cid=filter_input(INPUT_POST,'cid',FILTER_VALIDATE_INT);
echo '<option value="">Doktor seçin</option>';
if(!$cid||$cid<1)exit;
$stmt=$conn->prepare('SELECT DISTINCT d.DID,d.Name FROM doctor_availability da INNER JOIN doctor d ON d.DID=da.DID WHERE da.CID=? ORDER BY d.Name');
$stmt->bind_param('i',$cid);$stmt->execute();$result=$stmt->get_result();
while($row=$result->fetch_assoc()){echo '<option value="'.(int)$row['DID'].'">Dr. '.htmlspecialchars($row['Name'],ENT_QUOTES,'UTF-8').'</option>';}
$stmt->close();
?>