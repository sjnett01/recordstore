<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();
$orderId=(int)($_GET['order']??0);
$st=$pdo->prepare('SELECT o.id,o.status,o.created_at,o.paid_at,o.payment_provider,o.payment_reference,o.subtotal_pence,o.discount_code,o.discount_pence,o.vat_rate,o.vat_pence,o.total_pence FROM orders o WHERE o.id=? AND o.user_id=? LIMIT 1');
$st->execute([$orderId,(int)$u['id']]);$order=$st->fetch();
if(!$order||$order['status']!=='paid'){http_response_code(404);exit('Receipt not available.');} $vatRate=(float)($order['vat_rate']??0);$vatPence=(int)($order['vat_pence']??0);
$st=$pdo->prepare('SELECT oi.unit_price_pence,t.title,t.mix_name,a.name artist_name FROM order_items oi JOIN tracks t ON t.id=oi.track_id JOIN artists a ON a.id=t.artist_id WHERE oi.order_id=? ORDER BY oi.id');$st->execute([$orderId]);$items=$st->fetchAll();$business=business_details();
if(($_GET['format']??'')==='pdf'){header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="receipt-'.$orderId.'.pdf"');echo build_receipt_pdf($order,$items,$u,$business);exit;}
layout_header('Receipt');
?>
<section class="panel receipt-panel">
  <div class="receipt-toolbar"><a class="button secondary" href="<?=e(url('account.php'))?>">← My account</a><a class="button" href="<?=e(url('receipt/'.$orderId.'?format=pdf'))?>">Download PDF</a><button class="button secondary" type="button" onclick="window.print()">Print receipt</button></div>
  <header class="receipt-heading"><div><span class="kicker"><?=e($business['name']?:site_name())?></span><h1>Payment receipt</h1><p class="muted">Thank you for your purchase.</p><?php $businessLine=implode(' · ',array_filter([$business['address'],$business['city'],$business['postcode'],$business['country']]));?><p class="muted"><?=e($businessLine)?><?php if($business['vat_number']!==''):?> · VAT <?=e($business['vat_number'])?><?php endif;?></p></div><div class="receipt-reference"><strong>Order #<?=e((string)$order['id'])?></strong><span><?=e(date('j M Y, H:i',strtotime((string)($order['paid_at']?:$order['created_at']))) )?></span></div></header>
  <div class="receipt-customer"><strong><?=e($u['display_name'])?></strong><span><?=e($u['email'])?></span><span>Payment: <?=e(strtoupper((string)$order['payment_provider']))?><?php if(!empty($order['payment_reference'])):?> · <?=e($order['payment_reference'])?><?php endif;?></span></div>
  <div class="receipt-lines"><?php foreach($items as $item):?><div class="receipt-line"><span><strong><?=e($item['title'])?></strong><small><?=e($item['artist_name'])?><?=!empty($item['mix_name'])?' · '.e($item['mix_name']):''?></small></span><strong><?=money((int)$item['unit_price_pence'])?></strong></div><?php endforeach;?></div>
  <div class="receipt-totals"><span>Subtotal</span><strong><?=money((int)$order['subtotal_pence'])?></strong><?php if((int)$order['discount_pence']>0):?><span>Discount<?=!empty($order['discount_code'])?' ('.e($order['discount_code']).')':''?></span><strong class="receipt-discount">−<?=money((int)$order['discount_pence'])?></strong><?php endif;?><?php if($vatRate>0):?><span>VAT (<?=e(number_format($vatRate,2))?>%)</span><strong><?=money($vatPence)?></strong><?php endif;?><span>Total paid</span><strong><?=money((int)$order['total_pence'])?></strong></div>
  <p class="muted receipt-note">This receipt confirms payment for digital music. Your downloads remain available from My Account subject to the download allowance.</p>
</section>
<?php layout_footer();
