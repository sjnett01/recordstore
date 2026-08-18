<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();
if(empty($config['app']['demo_checkout'])){http_response_code(403);exit('Demo checkout is disabled. Enable app.demo_checkout in private config only for testing.');}
$ids=array_map('intval',array_keys(cart()));if(!$ids)redirect('cart.php');
$ph=implode(',',array_fill(0,count($ids),'?'));$st=$pdo->prepare("SELECT id,price_pence FROM tracks WHERE id IN ($ph) AND active=1");$st->execute($ids);$tracks=$st->fetchAll();$subtotal=array_sum(array_map(fn($t)=>(int)$t['price_pence'],$tracks));
$discount=cart_discount_quote($subtotal,(int)$u['id']);if(!empty($_SESSION['discount_code'])&&!$discount['ok'])throw new RuntimeException($discount['message']);$discountCode=$discount['ok']?$discount['code']:null;$discountPence=(int)$discount['discount_pence'];$total=$discount['total_pence'];
$pdo->beginTransaction();
try{
    if($discountCode){$lock=$pdo->prepare('UPDATE discount_codes SET used_count=used_count+1 WHERE code=? AND active=1 AND (usage_limit IS NULL OR used_count<usage_limit)');$lock->execute([$discountCode]);if($lock->rowCount()!==1)throw new RuntimeException('That discount code is no longer available.');}
    $st=$pdo->prepare("INSERT INTO orders(user_id,status,payment_provider,payment_reference,subtotal_pence,discount_code,discount_pence,total_pence,paid_at) VALUES(?,'paid','demo',?,?,?,?,?,NOW())");$ref='DEMO-'.bin2hex(random_bytes(5));$st->execute([$u['id'],$ref,$subtotal,$discountCode,$discountPence,$total]);$orderId=(int)$pdo->lastInsertId();
    $st=$pdo->prepare('INSERT INTO order_items(order_id,track_id,unit_price_pence,download_limit) VALUES(?,?,?,?)');foreach($tracks as $t)$st->execute([$orderId,$t['id'],$t['price_pence'],$config['app']['download_limit_default']]);
    $pdo->commit();$_SESSION['cart']=[];unset($_SESSION['discount_code']);redirect('account.php');
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
