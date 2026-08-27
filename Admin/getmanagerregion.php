<?php
session_start();
if(!isset($_SESSION['userName'])||$_SESSION['userName']!=='admin'){http_response_code(403);exit;}
require 'dbconfig.php';$city=trim($_POST['city']??'');echo '<option value="">Manager seçin</option>';if($city==='')exit;
$q=$conn->prepare('SELECT MID,Name,Region FROM manager WHERE Region LIKE CONCAT("%",?,"%") ORDER BY Name');$q->bind_param('s',$city);$q->execute();$res=$q->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['MID'].'">'.htmlspecialchars($r['Name'].' — '.$r['Region'],ENT_QUOTES,'UTF-8').'</option>';$q->close();
?>