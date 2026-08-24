<?php
require __DIR__.'/app-bootstrap.php';
layout_header('Cookies and privacy controls');
?>
<section class="panel legal-page"><span class="kicker">YOUR CHOICES</span><h1>Cookies and privacy controls</h1><p class="muted">Last updated <?=e(date('j F Y'))?></p>
<h2>Essential cookies</h2><p><?=e(site_name())?> uses essential session cookies to keep you signed in, protect forms and maintain your cart. These are required for the service to work.</p><h2>Optional controls</h2><p>We do not need advertising cookies for the store. You can clear cookies through your browser settings; doing so may sign you out and remove an unsubmitted cart.</p>
</section>
<?php layout_footer();
