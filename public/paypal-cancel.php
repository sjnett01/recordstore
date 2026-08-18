<?php
require __DIR__.'/app-bootstrap.php';
$u=require_login();$id=(int)($_GET['order']??0);
if($id){$pdo->prepare("UPDATE orders SET status='cancelled' WHERE id=? AND user_id=? AND status='pending'")->execute([$id,$u['id']]);notify_order_mail($id,'order_cancelled');}
layout_header('Payment cancelled');?><section class="panel"><h1>Payment cancelled</h1><p>No payment was completed. Your cart is still available.</p><a class="button" href="cart">Return to cart</a></section><?php layout_footer();
