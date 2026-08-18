<?php
require __DIR__.'/app-bootstrap.php';
$u=user();$message=null;$error=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        require_csrf();$email=strtolower(trim((string)($_POST['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
        $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
        $st=$pdo->prepare('SELECT id,confirmed_at,unsubscribed_at FROM newsletter_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$existing=$st->fetch();
        if($existing&&$existing['confirmed_at']&&!$existing['unsubscribed_at']){$message='You are already subscribed to release updates.';}
        else{
            if($existing)$pdo->prepare('UPDATE newsletter_subscribers SET user_id=?,confirmation_hash=?,confirmed_at=NULL,unsubscribed_at=NULL,source=? WHERE id=?')->execute([$u?(int)$u['id']:null,$hash,'site',(int)$existing['id']]);
            else $pdo->prepare('INSERT INTO newsletter_subscribers(user_id,email,confirmation_hash,source) VALUES(?,?,?,?)')->execute([$u?(int)$u['id']:null,$email,$hash,'site']);
            try{send_store_mail($email,'Confirm RecordStore release updates','Please confirm your subscription by visiting: '.url('newsletter.php?confirm='.$token)."\n\nIf you did not request this, you can ignore this email.");}catch(Throwable $mailError){error_log('Newsletter confirmation email failed: '.$mailError->getMessage());}
            $message='Check your inbox to confirm your release updates subscription.';
        }
    }catch(Throwable $e){$error=true;$message=$e->getMessage();}
}
if(isset($_GET['confirm'])){
    $hash=hash('sha256',(string)$_GET['confirm']);$st=$pdo->prepare('UPDATE newsletter_subscribers SET confirmed_at=NOW(),confirmation_hash=NULL,unsubscribed_at=NULL WHERE confirmation_hash=?');$st->execute([$hash]);$message=$st->rowCount()?'Your release updates subscription is confirmed.':'That confirmation link is invalid or has expired.';$error=$st->rowCount()===0;
}
if(isset($_GET['unsubscribe'])){
    $hash=hash('sha256',(string)$_GET['unsubscribe']);$st=$pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at=NOW() WHERE confirmation_hash=?');$st->execute([$hash]);$message=$st->rowCount()?'You have been unsubscribed.':'That unsubscribe link is invalid or has expired.';$error=$st->rowCount()===0;
}
layout_header('Release updates');
?>
<section class="panel newsletter-page"><span class="kicker">STAY IN THE LOOP</span><h1>Release updates</h1><p class="muted">Get occasional announcements when new RecordStore music is released. You can unsubscribe at any time.</p><?php if($message):?><div class="<?= $error?'error':'success'?>"><?=e($message)?></div><?php endif;?><form method="post" class="newsletter-form"><label>Email address<input type="email" name="email" value="<?=e($u['email']??'')?>" required></label><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button">Subscribe</button></form></section>
<?php layout_footer();
