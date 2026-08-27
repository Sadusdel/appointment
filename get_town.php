<?php
session_start();
if(!isset($_SESSION['user'])&&!isset($_SESSION['username'])){http_response_code(403);exit;}
require_once 'dbconfig.php';
$city=trim($_POST['countryid']??'');
echo '<option value="">İlçe seçin</option>';
if($city==='')exit;
$stmt=$conn->prepare('SELECT DISTINCT Town FROM clinic WHERE City=? ORDER BY Town');
$stmt->bind_param('s',$city);$stmt->execute();$result=$stmt->get_result();
while($row=$result->fetch_assoc()){echo '<option value="'.htmlspecialchars($row['Town'],ENT_QUOTES,'UTF-8').'">'.htmlspecialchars($row['Town'],ENT_QUOTES,'UTF-8').'</option>';}
$stmt->close();
?>