<?php
require __DIR__.'/app-bootstrap.php';
$u=user();$message=null;$error=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{require_csrf();$message=request_newsletter_subscription((string)($_POST['email']??''),$u?(int)$u['id']:null,'site');}
    catch(Throwable $e){$error=true;$message=$e->getMessage();}
}
if(isset($_GET['confirm'])){$hash=hash('sha256',(string)$_GET['confirm']);$st=$pdo->prepare('UPDATE newsletter_subscribers SET confirmed_at=NOW(),confirmation_hash=NULL,unsubscribed_at=NULL WHERE confirmation_hash=?');$st->execute([$hash]);$message=$st->rowCount()?'Your release updates subscription is confirmed.':'That confirmation link is invalid or has expired.';$error=$st->rowCount()===0;}
if(isset($_GET['unsubscribe'])){$hash=hash('sha256',(string)$_GET['unsubscribe']);$st=$pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at=NOW() WHERE confirmation_hash=?');$st->execute([$hash]);$message=$st->rowCount()?'You have been unsubscribed.':'That unsubscribe link is invalid or has expired.';$error=$st->rowCount()===0;}
layout_header('Release updates');
?>
<section class="panel newsletter-page"><span class="kicker">STAY IN THE LOOP</span><h1>Release updates</h1><p class="muted">Get occasional announcements when new music is released. You can unsubscribe at any time.</p><?php if($message):?><div class="<?= $error?'error':'success'?>"><?=e($message)?></div><?php endif;?><form method="post" class="newsletter-form"><label>Email address<input type="email" name="email" value="<?=e($u['email']??'')?>" required></label><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button">Subscribe</button></form></section>
<?php layout_footer();