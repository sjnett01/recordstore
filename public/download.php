<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();
function afd_stream_download(array $ticket): never {
    $path=(string)$ticket['path'];if(!is_file($path)){http_response_code(404);exit('File unavailable.');}
    session_write_close();header('Content-Type: '.($ticket['mime_type']?:'application/octet-stream'));header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.str_replace('"','',(string)$ticket['file_name']).'"');header('Cache-Control: private, no-store');readfile($path);exit;
}
if(isset($_GET['ticket'])){
    $token=(string)$_GET['ticket'];$ticket=$_SESSION['download_tickets'][$token]??null;unset($_SESSION['download_tickets'][$token]);
    if(!$ticket || (int)($ticket['expires']??0)<time()){http_response_code(403);exit('Download ticket expired.');}
    afd_stream_download($ticket);
}
$item=(int)($_GET['item']??0);$pdo->beginTransaction();
$st=$pdo->prepare("SELECT oi.id,oi.download_limit,oi.downloads_used,t.master_path,t.file_name,t.mime_type FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN tracks t ON t.id=oi.track_id WHERE oi.id=? AND o.user_id=? AND o.status='paid' FOR UPDATE");$st->execute([$item,$u['id']]);$row=$st->fetch();
if(!$row||$row['downloads_used']>=$row['download_limit']){$pdo->rollBack();if(isset($_GET['ajax'])){header('Content-Type: application/json');http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Download limit reached.']);exit;}http_response_code(403);exit('Download not available.');}
$path=realpath($config['paths']['masters'].'/'.$row['master_path']);$root=realpath($config['paths']['masters']);if(!$path||!$root||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){$pdo->rollBack();http_response_code(404);exit('File unavailable.');}
$pdo->prepare('UPDATE order_items SET downloads_used=downloads_used+1 WHERE id=?')->execute([$item]);$pdo->prepare('INSERT INTO download_log(order_item_id,user_id,ip_address,user_agent) VALUES(?,?,INET6_ATON(?),?)')->execute([$item,$u['id'],$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,255)]);$pdo->commit();
$used=(int)$row['downloads_used']+1;$limit=(int)$row['download_limit'];$ticket=['path'=>$path,'file_name'=>$row['file_name'],'mime_type'=>$row['mime_type'],'expires'=>time()+120];
if(isset($_GET['ajax'])){$token=bin2hex(random_bytes(24));$_SESSION['download_tickets'][$token]=$ticket;header('Content-Type: application/json');echo json_encode(['ok'=>true,'used'=>$used,'limit'=>$limit,'download_url'=>url('download.php?ticket='.$token)],JSON_UNESCAPED_SLASHES);exit;}
afd_stream_download($ticket);
