<?php
require __DIR__.'/app-bootstrap.php';
layout_header('Terms and licensing');
?>
<section class="panel legal-page"><span class="kicker">CUSTOMER TERMS</span><h1>Terms and licensing</h1><p class="muted">Last updated <?=e(date('j F Y'))?></p>
<h2>Digital music licence</h2><p>Purchases grant a personal, non-transferable licence to download and use the purchased track for lawful personal and DJ use. Redistribution, resale, unauthorised sharing and making the master available to others are not permitted.</p><h2>Account and downloads</h2><p>You are responsible for keeping your account secure. Download limits and access are shown in My Account. Access may be revoked for cancelled, refunded or fraudulent orders.</p><h2>Store information</h2><p>Track descriptions, prices and release dates may be updated. We will not change the price of an order after payment has been accepted.</p>
</section>
<?php layout_footer();
