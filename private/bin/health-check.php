<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$checks=[];$ok=true;
try{$pdo->query('SELECT 1');$checks['database']='ok';}catch(Throwable $e){$checks['database']='failed';$ok=false;}
foreach(['masters','previews','artwork'] as $key){$path=(string)($config['paths'][$key]??'');$checks['path_'.$key]=is_dir($path)&&is_readable($path)?'ok':'missing/unreadable';if($checks['path_'.$key]!=='ok')$ok=false;}
foreach(['users','tracks','orders','order_items'] as $table){try{$pdo->query('SELECT 1 FROM '.$table.' LIMIT 1');$checks['table_'.$table]='ok';}catch(Throwable $e){$checks['table_'.$table]='missing';$ok=false;}}
echo json_encode(['ok'=>$ok,'checks'=>$checks,'time'=>date(DATE_ATOM)],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($ok?0:1);
