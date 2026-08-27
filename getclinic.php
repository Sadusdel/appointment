<?php
session_start();
if(!isset($_SESSION['user'])&&!isset($_SESSION['username'])){http_response_code(403);exit;}
require_once 'dbconfig.php';
$town=trim($_POST['townid']??'');
echo '<option value="">Klinik seçin</option>';
if($town==='')exit;
$stmt=$conn->prepare('SELECT CID,Name,Town FROM clinic WHERE Town=? ORDER BY Name');
$stmt->bind_param('s',$town);$stmt->execute();$result=$stmt->get_result();
while($row=$result->fetch_assoc()){$label=$row['Name'].' — '.$row['Town'].' (CID-'.$row['CID'].')';echo '<option value="'.(int)$row['CID'].'">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').'</option>';}
$stmt->close();
?>