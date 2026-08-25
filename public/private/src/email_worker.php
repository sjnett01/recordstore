<?php
declare(strict_types=1);

function worker_sent(string $email,string $subject):bool{global $pdo;$st=$pdo->prepare('SELECT id FROM mail_log WHERE recipient_email=? AND subject=? AND status=? AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) LIMIT 1');$st->execute([$email,$subject,'sent']);return (bool)$st->fetch();}
function worker_send(string $email,string $subject,string $body,string $template,?string $htmlBody=null):void{global $pdo;try{if(worker_sent($email,$subject))return;send_store_mail($email,$subject,$body,$htmlBody);try{$pdo->prepare('UPDATE mail_log SET template_key=? WHERE recipient_email=? AND subject=? AND status=? ORDER BY id DESC LIMIT 1')->execute([$template,$email,$subject,'sent']);}catch(Throwable $ignored){}}catch(Throwable $e){try{$pdo->prepare('INSERT INTO mail_log(recipient_email,template_key,subject,status,error_message) VALUES(?,?,?,?,?)')->execute([$email,$template,$subject,'failed',substr($e->getMessage(),0,500)]);}catch(Throwable $ignored){}}}
function run_email_worker(string $mode): int {
    global $pdo,$config;
    if($mode==='release'){
        $tracks=$pdo->query("SELECT t.id,t.title,a.name artist_name,COALESCE(t.release_date,DATE(t.created_at)) released FROM tracks t JOIN artists a ON a.id=t.artist_id WHERE t.active=1 AND COALESCE(t.release_date,DATE(t.created_at)) BETWEEN DATE_SUB(CURDATE(),INTERVAL 7 DAY) AND CURDATE() ORDER BY released DESC,t.id DESC")->fetchAll();
        if($tracks){$subs=$pdo->query('SELECT email FROM newsletter_subscribers WHERE confirmed_at IS NOT NULL AND unsubscribed_at IS NULL')->fetchAll();$lines=[];foreach($tracks as $t)$lines[]='- '.$t['artist_name'].' — '.$t['title'];foreach($subs as $sub){$mail=render_mail_template('new_releases',['customer_name'=>'there','release_list'=>implode("\n",$lines),'store_url'=>url('tracks'),'site_name'=>site_name()]);if($mail)worker_send((string)$sub['email'],$mail['subject'],$mail['body'],'new_releases',$mail['html_body']??null);}}
        return 0;
    }
    if($mode==='abandoned'){
        $rows=$pdo->query("SELECT c.id,c.user_id,u.email,u.display_name FROM cart_snapshots c JOIN users u ON u.id=c.user_id WHERE c.reminder_sent_at IS NULL AND c.updated_at<=DATE_SUB(NOW(),INTERVAL 24 HOUR) AND c.updated_at>=DATE_SUB(NOW(),INTERVAL 14 DAY) AND u.disabled_at IS NULL AND u.banned_at IS NULL")->fetchAll();
        foreach($rows as $row){$mail=render_mail_template('abandoned_cart',['customer_name'=>$row['display_name'],'cart_items'=>'Tracks saved in your basket','cart_url'=>url('cart.php'),'site_name'=>site_name()]);if($mail)worker_send((string)$row['email'],$mail['subject'],$mail['body'],'abandoned_cart',$mail['html_body']??null);$pdo->prepare('UPDATE cart_snapshots SET reminder_sent_at=NOW() WHERE id=?')->execute([(int)$row['id']]);}
        return 0;
    }
    return 1;
}