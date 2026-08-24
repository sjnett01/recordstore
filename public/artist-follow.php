<?php
require __DIR__.'/app-bootstrap.php';$u=require_login();if($_SERVER['REQUEST_METHOD']!=='POST'){redirect('artists');}require_csrf();$artistId=(int)($_POST['artist_id']??0);$follow=(int)($_POST['follow']??0)===1;
try{$st=$pdo->prepare('SELECT id FROM artists WHERE id=?');$st->execute([$artistId]);if(!$st->fetch())throw new RuntimeException('Artist not found.');if($follow)$pdo->prepare('INSERT IGNORE INTO artist_followers(user_id,artist_id) VALUES(?,?)')->execute([(int)$u['id'],$artistId]);else $pdo->prepare('DELETE FROM artist_followers WHERE user_id=? AND artist_id=?')->execute([(int)$u['id'],$artistId]);}catch(Throwable $e){$_SESSION['flash_error']=$e->getMessage();}
$back=(string)($_SERVER['HTTP_REFERER']??'artists');header('Location: '.preg_replace('/\s+/','',$back));exit;
