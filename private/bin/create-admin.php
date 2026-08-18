<?php
if (PHP_SAPI !== 'cli') exit("CLI only\n");
require __DIR__.'/../src/bootstrap.php';
$args=$argv;array_shift($args);
if(count($args)===2){[$email,$name]=$args;$fallbackPassword='';}
elseif(count($args)>=3){[$email,$fallbackPassword,$name]=$args;}
else exit("Usage: php bin/create-admin.php admin@example.com ['FallbackPassword'] 'Admin Name'\n");
$email=strtolower(trim((string)$email));$name=trim((string)$name);
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name==='')exit("Enter a valid email and name.\n");
if($fallbackPassword!==''&&strlen($fallbackPassword)<12)exit("Fallback password must be at least 12 characters.\n");
$hash=password_hash($fallbackPassword!==''?$fallbackPassword:bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
$st=$pdo->prepare('INSERT INTO users(email,password_hash,display_name,is_admin,email_verified_at) VALUES(?,?,?,1,NOW())');
$st->execute([$email,$hash,$name]);
echo "Admin created. OTP login is enabled".($fallbackPassword!==''?' with password fallback':'')." for {$email}.\n";
