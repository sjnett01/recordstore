<?php
declare(strict_types=1);
require __DIR__.'/../src/bootstrap.php';
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$backupDir=(string)($config['paths']['backups']??dirname(__DIR__).'/backups');if(!is_dir($backupDir)&&!mkdir($backupDir,0750,true)&&!is_dir($backupDir))throw new RuntimeException('Cannot create backup directory.');
$file=rtrim($backupDir,'/').'/recordstore-'.date('Ymd-His').'.sql.gz';
$db=$config['db'];$bin=(string)($config['app']['mysqldump_bin']??'/usr/bin/mysqldump');$cmd=escapeshellarg($bin).' --single-transaction --routines --triggers -h'.escapeshellarg((string)$db['host']).' -P'.escapeshellarg((string)$db['port']).' -u'.escapeshellarg((string)$db['user']).' '.escapeshellarg((string)$db['name']).' | gzip > '.escapeshellarg($file);
putenv('MYSQL_PWD='.(string)$db['pass']);$code=0;exec($cmd,$output,$code);if($code!==0){@unlink($file);throw new RuntimeException('mysqldump failed with exit code '.$code);}
foreach(glob(rtrim($backupDir,'/').'/recordstore-*.sql.gz')?:[] as $old){if(filemtime($old)<time()-604800)@unlink($old);}
echo $file.PHP_EOL;
