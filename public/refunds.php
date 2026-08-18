<?php
require __DIR__.'/app-bootstrap.php';
layout_header('Refund policy');
?>
<section class="panel legal-page"><span class="kicker">CUSTOMER TRUST</span><h1>Refund policy</h1><p class="muted">Last updated <?=e(date('j F Y'))?></p>
<h2>Digital purchases</h2><p>Because digital downloads are supplied immediately after a successful payment, you request that delivery begins at checkout and acknowledge that the usual cancellation right may end once the download is made available.</p><h2>When to contact us</h2><p>Contact us promptly if a file is corrupt, materially different from its description, or you were charged incorrectly. We will investigate and, where appropriate, replace the file or arrange a refund through the original payment method.</p><p>Refunds for suspected fraud, duplicate payment or technical failure are reviewed individually.</p>
</section>
<?php layout_footer();
