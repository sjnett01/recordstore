<?php
require __DIR__.'/app-bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
$raw=file_get_contents('php://input');$data=json_decode($raw?:'',true);if(!is_array($data))$data=$_POST;
$trackId=filter_var($data['track_id']??0,FILTER_VALIDATE_INT);$event=(string)($data['event_type']??'');
if(!$trackId||!in_array($event,['play','complete','skip','seek'],true)){http_response_code(422);echo json_encode(['ok'=>false]);exit;}
$position=max(0,min(86400,(float)($data['position_seconds']??0)));$duration=max(0,min(86400,(float)($data['duration_seconds']??0)));$section=max(0,min(86400,(int)($data['section_seconds']??floor($position/10)*10)));
try{
  $st=$pdo->prepare("SELECT id FROM tracks WHERE id=? AND active=1 AND COALESCE(release_date,DATE(created_at))<=CURDATE() LIMIT 1");$st->execute([$trackId]);if(!$st->fetch()){http_response_code(404);echo json_encode(['ok'=>false]);exit;}
  $uid=empty($_SESSION['user_id'])?null:(int)$_SESSION['user_id'];$sid=session_id()?:null;$ip=$_SERVER['REMOTE_ADDR']??null;$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,512);
  $st=$pdo->prepare('INSERT INTO preview_events(track_id,user_id,session_id,event_type,position_seconds,duration_seconds,section_seconds,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,INET6_ATON(?),?)');$st->execute([$trackId,$uid,$sid,$event,$position?:null,$duration?:null,$section?:null,$ip,$ua]);
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){http_response_code(503);echo json_encode(['ok'=>false]);}
