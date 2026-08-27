<?php
session_start();
if(!isset($_SESSION['userName'])||$_SESSION['userName']!=='admin'){http_response_code(403);exit;}
require 'dbconfig.php';$city=trim($_POST['city']??'');echo '<option value="">Klinik seçin</option>';if($city==='')exit;
$q=$conn->prepare('SELECT CID,Name,Town,City FROM clinic WHERE City=? ORDER BY Town,Name');$q->bind_param('s',$city);$q->execute();$res=$q->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['CID'].'">'.htmlspecialchars($r['Name'].' — '.$r['Town'].' (CID-'.$r['CID'].')',ENT_QUOTES,'UTF-8').'</option>';$q->close();
?>