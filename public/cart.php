<?php
require __DIR__.'/app-bootstrap.php';

$discountFlash=null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='apply_discount') { require_csrf(); $_SESSION['discount_code']=discount_code_normalize((string)($_POST['discount_code']??'')); }
if (isset($_GET['clear_discount'])) unset($_SESSION['discount_code']);
if (isset($_GET['remove'])) {
    unset($_SESSION['cart'][(int)$_GET['remove']]);
}

$ids = array_keys(cart());
$tracks = [];
$total = 0;

if ($ids) {
    $ph = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT
        t.id,
        t.title,
        t.mix_name,
        t.bpm,
        t.price_pence,
        t.preview_path,
        t.artwork_path AS track_artwork_path,
        a.name AS artist_name,
        g.name AS genre_name,
        r.artwork_path AS release_artwork_path
      FROM tracks t
      JOIN artists a ON a.id=t.artist_id
      LEFT JOIN genres g ON g.id=t.genre_id
      LEFT JOIN releases r ON r.id=t.release_id
      WHERE t.id IN ($ph) AND t.active=1
      ORDER BY a.name, t.title");
    $st->execute($ids);
    $tracks = $st->fetchAll();
    foreach ($tracks as $t) $total += (int)$t['price_pence'];
}

$viewer=user();$discount=cart_discount_quote($total,$viewer?(int)$viewer['id']:null);
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='apply_discount'){$discountFlash=$discount;if(!$discount['ok']&&!empty($_SESSION['discount_code']))unset($_SESSION['discount_code']);}
try{if($viewer&&$ids){$pdo->prepare('INSERT INTO cart_snapshots(session_id,user_id,cart_json,updated_at,reminder_sent_at) VALUES(?,?,?,NOW(),NULL) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),cart_json=VALUES(cart_json),updated_at=NOW(),reminder_sent_at=NULL')->execute([session_id(),(int)$viewer['id'],json_encode(array_values(array_map('intval',$ids)))]);}}catch(Throwable $ignored){}layout_header('Cart');
?>
<div class="section-title"><div><span class="kicker">YOUR SELECTION</span><h1>Your cart</h1></div><span><?=count($tracks)?> track<?=count($tracks)===1?'':'s'?></span></div>
<section class="panel catalogue-track-table cart-track-table">
<?php if (!$tracks): ?>
  <div class="cart-empty"><strong>Your cart is empty.</strong><span>Add tracks from the catalogue and they will appear here.</span><a class="button" href="<?=e(url('tracks'))?>">Browse tracks</a></div>
<?php else: ?>
  <?php foreach ($tracks as $t):
    $art = track_artwork_url($t);
    $preview = track_preview_url($t);
  ?>
    <div class="catalogue-track-row cart-track-row">
      <button class="preview-fab play catalogue-play" data-preview="<?=e($preview)?>" data-title="<?=e($t['title'])?>" data-artist="<?=e($t['artist_name'])?>" data-art="<?=e($art)?>" aria-label="Preview <?=e($t['title'])?>">▶</button>
      <img class="catalogue-track-art" src="<?=e($art)?>" alt="">
      <div class="catalogue-track-identity">
        <div class="catalogue-track-line">
          <strong class="catalogue-track-title"><?=e($t['title'])?><?php if(!empty($t['mix_name']) && $t['mix_name'] !== 'Original Mix'):?> <span class="catalogue-mix">(<?=e($t['mix_name'])?>)</span><?php endif;?></strong>
          <span class="catalogue-separator">—</span>
          <span class="catalogue-artist"><?=e($t['artist_name'])?></span>
          <span class="catalogue-meta">
            <?php if(!empty($t['bpm'])):?><span class="catalogue-chip"><?=e($t['bpm'])?> BPM</span><?php endif;?>
            <?php if(!empty($t['genre_name'])):?><span class="catalogue-chip"><?=e($t['genre_name'])?></span><?php endif;?>
          </span>
        </div>
      </div>
      <strong class="catalogue-price"><?=money((int)$t['price_pence'])?></strong>
      <a class="button cart-remove" href="?remove=<?=$t['id']?>" aria-label="Remove <?=e($t['title'])?> from cart">Remove</a>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</section>
<?php if ($tracks): ?>
  <section class="panel discount-panel"><div><span class="kicker">PROMOTION</span><h2>Have a discount code?</h2><small class="muted"><?php if(empty($_SESSION['discount_code'])&&$discount['ok']):?>An automatic offer has been applied.<?php else:?>Enter a code to check for a customer offer.<?php endif;?></small></div><form method="post" class="discount-form"><input type="text" name="discount_code" value="<?=e((string)($_SESSION['discount_code']??''))?>" placeholder="Enter code" maxlength="64"><input type="hidden" name="action" value="apply_discount"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button secondary">Apply code</button><?php if(!empty($_SESSION['discount_code'])):?><a class="button secondary" href="<?=e(url('cart.php?clear_discount=1'))?>">Clear</a><?php endif;?></form><?php if($discountFlash):?><p class="<?=!empty($discountFlash['ok'])?'success':'error'?>"><?=e($discountFlash['message'])?></p><?php endif;?></section>
  <div class="cart-summary panel"><div class="cart-pricing"><span>Subtotal</span><strong><?=money($total)?></strong><?php if($discount['ok']&&$discount['discount_pence']>0):?><span>Discount (<?=e($discount['code'])?>)</span><strong class="discount-value">−<?=money((int)$discount['discount_pence'])?></strong><?php endif;?><span>Total</span><strong><?=money((int)$discount['total_pence'])?></strong></div><?php $pm=paypal_mode(); ?><a class="button" href="checkout">Pay with PayPal<?php if($pm==='sandbox'):?> · TEST<?php endif;?></a></div>
  <?php if($pm==='sandbox'):?><p class="payment-test-banner"><strong>PayPal Sandbox test mode</strong> No real money will be charged.</p><?php endif;?>
<?php endif; ?>
<?php layout_footer();
