<?php
session_start();
if(!isset($_SESSION['userName'])||$_SESSION['userName']!=='admin'){http_response_code(403);exit;}
require 'dbconfig.php';$cid=(int)($_POST['cid']??0);echo '<option value="">Manager seçin</option>';if($cid<=0)exit;
$q=$conn->prepare('SELECT mc.MID,m.Name,m.Region FROM manager_clinic mc INNER JOIN manager m ON m.MID=mc.MID WHERE mc.CID=? ORDER BY m.Name');$q->bind_param('i',$cid);$q->execute();$res=$q->get_result();while($r=$res->fetch_assoc())echo '<option value="'.(int)$r['MID'].'">'.htmlspecialchars($r['Name'].' — '.$r['Region'].' (MID-'.$r['MID'].')',ENT_QUOTES,'UTF-8').'</option>';$q->close();
?>