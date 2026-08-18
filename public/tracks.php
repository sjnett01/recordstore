<?php
require __DIR__.'/app-bootstrap.php';

$q=trim((string)($_GET['q']??''));
$genreId=(int)($_GET['genre']??0);
$genreSlug=slugify((string)($_GET['genre_slug']??''));
if($genreSlug!=='item'){$stSlug=$pdo->prepare('SELECT id FROM genres WHERE slug=?');$stSlug->execute([$genreSlug]);$genreId=(int)($stSlug->fetchColumn()?:0);}
$artistId=(int)($_GET['artist']??0);
$mix=trim((string)($_GET['mix']??''));
$bpmMin=max(0,min(300,(int)($_GET['bpm_min']??0)));
$bpmMax=max(0,min(300,(int)($_GET['bpm_max']??0)));
$dateFrom=(string)($_GET['date_from']??'');
$dateTo=(string)($_GET['date_to']??'');
$priceMinRaw=trim((string)($_GET['price_min']??''));
$priceMaxRaw=trim((string)($_GET['price_max']??''));
$priceMin=is_numeric($priceMinRaw)?max(0,(int)round((float)$priceMinRaw*100)):null;
$priceMax=is_numeric($priceMaxRaw)?max(0,(int)round((float)$priceMaxRaw*100)):null;
$effective='COALESCE(t.release_date,r.release_date,DATE(t.created_at))';
$mixExpr="COALESCE(NULLIF(TRIM(t.mix_name),''),'Original Mix')";
$params=[];$where=['t.active=1',$effective.' <= CURDATE()'];
if($q!==''){$where[]='(t.title LIKE ? OR t.mix_name LIKE ? OR a.name LIKE ? OR r.title LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like);}
if($genreId>0){$where[]='t.genre_id=?';$params[]=$genreId;}
if($artistId>0){$where[]='t.artist_id=?';$params[]=$artistId;}
if($mix!==''){$where[]=$mixExpr.'=?';$params[]=$mix;}
if($bpmMin>0){$where[]='t.bpm>=?';$params[]=$bpmMin;}
if($bpmMax>0){$where[]='t.bpm<=?';$params[]=$bpmMax;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateFrom)){$where[]=$effective.'>=?';$params[]=$dateFrom;}
if(preg_match('/^\d{4}-\d{2}-\d{2}$/',$dateTo)){$where[]=$effective.'<=?';$params[]=$dateTo;}
if($priceMin!==null){$where[]='t.price_pence>=?';$params[]=$priceMin;}
if($priceMax!==null){$where[]='t.price_pence<=?';$params[]=$priceMax;}
$sql="SELECT t.*,a.name artist_name,g.name genre_name,g.slug genre_slug,r.title release_title,r.artwork_path release_artwork_path,t.artwork_path track_artwork_path,$effective effective_date FROM tracks t JOIN artists a ON a.id=t.artist_id LEFT JOIN releases r ON r.id=t.release_id LEFT JOIN genres g ON g.id=t.genre_id WHERE ".implode(' AND ',$where)." ORDER BY effective_date DESC,t.id DESC LIMIT 200";
$st=$pdo->prepare($sql);$st->execute($params);$tracks=$st->fetchAll();
$genres=$pdo->query('SELECT id,name FROM genres ORDER BY name')->fetchAll();
$artists=$pdo->query('SELECT id,name FROM artists ORDER BY name')->fetchAll();
$mixes=$pdo->query("SELECT DISTINCT $mixExpr mix_name FROM tracks t WHERE t.active=1 ORDER BY mix_name")->fetchAll();
$hasFilters=$q!==''||$genreId>0||$artistId>0||$mix!==''||$bpmMin>0||$bpmMax>0||$dateFrom!==''||$dateTo!==''||$priceMinRaw!==''||$priceMaxRaw!=='';
layout_header($hasFilters?'Filtered tracks':($q!==''?'Search':'Tracks'));
?>
<div class="section-title"><div><span class="kicker"><?=$hasFilters?'DISCOVERY':($q!==''?'SEARCH RESULTS':'CATALOGUE')?></span><h1><?=$q!==''?'Search: '.e($q):'All Tracks'?></h1></div><span><?=count($tracks)?> found</span></div>
<form class="catalogue-filter panel" method="get">
  <label>Search<input name="q" value="<?=e($q)?>" placeholder="Artist or track name"></label>
  <label>Genre<select name="genre"><option value="">All genres</option><?php foreach($genres as $g):?><option value="<?=$g['id']?>" <?=$genreId===(int)$g['id']?'selected':''?>><?=e($g['name'])?></option><?php endforeach;?></select></label>
  <label>Artist<select name="artist"><option value="">All artists</option><?php foreach($artists as $a):?><option value="<?=$a['id']?>" <?=$artistId===(int)$a['id']?'selected':''?>><?=e($a['name'])?></option><?php endforeach;?></select></label>
  <label>Mix type<select name="mix"><option value="">All mix types</option><?php foreach($mixes as $m):?><option value="<?=e($m['mix_name'])?>" <?=$mix===$m['mix_name']?'selected':''?>><?=e($m['mix_name'])?></option><?php endforeach;?></select></label>
  <label>BPM minimum<input name="bpm_min" type="number" min="0" max="300" step="1" value="<?=$bpmMin?:''?>" placeholder="Min"></label>
  <label>BPM maximum<input name="bpm_max" type="number" min="0" max="300" step="1" value="<?=$bpmMax?:''?>" placeholder="Max"></label>
  <label>Release from<input name="date_from" type="date" value="<?=e($dateFrom)?>"></label>
  <label>Release to<input name="date_to" type="date" value="<?=e($dateTo)?>"></label>
  <label>Price minimum (£)<input name="price_min" type="number" min="0" step="0.01" value="<?=e($priceMinRaw)?>" placeholder="0.00"></label>
  <label>Price maximum (£)<input name="price_max" type="number" min="0" step="0.01" value="<?=e($priceMaxRaw)?>" placeholder="99.99"></label>
  <div class="catalogue-filter-actions"><button class="button">Apply filters</button><?php if($hasFilters):?><a class="button secondary" href="<?=e(url('tracks'))?>">Clear</a><?php endif;?></div>
</form>
<div class="panel catalogue-track-table">
<?php foreach($tracks as $t):$art=track_artwork_url($t);?><div class="catalogue-track-row"><button class="play round catalogue-play" data-preview="<?=e(track_preview_url($t))?>" data-title="<?=e($t['title'])?>" data-artist="<?=e($t['artist_name'])?>" data-art="<?=e($art)?>" aria-label="Preview <?=e($t['title'])?>">▶</button><img class="catalogue-track-art" src="<?=e($art)?>" alt=""><div class="catalogue-track-identity"><div class="catalogue-track-line"><a class="catalogue-track-title" href="<?=e(track_public_url($t))?>"><?=e($t['title'])?><?=$t['mix_name']?' <span class="catalogue-mix">('.e($t['mix_name']).')</span>':''?></a><span class="catalogue-separator">–</span><span class="catalogue-artist"><?=e($t['artist_name'])?></span><span class="catalogue-separator meta-separator">–</span><span class="catalogue-meta"><?php if($t['bpm']):?><span class="catalogue-chip"><?=e((string)$t['bpm'])?> BPM</span><?php endif;?><?php if(!empty($t['genre_name'])):?><span class="catalogue-chip"><?=e($t['genre_name'])?></span><?php endif;?></span></div></div><strong class="catalogue-price"><?=money((int)$t['price_pence'])?></strong><div class="catalogue-actions"><?php if(user()):?><form method="post" action="<?=e(url('favourites.php'))?>" class="favourite-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="track_id" value="<?=$t['id']?>"><input type="hidden" name="ajax" value="1"><button type="submit" class="square-link favourite-toggle <?=is_track_favourite((int)$t['id'])?'is-favourite':''?>" aria-label="<?=is_track_favourite((int)$t['id'])?'Remove':'Save'?> <?=e($t['title'])?> <?=is_track_favourite((int)$t['id'])?'from':'to'?> favourites" title="<?=is_track_favourite((int)$t['id'])?'Remove from':'Save to'?> favourites"><?=is_track_favourite((int)$t['id'])?'♥':'♡'?></button></form><?php else: ?><a class="square-link favourite-toggle" href="<?=e(url('login.php?next='.rawurlencode('tracks/'.track_public_slug($t)).'&favourite='.(int)$t['id']))?>" aria-label="Log in to save <?=e($t['title'])?>" title="Log in to save">♡</a><?php endif;?><form method="post" action="<?=e(track_public_url($t))?>" class="quick-add-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="ajax" value="1"><button type="submit" class="button tiny quick-add-button" aria-label="Add <?=e($t['title'])?> to cart" title="Add to cart">🛒</button></form><a class="square-link info-link" href="<?=e(track_public_url($t))?>">i</a></div></div><?php endforeach;?><?php if(!$tracks):?><p class="muted">No matching tracks. Try widening your filters.</p><?php endif;?></div>
<?php layout_footer();