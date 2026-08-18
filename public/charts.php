<?php
require __DIR__.'/app-bootstrap.php';
$period=$_GET['period']??'30';$days=in_array($period,['7','30','365','all'],true)?$period:'30';
$genreId=(int)($_GET['genre']??0);
$genreSlug=slugify((string)($_GET['genre_slug']??''));if($genreSlug!=='item'){$stSlug=$pdo->prepare('SELECT id FROM genres WHERE slug=?');$stSlug->execute([$genreSlug]);$genreId=(int)($stSlug->fetchColumn()?:0);}
$genres=$pdo->query('SELECT id,name,slug FROM genres ORDER BY name')->fetchAll();
$genreName='Overall';$genreSlug='';foreach($genres as $g){if((int)$g['id']===$genreId){$genreName=$g['name'];$genreSlug=$g['slug'];break;}}
$params=[];$where=["o.status='paid'"];
if($days!=='all'){$where[]='o.paid_at >= DATE_SUB(NOW(), INTERVAL '.(int)$days.' DAY)';}
if($genreId>0){$where[]='t.genre_id=?';$params[]=$genreId;}
$sql="SELECT t.id,t.title,t.mix_name,t.price_pence,t.bpm,t.preview_path,t.artwork_path track_artwork_path,r.artwork_path release_artwork_path,a.name artist_name,g.name genre_name,COUNT(oi.id) units,SUM(oi.unit_price_pence) revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id JOIN tracks t ON t.id=oi.track_id JOIN artists a ON a.id=t.artist_id LEFT JOIN releases r ON r.id=t.release_id LEFT JOIN genres g ON g.id=t.genre_id WHERE ".implode(' AND ',$where)." GROUP BY t.id ORDER BY units DESC,revenue DESC,t.title LIMIT 100";
$st=$pdo->prepare($sql);$st->execute($params);$tracks=$st->fetchAll();
layout_header($genreName.' Chart');?>
<div class="section-title"><div><span class="kicker">TOP TRACKS</span><h1><?=e($genreName)?> Chart</h1></div><div class="chart-periods"><a href="<?=e(url('charts/'.rawurlencode($genreSlug)))?>?period=7">7 days</a><a href="<?=e(url('charts/'.rawurlencode($genreSlug)))?>?period=30">30 days</a><a href="<?=e(url('charts/'.rawurlencode($genreSlug)))?>?period=365">Year</a><a href="<?=e(url('charts/'.rawurlencode($genreSlug)))?>?period=all">All time</a></div></div>
<nav class="genre-tabs"><a class="<?=$genreId===0?'active':''?>" href="?period=<?=e($days)?>">Overall</a><?php foreach($genres as $g):?><a class="<?=$genreId===(int)$g['id']?'active':''?>" href="<?=e(url('charts/'.rawurlencode($g['slug'])))?>?period=<?=e($days)?>"><?=e($g['name'])?></a><?php endforeach;?></nav>
<div class="panel track-table chart-track-table"><?php foreach($tracks as $i=>$t):$art=track_artwork_url($t);?><div class="track-row chart-track-row"><span class="rank"><?=($i+1)?></span><button class="play round" data-preview="<?=e(track_preview_url($t))?>" data-title="<?=e($t['title'])?>" data-artist="<?=e($t['artist_name'])?>" data-art="<?=e($art)?>">▶</button><img class="track-thumb" src="<?=e($art)?>" alt=""><div class="track-name"><strong><?=e($t['title'])?></strong><small><?=e($t['artist_name'])?><?=$t['mix_name']?' · '.e($t['mix_name']):''?></small></div><span class="chart-track-genre"><?=e($t['genre_name']??'')?></span><strong><?=money((int)$t['price_pence'])?></strong><a class="button tiny" href="<?=e(track_public_url($t))?>">View</a></div><?php endforeach;?><?php if(!$tracks):?><p class="muted">No chart data in this period yet.</p><?php endif;?></div>
<?php layout_footer();
