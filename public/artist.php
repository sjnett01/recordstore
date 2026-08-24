<?php
require __DIR__.'/app-bootstrap.php';

$slug=trim((string)($_GET['slug']??''));$id=(int)($_GET['id']??0);
if($slug!==''){$st=$pdo->prepare('SELECT * FROM artists WHERE slug=?');$st->execute([$slug]);$artist=$st->fetch();if(!$artist){$st=$pdo->prepare("SELECT * FROM artists WHERE slug LIKE CONCAT(?,'-%') ORDER BY id LIMIT 1");$st->execute([$slug]);$artist=$st->fetch();}}
else{$st=$pdo->prepare('SELECT * FROM artists WHERE id=?');$st->execute([$id]);$artist=$st->fetch();}
if(!$artist){http_response_code(404);exit('Artist not found');}
$canonical=artist_public_slug($artist);
if(($slug!==''&&$slug!==$canonical)||($slug===''&&$id)){header('Location: '.url('artists/'.rawurlencode($canonical)),true,301);exit;}
$id=(int)$artist['id'];

$sort=(string)($_GET['sort']??'release');
$sortSql=match($sort){'title'=>'t.title ASC,t.id DESC','sales'=>'(SELECT COUNT(*) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.track_id=t.id AND o.status=\'paid\') DESC,t.title ASC','release'=>'COALESCE(t.release_date,r.release_date,DATE(t.created_at)) DESC,t.id DESC',default=>'COALESCE(t.release_date,r.release_date,DATE(t.created_at)) DESC,t.id DESC'};

$st=$pdo->prepare("SELECT t.*,a.name artist_name,r.artwork_path release_artwork_path,t.artwork_path track_artwork_path,g.name genre_name FROM tracks t JOIN artists a ON a.id=t.artist_id LEFT JOIN releases r ON r.id=t.release_id LEFT JOIN genres g ON g.id=t.genre_id WHERE EXISTS (SELECT 1 FROM track_artists ta WHERE ta.track_id=t.id AND ta.artist_id=?) AND t.active=1 AND COALESCE(t.release_date,r.release_date,DATE(t.created_at))<=CURDATE() ORDER BY {$sortSql}");
$st->execute([$id]);$tracks=$st->fetchAll();
$releases=[];$genres=[];
try{$st=$pdo->prepare("SELECT r.*,COUNT(t.id) track_count FROM releases r LEFT JOIN tracks t ON t.release_id=r.id AND t.active=1 WHERE r.artist_id=? GROUP BY r.id ORDER BY r.release_date DESC,r.id DESC");$st->execute([$id]);$releases=$st->fetchAll();}catch(Throwable $ignored){}
try{$st=$pdo->prepare("SELECT DISTINCT g.name,g.slug FROM tracks t JOIN genres g ON g.id=t.genre_id JOIN track_artists ta ON ta.track_id=t.id AND ta.artist_id=? WHERE t.active=1 ORDER BY g.name");$st->execute([$id]);$genres=$st->fetchAll();}catch(Throwable $ignored){}
$socials=[['website_url','Website','↗'],['instagram_url','Instagram','◎'],['soundcloud_url','SoundCloud','◉'],['youtube_url','YouTube','▶']];
$GLOBALS['page_meta']=['description'=>$artist['name'].' music catalogue on '.site_name(),'image'=>artist_image_url($artist['image_path']),'image_alt'=>$artist['name'].' artist artwork','image_type'=>'image/jpeg','type'=>'profile','structured'=>['@context'=>'https://schema.org','@type'=>'MusicGroup','name'=>$artist['name'],'url'=>url('artists/'.rawurlencode($canonical)),'image'=>artist_image_url($artist['image_path'])]];
$viewer=user();$following=false;
if($viewer){try{$st=$pdo->prepare('SELECT 1 FROM artist_followers WHERE user_id=? AND artist_id=?');$st->execute([(int)$viewer['id'],$id]);$following=(bool)$st->fetchColumn();}catch(Throwable $ignored){}}

layout_header($artist['name']);
?>
<section class="panel artist-profile<?=!empty($artist['banner_image_path'])?' has-banner':''?>"<?=!empty($artist['banner_image_path'])?' style="--artist-banner:url(\''.e(artist_image_url($artist['banner_image_path'])).'\')"':''?>>
  <img class="artist-avatar large" src="<?=e(artist_image_url($artist['image_path']))?>" alt="">
  <div>
    <span class="kicker">ARTIST PROFILE</span>
    <h1><?=e($artist['name'])?> <?php if(!empty($artist['is_verified'])):?><span class="verified-badge" title="Verified artist" aria-label="Verified artist">✓</span><?php endif;?></h1>
    <?php if(trim((string)$artist['bio'])!==''):?><p><?=nl2br(e($artist['bio']))?></p><?php endif;?>
    <div class="artist-socials">
      <?php foreach($socials as $social):$key=$social[0];$label=$social[1];$icon=$social[2];if(array_key_exists($key,$artist)&&filter_var($artist[$key],FILTER_VALIDATE_URL)):?>
        <a href="<?=e($artist[$key])?>" target="_blank" rel="noopener noreferrer"><span><?=e($icon)?></span><?=e($label)?></a>
      <?php endif;endforeach;?>
    </div>
    <?php if($genres):?><div class="artist-genres"><?php foreach($genres as $g):?><a href="<?=e(url('genres/'.rawurlencode($g['slug'])))?>"><?=e($g['name'])?></a><?php endforeach;?></div><?php endif;?>
    <div class="artist-profile-actions"><a class="button secondary" href="?sort=title">A–Z catalogue</a><a class="button secondary" href="?sort=sales">Best sellers</a><?php if($viewer):?><form method="post" action="<?=e(url('artist-follow.php'))?>"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="artist_id" value="<?=$id?>"><input type="hidden" name="follow" value="<?=$following?'0':'1'?>"><button class="button secondary" type="submit"><?=$following?'Following':'Follow artist'?></button></form><?php endif;?></div>
  </div>
</section>
<?php if($releases):?>
<div class="section-title"><h2>Latest releases</h2><span><?=count($releases)?> release<?=count($releases)===1?'':'s'?></span></div>
<div class="release-admin-list artist-release-list">
<?php foreach($releases as $r):?>
<a class="panel artist-release-card" href="<?=e(url('tracks?release='.(int)$r['id']))?>"><img src="<?=e(artwork_url($r['artwork_path']))?>" alt=""><span><strong><?=e($r['title'])?></strong><small><?=e($r['release_date'])?> · <?=e((string)$r['track_count'])?> tracks</small></span></a>
<?php endforeach;?>
</div>
<?php endif;?>
<div class="section-title"><h2>Tracks</h2><span><?=count($tracks)?> available</span></div>
<div class="cards"><?php foreach($tracks as $t){echo track_card($t);}?></div>
<?php if(!$tracks):?><p class="muted">No active tracks for this artist yet.</p><?php endif;?>
<?php layout_footer();
