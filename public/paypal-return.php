<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();
$paypalId=trim((string)($_GET['token']??''));
if($paypalId===''){http_response_code(400);exit('Missing PayPal order token.');}
$st=$pdo->prepare("SELECT * FROM orders WHERE user_id=? AND payment_provider='paypal' AND payment_reference=? LIMIT 1");$st->execute([$u['id'],$paypalId]);$order=$st->fetch();
if(!$order){http_response_code(404);exit('Order not found.');}
if(in_array((string)$order['status'],['cancelled','refunded'],true)){http_response_code(409);exit('This order has been closed and cannot be captured.');}
if($order['status']==='paid'){$_SESSION['cart']=[];redirect('account.php');}
try{
    $resp=paypal_request('POST','/v2/checkout/orders/'.rawurlencode($paypalId).'/capture',null, 'afd-capture-'.$order['id']);
    $d=paypal_capture_details($resp);$pc=paypal_config();
    if($d['status']!=='COMPLETED'||$d['id']==='')throw new RuntimeException('PayPal capture did not complete.');
    if($d['currency']!==$pc['currency']||$d['value']!==paypal_money_value((int)$order['total_pence']))throw new RuntimeException('PayPal payment amount did not match the RecordStore order.');
    mark_order_paid_from_paypal((int)$order['id'],$paypalId,$d['id'],$resp);$_SESSION['cart']=[];$_SESSION['payment_flash']='Payment completed successfully.';redirect('account.php');
}catch(Throwable $e){error_log('PayPal capture error: '.$e->getMessage());layout_header('Payment status');?><section class="panel"><h1>We could not confirm your payment</h1><p>Your downloads have not been unlocked. Please do not pay again yet.</p><p class="muted"><?=e($e->getMessage())?></p><a class="button" href="account">My account</a></section><?php layout_footer();}
