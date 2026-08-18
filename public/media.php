<?php require __DIR__.'/app-bootstrap.php';
if(($_GET['type']??'')!=='artwork'){http_response_code(404);exit;}
$f=str_replace('\\','/',(string)($_GET['f']??''));
if($f==='' || str_contains($f,'..') || str_starts_with($f,'/')){http_response_code(400);exit;}
$root=realpath($config['paths']['artwork']);$path=$root?realpath($root.'/'.$f):false;
if(!$path||!$root||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit;}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:'application/octet-stream';
if(!str_starts_with($mime,'image/')){http_response_code(415);exit;}
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: public, max-age=86400');readfile($path);exit;
