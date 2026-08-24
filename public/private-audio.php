<?php
require __DIR__.'/app-bootstrap.php';
require_admin();
$id=(int)($_GET['submission']??0);
$st=$pdo->prepare('SELECT master_path,preview_path,mime_type FROM artist_submissions WHERE id=? AND status=\'pending\'');$st->execute([$id]);$row=$st->fetch();
if(!$row){http_response_code(404);exit('Submission not found.');}
$isPreview=isset($_GET['preview']);$root=realpath(rtrim($config['paths'][$isPreview?'previews':'masters'],'/'));$relative=$isPreview?(string)$row['preview_path']:(string)$row['master_path'];$path=$root?realpath($root.'/'.ltrim($relative,'/')):false;
if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('Audio unavailable.');}
$mime=(new finfo(FILEINFO_MIME_TYPE))->file($path)?:($row['mime_type']?:'audio/mpeg');$size=filesize($path);$start=0;$end=$size-1;if(isset($_SERVER['HTTP_RANGE'])&&preg_match('/bytes=(\d*)-(\d*)/',$_SERVER['HTTP_RANGE'],$m)){if($m[1]===''&&$m[2]!=='')$start=max(0,$size-(int)$m[2]);else$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}http_response_code(206);header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);}header('Content-Type: '.$mime);header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));header('Content-Disposition: inline; filename="submission-'.$id.'.'.pathinfo($path,PATHINFO_EXTENSION).'"');header('X-Content-Type-Options: nosniff');$handle=fopen($path,'rb');fseek($handle,$start);$remaining=$end-$start+1;while($remaining>0&&!feof($handle)){$chunk=fread($handle,min(8192,$remaining));if($chunk==='')break;echo $chunk;$remaining-=strlen($chunk);}fclose($handle);
