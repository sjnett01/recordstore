<?php
require __DIR__.'/app-bootstrap.php';
layout_header('Privacy policy');
?>
<section class="panel legal-page"><span class="kicker">YOUR PRIVACY</span><h1>Privacy policy</h1><p class="muted">Last updated <?=e(date('j F Y'))?></p>
<h2>Information we use</h2><p>We use account details, order information, download records and security logs to provide the store, process payments, prevent abuse and support customers. Passwords are stored as secure hashes and payment credentials are handled by the payment provider.</p><h2>Retention and access</h2><p>We retain transaction and accounting records for the periods required by law. You may request access, correction or deletion of personal information where legally available by contacting the store administrator.</p><h2>Email updates</h2><p>Release updates are optional. Every marketing message must include an unsubscribe route. Transactional order and security messages are separate from marketing preferences.</p>
</section>
<?php layout_footer();
