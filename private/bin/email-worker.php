<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
require __DIR__.'/../src/email_worker.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$mode=$argv[1]??'';
$status=run_email_worker($mode);
if($status!==0){fwrite(STDERR,"Usage: php email-worker.php release|abandoned\n");}
exit($status);