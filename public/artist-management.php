<?php
require __DIR__.'/app-bootstrap.php';
$u=require_artist();
$error=''; $success='';

function managed_artist_ids(array $user): array {
    global $pdo;
    if(!empty($user['is_admin'])) return array_map('intval',$pdo->query('SELECT id FROM artists ORDER BY name')->fetchAll(PDO::FETCH_COLUMN));
    $st=$pdo->prepare('SELECT id,name,owner_user_id FROM artists WHERE owner_user_id=? OR (owner_user_id IS NULL AND LOWER(TRIM(name))=LOWER(TRIM(?))) ORDER BY name');
    $st->execute([(int)$user['id'],(string)$user['display_name']]);
    $artists=$st->fetchAll();
    foreach($artists as $artist){
        if(empty($artist['owner_user_id'])){
            $pdo->prepare('UPDATE artists SET owner_user_id=? WHERE id=? AND owner_user_id IS NULL')->execute([(int)$user['id'],(int)$artist['id']]);
        }
    }
    return array_map('intval',array_column($artists,'id'));
}

try{
    $artistIds=managed_artist_ids($u);
    if(!$artistIds && !empty($u['is_admin'])){
        $artistIds=array_map('intval',$pdo->query('SELECT id FROM artists ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        require_csrf();
        $action=(string)($_POST['action']??''); $artistId=(int)($_POST['artist_id']??0);
        if($action==='artist_profile_save' && ($artistId<=0 || !in_array($artistId,$artistIds,true))) throw new RuntimeException('You do not have permission to manage that artist.');
        if($action==='artist_profile_save'){
            $bio=trim((string)($_POST['bio']??''));
            if(mb_strlen($bio)>5000) throw new RuntimeException('The artist bio must be 5,000 characters or fewer.');
            $image=null;
            if(($_FILES['image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) $image=store_uploaded_image($_FILES['image'],'artists','artist-'.$artistId);
            if($image){
                $oldSt=$pdo->prepare('SELECT image_path FROM artists WHERE id=?');$oldSt->execute([$artistId]);$old=(string)($oldSt->fetchColumn()??'');
                $pdo->prepare('UPDATE artists SET bio=?,image_path=? WHERE id=?')->execute([$bio,$image,$artistId]);
                if($old && $old!==$image && function_exists('admin_unlink_artwork')) admin_unlink_artwork($old);
            }else{
                $pdo->prepare('UPDATE artists SET bio=? WHERE id=?')->execute([$bio,$artistId]);
            }
            $success='Artist profile updated.';
        }elseif($action==='artist_paypal_save'){
            $paypalEmail=strtolower(trim((string)($_POST['paypal_email']??'')));
            if($paypalEmail!==''&&!filter_var($paypalEmail,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid PayPal email address or leave it blank.');
            $pdo->prepare('UPDATE users SET paypal_email=?,paypal_email_updated_at=NOW() WHERE id=?')->execute([$paypalEmail!==''?$paypalEmail:null,(int)$u['id']]);
            $success='PayPal account saved for all of your artist aliases.';
        }elseif($action==='artist_track_status'){
            $trackId=(int)($_POST['track_id']??0);$active=(int)($_POST['active']??0)===1?1:0;
            $ownedPh=implode(',',array_fill(0,count($artistIds),'?'));
            $st=$pdo->prepare("SELECT t.id FROM tracks t JOIN track_artists ta ON ta.track_id=t.id WHERE t.id=? AND ta.artist_id IN ($ownedPh) LIMIT 1");$st->execute(array_merge([$trackId],$artistIds));
            if(!$st->fetch()) throw new RuntimeException('That track is not managed by this artist.');
            $pdo->prepare('UPDATE tracks SET active=?,is_featured=IF(?=0,0,is_featured) WHERE id=?')->execute([$active,$active,$trackId]);
            $success=$active?'Track returned to sale.':'Track removed from sale and hidden from the public catalogue.';
        }else{ throw new RuntimeException('Unknown management action.'); }
    }
    $artists=[];
    if($artistIds){$ph=implode(',',array_fill(0,count($artistIds),'?'));$st=$pdo->prepare("SELECT * FROM artists WHERE id IN ($ph) ORDER BY name");$st->execute($artistIds);$artists=$st->fetchAll();}
    $tracks=[];
    if($artistIds){
        $ph=implode(',',array_fill(0,count($artistIds),'?'));
        $sql="SELECT t.id,t.title,t.mix_name,t.release_date,t.active,t.price_pence,
          GROUP_CONCAT(DISTINCT all_a.name ORDER BY all_ta.sort_order,all_a.name SEPARATOR ' x ') artist_credit,
          COUNT(DISTINCT CASE WHEN o.status='paid' THEN oi.id END) sales
          FROM tracks t JOIN track_artists owned_ta ON owned_ta.track_id=t.id
          JOIN artists owned_a ON owned_a.id=owned_ta.artist_id AND owned_a.id IN ($ph)
          LEFT JOIN track_artists all_ta ON all_ta.track_id=t.id LEFT JOIN artists all_a ON all_a.id=all_ta.artist_id
          LEFT JOIN order_items oi ON oi.track_id=t.id LEFT JOIN orders o ON o.id=oi.order_id
          GROUP BY t.id ORDER BY COALESCE(t.release_date,'1000-01-01') DESC,t.id DESC";
        $st=$pdo->prepare($sql);$st->execute($artistIds);$tracks=$st->fetchAll();
    }
}catch(Throwable $e){$error=$e->getMessage();$artistIds=$artistIds??[];$artists=$artists??[];$tracks=$tracks??[];}

layout_header('Artist Management');
?>
<div class="section-title"><div><span class="kicker">ARTIST AREA</span><h1>Management</h1></div><a class="button" href="<?=e(url('artist-submit'))?>">＋ Submit a track</a></div>
<?php if($error):?><div class="panel admin-flash error"><?=e($error)?></div><?php endif;?><?php if($success):?><div class="panel admin-flash success"><?=e($success)?></div><?php endif;?>
<div class="artist-management">
  <section class="panel"><div class="section-title compact"><div><span class="kicker">CATALOGUE</span><h2>Your tracks</h2></div><span class="muted">Sales are paid order items</span></div>
  <?php if(!$tracks):?><p class="muted">No published tracks are linked to your artist profile yet.</p><?php else:?><div class="artist-track-table"><div class="artist-track-row artist-track-head"><strong>Artist(s)</strong><strong>Track</strong><strong>Release</strong><strong>Sales</strong><strong>Status</strong><strong></strong></div><?php foreach($tracks as $track):?><div class="artist-track-row"><span><?=e($track['artist_credit']?:'—')?></span><span><strong><?=e($track['title'])?></strong><?php if($track['mix_name']):?><small><?=e($track['mix_name'])?></small><?php endif;?></span><span><?=e($track['release_date']?:'—')?></span><span><?=e($track['sales'])?></span><span class="badge <?=((int)$track['active']===1)?'paid':'cancelled'?>"><?=((int)$track['active']===1)?'On sale':'Removed'?></span><form method="post"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="artist_track_status"><input type="hidden" name="artist_id" value="<?=e($artistIds[0]??0)?>"><input type="hidden" name="track_id" value="<?=e($track['id'])?>"><input type="hidden" name="active" value="<?=((int)$track['active']===1)?'0':'1'?>"><button class="button tiny <?=((int)$track['active']===1)?'danger':'secondary'?>"><?=((int)$track['active']===1)?'Remove from sale':'Return to sale'?></button></form></div><?php endforeach;?></div><?php endif;?></section>
  <section class="panel"><div class="section-title compact"><div><span class="kicker">PAYMENT DETAILS</span><h2>PayPal account</h2></div></div><p class="muted">This single PayPal address is shared by all of your artist aliases. We do not validate ownership; you are responsible for errors, typos and future changes.</p><form method="post" class="artist-profile-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="artist_paypal_save"><label>PayPal payment email<input type="email" name="paypal_email" value="<?=e((string)($u['paypal_email']??''))?>" placeholder="payments@example.com"></label><button class="button">Save PayPal account</button></form></section>
  <?php foreach($artists as $artist):?><section class="panel artist-profile-management"><div class="section-title compact"><div><span class="kicker">ARTIST PROFILE</span><h2><?=e($artist['name'])?></h2></div></div><form method="post" enctype="multipart/form-data" class="artist-profile-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="artist_profile_save"><input type="hidden" name="artist_id" value="<?=e($artist['id'])?>"><div class="artist-profile-form-grid"><div><img class="artist-avatar large" src="<?=e(artist_image_url($artist['image_path']))?>" alt=""><label>Artist image<input type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG or WebP; maximum 6 MB. Images are automatically constrained wherever displayed.</small></label></div><label>Bio<textarea name="bio" maxlength="5000" placeholder="Tell customers about this artist..."><?=e($artist['bio']??'')?></textarea><small>Maximum 5,000 characters.</small></label></div><button class="button">Save profile</button></form></section><?php endforeach;?>
</div>
<?php layout_footer();
