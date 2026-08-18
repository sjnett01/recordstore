<?php
require __DIR__.'/app-bootstrap.php';
$raw=file_get_contents('php://input')?:'';$event=json_decode($raw,true);
if(!is_array($event)){http_response_code(400);exit;}
$pc=paypal_config();
$eventId=(string)($event['id']??'');$eventType=(string)($event['event_type']??'');
try{
    if($pc['webhook_id']===''||str_starts_with($pc['webhook_id'],'REPLACE_'))throw new RuntimeException('PayPal webhook ID is not configured.');
    $ok=paypal_verify_webhook_raw($raw,[
      'auth_algo'=>(string)($_SERVER['HTTP_PAYPAL_AUTH_ALGO']??''),
      'cert_url'=>(string)($_SERVER['HTTP_PAYPAL_CERT_URL']??''),
      'transmission_id'=>(string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID']??''),
      'transmission_sig'=>(string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']??''),
      'transmission_time'=>(string)($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME']??'')
    ]);
    $verified=$ok?'SUCCESS':'FAILURE';
$webhookLog=$pdo->prepare('INSERT IGNORE INTO payment_webhook_log(provider,event_id,event_type,verification_status,payload_json) VALUES(?,?,?,?,?)');$webhookLog->execute(['paypal',$eventId,$eventType,$verified,$raw]);if($eventId!==''&&$webhookLog->rowCount()===0){http_response_code(200);echo 'OK';exit;}
    if($verified!=='SUCCESS'){http_response_code(400);exit;}
    if($eventType==='PAYMENT.CAPTURE.COMPLETED'){
        $resource=$event['resource']??[];$captureId=(string)($resource['id']??'');$paypalOrderId=(string)($resource['supplementary_data']['related_ids']['order_id']??'');
        $currency=(string)($resource['amount']['currency_code']??'');$value=(string)($resource['amount']['value']??'');
        if($paypalOrderId!==''){
            $st=$pdo->prepare("SELECT * FROM orders WHERE payment_provider='paypal' AND payment_reference=? LIMIT 1");$st->execute([$paypalOrderId]);$order=$st->fetch();
            if($order && $currency===$pc['currency'] && $value===paypal_money_value((int)$order['total_pence']))mark_order_paid_from_paypal((int)$order['id'],$paypalOrderId,$captureId,$event);
        }
    }
    http_response_code(200);echo 'OK';
}catch(Throwable $e){error_log('PayPal webhook error: '.$e->getMessage());http_response_code(500);echo 'Webhook error';}
