<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();
    if(isset($_POST['newsletter_opt_in'])){
        try{request_newsletter_subscription((string)$u['email'],(int)$u['id'],'checkout');}
        catch(Throwable $newsletterError){error_log('Checkout newsletter subscription failed: '.$newsletterError->getMessage());}
    }
}if(payment_gateway()!=='paypal'){http_response_code(503);exit('The selected payment gateway is not available yet.');}
try{
    $local=create_pending_order_from_cart((int)$u['id']);
    $pc=paypal_config();
    $body=[
      'intent'=>'CAPTURE',
      'purchase_units'=>[[
        'reference_id'=>'AFD-'.$local['id'],
        'description'=>site_name().' track purchase',
        'custom_id'=>(string)$local['id'],
        'amount'=>['currency_code'=>$pc['currency'],'value'=>paypal_money_value((int)$local['total_pence'])]
      ]],
      'payment_source'=>['paypal'=>['experience_context'=>[
        'brand_name'=>site_name(),'shipping_preference'=>'NO_SHIPPING','user_action'=>'PAY_NOW',
        'return_url'=>url('paypal-return.php'),'cancel_url'=>url('paypal-cancel.php?order='.$local['id'])
      ]]]
    ];
    $pp=paypal_request('POST','/v2/checkout/orders',$body,'afd-create-'.$local['id']);
    $paypalId=(string)($pp['id']??''); if($paypalId==='')throw new RuntimeException('PayPal did not return an order ID.');
    $pdo->prepare('UPDATE orders SET payment_reference=?,gateway_payload=? WHERE id=?')->execute([$paypalId,json_encode($pp,JSON_UNESCAPED_SLASHES),$local['id']]);
    $approve='';foreach(($pp['links']??[]) as $link){if(($link['rel']??'')==='payer-action'||($link['rel']??'')==='approve'){$approve=(string)$link['href'];break;}}
    if($approve==='')throw new RuntimeException('PayPal did not return an approval URL.');
    header('Location: '.$approve);exit;
}catch(Throwable $e){error_log(site_name() . ' checkout error: '.$e->getMessage());layout_header('Payment error');?><section class="panel"><h1>Payment could not start</h1><p><?=e($e->getMessage())?></p><a class="button" href="cart">Return to cart</a></section><?php layout_footer();}
