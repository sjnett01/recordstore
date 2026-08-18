<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$count=purge_unverified_accounts();
echo json_encode(['ok'=>true,'deleted'=>$count,'ran_at'=>date(DATE_ATOM)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
