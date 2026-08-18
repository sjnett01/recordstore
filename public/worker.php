<?php
declare(strict_types=1);
require __DIR__.'/app-bootstrap.php';
$secret=(string)($config['app']['worker_secret']??'');
$key=(string)($_GET['key']??'');
$mode=(string)($_GET['mode']??'');
if($secret===''||$key===''||!hash_equals($secret,$key)||!in_array($mode,['release','abandoned','cleanup'],true)){http_response_code(404);exit('Worker unavailable.');}
require $privateRootPath.'/src/email_worker.php';
header('Content-Type: application/json; charset=utf-8');
try{if($mode==='cleanup'){$deleted=purge_unverified_accounts();echo json_encode(['ok'=>true,'mode'=>$mode,'deleted'=>$deleted,'ran_at'=>date(DATE_ATOM)]);}else{$status=run_email_worker($mode);echo json_encode(['ok'=>$status===0,'mode'=>$mode,'ran_at'=>date(DATE_ATOM)]);}}catch(Throwable $e){http_response_code(500);echo json_encode(['ok'=>false,'error'=>'Worker failed.']);error_log('Worker error: '.$e->getMessage());}
