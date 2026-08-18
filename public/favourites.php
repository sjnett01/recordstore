<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();

if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    $trackId=(int)($_POST['track_id']??0);
    $action=(string)($_POST['action']??'toggle');
    $st=$pdo->prepare('SELECT id FROM tracks WHERE id=? AND active=1');$st->execute([$trackId]);
    if(!$st->fetchColumn()){http_response_code(404);$message='Track not found.';}
    else{
        $check=$pdo->prepare('SELECT 1 FROM user_favourites WHERE user_id=? AND track_id=?');$check->execute([(int)$u['id'],$trackId]);$already=(bool)$check->fetchColumn();
        $remove=$action==='remove'||($action==='toggle'&&$already);
        if($remove)$pdo->prepare('DELETE FROM user_favourites WHERE user_id=? AND track_id=?')->execute([(int)$u['id'],$trackId]);
        else $pdo->prepare('INSERT IGNORE INTO user_favourites(user_id,track_id) VALUES(?,?)')->execute([(int)$u['id'],$trackId]);
        $isFavourite=!$remove;        if(($_POST['ajax']??'')==='1'){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'favourite'=>$isFavourite,'count'=>(int)$pdo->query('SELECT COUNT(*) FROM user_favourites WHERE user_id='.(int)$u['id'])->fetchColumn(),'track_id'=>$trackId]);exit;}
    }
    redirect('favourites.php');
}

try{
    $st=$pdo->prepare("SELECT t.*,a.name artist_name,g.name genre_name,r.artwork_path release_artwork_path,t.artwork_path track_artwork_path,COALESCE(t.release_date,r.release_date,DATE(t.created_at)) effective_date,uf.created_at favourited_at FROM user_favourites uf JOIN tracks t ON t.id=uf.track_id JOIN artists a ON a.id=t.artist_id LEFT JOIN genres g ON g.id=t.genre_id LEFT JOIN releases r ON r.id=t.release_id WHERE uf.user_id=? AND t.active=1 ORDER BY uf.created_at DESC");
    $st->execute([(int)$u['id']]);$tracks=$st->fetchAll();$dbReady=true;
}catch(Throwable $ignored){$tracks=[];$dbReady=false;}
layout_header('Favourites');
?>
<div class="section-title"><div><span class="kicker">YOUR ACCOUNT</span><h1>Favourites</h1></div><span><?=count($tracks)?> saved</span></div>
<?php if(!$dbReady): ?><div class="panel empty-state"><h3>Favourites are unavailable</h3><p class="muted">The installed database schema does not contain the favourites table. Re-run the installer only against a new database.</p></div>
<?php elseif(!$tracks): ?><div class="panel empty-state"><h3>No favourites yet</h3><p class="muted">Save tracks with the heart button and they will appear here.</p><a class="button" href="new-tracks">Browse new tracks</a></div>
<?php else: ?><div class="cards favourites-cards"><?php foreach($tracks as $t) echo track_card($t); ?></div><?php endif; ?>
<?php layout_footer();
