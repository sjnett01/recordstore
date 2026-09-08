<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function asset_url(string $path): string { global $config; $path=ltrim($path,'/'); $base=(string)($config['app']['base_url']??''); $basePath=(string)(parse_url($base,PHP_URL_PATH)??''); return rtrim('/'.trim($basePath,'/'),'/').'/'.$path; }
function url(string $path=''): string { global $config; $path=ltrim($path,'/'); $aliases=['account.php'=>'account','cart.php'=>'cart','checkout.php'=>'checkout','paypal-resume.php'=>'paypal-resume','favourites.php'=>'favourites','login.php'=>'login','logout.php'=>'logout','register.php'=>'register','newsletter.php'=>'newsletter','new-releases.php'=>'new-releases','artist-submit.php'=>'artist-submit','artist-management.php'=>'artist-management','terms.php'=>'terms','privacy.php'=>'privacy','refunds.php'=>'refunds','cookies.php'=>'cookies']; foreach($aliases as $from=>$to){if($path===$from||str_starts_with($path,$from.'?')){$path=$to.substr($path,strlen($from));break;}} if(str_starts_with($path,'receipt.php?order=')){$path='receipt/'.rawurlencode(substr($path,strlen('receipt.php?order=')));} return rtrim($config['app']['base_url'],'/').'/'.$path; }
function recordstore_build_version(): string { return '1.13.5'; }
function recordstore_preview_format(): string { return 'M4A / AAC (faststart)'; }
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function require_csrf(): void { if(!hash_equals($_SESSION['csrf']??'', $_POST['csrf']??'')){ http_response_code(419); exit('Invalid CSRF token'); } }
function audit_event(string $eventType, ?int $userId=null, array $metadata=[]): void { global $pdo; try { $ip=$_SERVER['REMOTE_ADDR']??null;$ua=substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,512);$json=$metadata?json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;$st=$pdo->prepare('INSERT INTO audit_log(user_id,event_type,ip_address,user_agent,metadata_json) VALUES(?,?,INET6_ATON(?),?,?)');$st->execute([$userId,$eventType,$ip,$ua,$json]); } catch(Throwable $ignored) {} }
function login_rate_limited(): bool { global $pdo; $ip=(string)($_SERVER['REMOTE_ADDR']??''); if($ip==='')return false; try{$st=$pdo->prepare("SELECT COUNT(*) FROM audit_log WHERE event_type='login_failed' AND ip_address=INET6_ATON(?) AND created_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)");$st->execute([$ip]);return (int)$st->fetchColumn()>=10;}catch(Throwable $ignored){return false;} }function user(): ?array { global $pdo; if(empty($_SESSION['user_id'])) return null; $st=$pdo->prepare('SELECT id,email,display_name,is_admin,is_artist,paypal_email,email_verified_at,disabled_at,banned_at,ban_reason FROM users WHERE id=?'); $st->execute([$_SESSION['user_id']]); $row=$st->fetch()?:null; if($row&&($row['disabled_at']||$row['banned_at']||empty($row['email_verified_at']))){if($row&&empty($row['email_verified_at'])){$reason='email_unverified';}else{$reason=$row['banned_at']?'banned':'disabled';}if(empty($_SESSION['blocked_session_audited'])){audit_event('session_blocked',(int)$row['id'],['reason'=>$reason]);$_SESSION['blocked_session_audited']=1;}unset($_SESSION['user_id']);return null;}return $row; }
function require_login(): array { global $pdo; $u=user(); if(!$u) redirect('login?next='.urlencode($_SERVER['REQUEST_URI']??'/account')); try{$st=$pdo->prepare('SELECT revoked_at,expires_at FROM user_sessions WHERE session_hash=? AND user_id=? LIMIT 1');$st->execute([security_session_hash(),(int)$u['id']]);$session=$st->fetch();if($session&&($session['revoked_at']||($session['expires_at']&&strtotime($session['expires_at'])<time()))){revoke_user_session();$_SESSION=[];redirect('login');}}catch(Throwable $ignored){} return $u; }
function require_admin(): array { global $pdo; $u=require_login(); if(empty($u['is_admin'])){http_response_code(403);exit('Admin only');} try{$st=$pdo->prepare('SELECT admin_2fa_enabled FROM users WHERE id=?');$st->execute([(int)$u['id']]);if((int)$st->fetchColumn()===1&&empty($_SESSION['admin_2fa_verified'])){$_SESSION['admin_2fa_return']=(string)($_SERVER['REQUEST_URI']??'admin.php');redirect('admin-2fa.php');}}catch(Throwable $ignored){} return $u; }
function require_artist(): array { $u=require_login(); if(empty($u['is_artist'])&&!empty($u['is_admin']))return $u; if(empty($u['is_artist'])){http_response_code(403);exit('Artist submissions are not enabled for this account.');} return $u; }
function money(int $pence): string { return '£'.number_format($pence/100,2); }
function business_details(): array { return ['name'=>(string)store_setting('business_name',site_name()),'address'=>(string)store_setting('business_address',''),'city'=>(string)store_setting('business_city',''),'postcode'=>(string)store_setting('business_postcode',''),'country'=>(string)store_setting('business_country',''),'vat_number'=>(string)store_setting('business_vat_number','')]; }
function receipt_pdf_text(string $value): string { return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],preg_replace('/[^\\x20-\\x7E\\n]/','?',(string)$value)??''); }
function build_receipt_pdf(array $order,array $items,array $customer,array $business): string {
    $lines=[];$lines[]=$business['name'];foreach([$business['address'],$business['city'],$business['postcode'],$business['country']] as $part){if(trim($part)!=='')$lines[]=$part;}if($business['vat_number']!=='')$lines[]='VAT number: '.$business['vat_number'];$lines[]='';$lines[]='PAYMENT RECEIPT';$lines[]='Order #'.$order['id'].'  '.date('j M Y, H:i',strtotime((string)($order['paid_at']?:$order['created_at'])));$lines[]='Customer: '.$customer['display_name'].' <'.$customer['email'].'>';$lines[]='';foreach($items as $item)$lines[]=$item['title'].' - '.$item['artist_name'].(!empty($item['mix_name'])?' / '.$item['mix_name']:'').'  '.money((int)$item['unit_price_pence']);$lines[]='';$lines[]='Subtotal: '.money((int)$order['subtotal_pence']);if((int)$order['discount_pence']>0)$lines[]='Discount: -'.money((int)$order['discount_pence']);if((float)($order['vat_rate']??0)>0)$lines[]='VAT ('.number_format((float)$order['vat_rate'],2).'%) '.money((int)$order['vat_pence']);$lines[]='Total paid: '.money((int)$order['total_pence']);$lines[]='';$lines[]='Digital goods receipt - no physical delivery.';$content="BT\n/F1 11 Tf\n50 790 Td\n";foreach($lines as $i=>$line){if($i>0)$content.="0 -16 Td\n";$content.='('.receipt_pdf_text($line).") Tj\n";}$content.="ET\n";$objects=[];$objects[]='<< /Type /Catalog /Pages 2 0 R >>';$objects[]='<< /Type /Pages /Kids [3 0 R] /Count 1 >>';$objects[]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>';$objects[]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';$objects[]='<< /Length '.strlen($content).' >>\nstream\n'.$content.'endstream';$pdf="%PDF-1.4\n";$offsets=[0];foreach($objects as $i=>$object){$offsets[] = strlen($pdf);$pdf.=($i+1).' 0 obj\n'.$object."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";for($i=1;$i<=count($objects);$i++)$pdf.=sprintf('%010d 00000 n \n',$offsets[$i]);$pdf.="trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";return $pdf;
}
function purge_unverified_accounts(): int { global $pdo; $st=$pdo->prepare("DELETE FROM users WHERE email_verified_at IS NULL AND is_admin=0 AND created_at < DATE_SUB(NOW(),INTERVAL 7 DAY) AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id=users.id)");$st->execute();return $st->rowCount(); }
function cart(): array { return $_SESSION['cart']??[]; }
function cart_count(): int { return count(cart()); }function discount_code_normalize(string $code): string { return strtoupper(trim($code)); }
function discount_quote(string $code,int $subtotalPence,?int $userId=null): array {
    global $pdo;
    $code=discount_code_normalize($code);
    if($code==='')return ['ok'=>false,'message'=>'Enter a discount code.','code'=>'','discount_pence'=>0,'total_pence'=>$subtotalPence];
    try { $st=$pdo->prepare('SELECT * FROM discount_codes WHERE code=? AND active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (ends_at IS NULL OR ends_at>=NOW()) LIMIT 1');$st->execute([$code]);$row=$st->fetch(); } catch (Throwable $e) { return ['ok'=>false,'message'=>'Discounts are not available yet.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence]; }
    if(!$row)return ['ok'=>false,'message'=>'That discount code is not valid or has expired.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence];
    if($row['user_id']!==null&&(!$userId||(int)$row['user_id']!==$userId))return ['ok'=>false,'message'=>'That discount code is not available for this account.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence];
    if((int)$row['one_time_per_user']===1&&$userId){$used=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND discount_code=? AND status IN ('pending','paid')");$used->execute([$userId,$code]);if((int)$used->fetchColumn()>0)return ['ok'=>false,'message'=>'This discount code has already been used by this account.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence];}
    if($row['usage_limit']!==null&&(int)$row['used_count']>=(int)$row['usage_limit'])return ['ok'=>false,'message'=>'That discount code has reached its usage limit.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence];
    if($subtotalPence<(int)$row['minimum_subtotal_pence'])return ['ok'=>false,'message'=>'This code requires a minimum basket of '.money((int)$row['minimum_subtotal_pence']).'.','code'=>$code,'discount_pence'=>0,'total_pence'=>$subtotalPence];
    $discount=(string)$row['discount_type']==='percent'?(int)floor($subtotalPence*min(100,(int)$row['discount_value'])/100):min($subtotalPence,(int)$row['discount_value']);
    return ['ok'=>true,'message'=>'Discount applied.','code'=>$code,'discount_pence'=>$discount,'total_pence'=>max(0,$subtotalPence-$discount),'row'=>$row];
}
function automatic_promotion_quote(int $subtotalPence,?int $userId=null): array {
    global $pdo;
    $ids=array_map('intval',array_keys(cart()));
    $empty=['ok'=>false,'message'=>'','code'=>'','discount_pence'=>0,'total_pence'=>$subtotalPence];
    if(!$ids)return $empty;
    $ph=implode(',',array_fill(0,count($ids),'?'));
    try{$st=$pdo->prepare("SELECT t.id,t.price_pence,t.genre_id,g.name genre_name FROM tracks t LEFT JOIN genres g ON g.id=t.genre_id WHERE t.active=1 AND t.id IN ($ph)");$st->execute($ids);$tracks=$st->fetchAll();$now=$pdo->query('SELECT NOW()')->fetchColumn();$st=$pdo->prepare("SELECT d.*,g.name genre_name FROM discount_codes d LEFT JOIN genres g ON g.id=d.genre_id WHERE d.active=1 AND d.auto_apply=1 AND d.promotion_kind<>'code' AND (d.starts_at IS NULL OR d.starts_at<=?) AND (d.ends_at IS NULL OR d.ends_at>=?) ORDER BY d.discount_value DESC,d.id ASC");$st->execute([$now,$now]);$promotions=$st->fetchAll();}catch(Throwable $e){return $empty;}
    $best=$empty;$count=count($tracks);
    foreach($promotions as $row){
        if($row['user_id']!==null&&(!$userId||(int)$row['user_id']!==$userId))continue;
        if((int)$row['one_time_per_user']===1&&$userId){$used=$pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND discount_code=? AND status IN ('pending','paid')");$used->execute([$userId,$row['code']]);if((int)$used->fetchColumn()>0)continue;}
        if($row['usage_limit']!==null&&(int)$row['used_count']>=(int)$row['usage_limit'])continue;
        if($subtotalPence<(int)$row['minimum_subtotal_pence']||$count<(int)$row['minimum_items'])continue;
        if($row['promotion_kind']==='genre'){$genreId=(int)($row['genre_id']??0);if(!$genreId||!array_filter($tracks,fn($t)=>(int)($t['genre_id']??0)===$genreId))continue;}
        $eligible=$tracks;if($row['promotion_kind']==='genre'){$genreId=(int)$row['genre_id'];$eligible=array_filter($tracks,fn($t)=>(int)($t['genre_id']??0)===$genreId);}
        $base=array_sum(array_map(fn($t)=>(int)$t['price_pence'],$eligible));$discount=(string)$row['discount_type']==='percent'?(int)floor($base*min(100,(int)$row['discount_value'])/100):min($base,(int)$row['discount_value']);
        if($discount>(int)$best['discount_pence'])$best=['ok'=>$discount>0,'message'=>($row['promotion_kind']==='bundle'?'Bundle offer applied.':($row['genre_name']?$row['genre_name'].' sale applied.':'Promotion applied.')),'code'=>$row['code'],'discount_pence'=>$discount,'total_pence'=>max(0,$subtotalPence-$discount),'row'=>$row];
    }
    return $best;
}
function cart_discount_quote(int $subtotalPence,?int $userId=null): array { $code=discount_code_normalize((string)($_SESSION['discount_code']??'')); return $code!==''?discount_quote($code,$subtotalPence,$userId):automatic_promotion_quote($subtotalPence,$userId); }function favourite_track_ids(?int $userId=null): array {
    static $cache=[];
    if($userId===null){$u=user();$userId=$u?(int)$u['id']:0;}
    if($userId<=0)return [];
    if(array_key_exists($userId,$cache))return $cache[$userId];
    try{$st=$GLOBALS['pdo']->prepare('SELECT track_id FROM user_favourites WHERE user_id=?');$st->execute([$userId]);$cache[$userId]=array_fill_keys(array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN)),true);}catch(Throwable $ignored){$cache[$userId]=[];}
    return $cache[$userId];
}
function is_track_favourite(int $trackId): bool { return isset(favourite_track_ids()[$trackId]); }function save_pending_favourite(int $userId): void {
    $trackId=(int)($_SESSION['pending_favourite_track']??0);unset($_SESSION['pending_favourite_track']);
    if($trackId<=0)return;
    try{$st=$GLOBALS['pdo']->prepare('INSERT IGNORE INTO user_favourites(user_id,track_id) SELECT ?,id FROM tracks WHERE id=? AND active=1');$st->execute([$userId,$trackId]);}catch(Throwable $ignored){}
}
function current_path(): string { return basename(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'index.php'); }
function slugify(string $s): string { $s=strtolower(trim($s)); $s=preg_replace('/[^a-z0-9]+/','-',$s)??''; return trim($s,'-')?:'item'; }function unique_artist_slug(PDO $pdo, string $name, ?int $excludeId=null): string { $base=slugify($name); $candidate=$base; $suffix=2; while(true){$sql='SELECT id FROM artists WHERE slug=?'.($excludeId?' AND id<>?':'').' LIMIT 1';$st=$pdo->prepare($sql);$excludeId?$st->execute([$candidate,$excludeId]):$st->execute([$candidate]);if(!$st->fetch())return $candidate;$candidate=$base.'-'.$suffix++;} }
function artist_public_slug(array $artist): string { $slug=slugify((string)($artist['slug']??$artist['artist_slug']??$artist['name']??'artist'));return preg_replace('/-[0-9a-f]{4}$/','',$slug)?:$slug; }
function parse_artist_credit_names(string $credit): array { $credit=trim(preg_replace('/\s+/',' ',$credit)??$credit);if($credit==='')return []; $parts=preg_split('/\s+(?:x|×|&|vs\.?|feat\.?|ft\.?|featuring)\s+/iu',$credit,-1,PREG_SPLIT_NO_EMPTY)?:[$credit];$out=[];foreach($parts as $part){$part=trim($part," \t\n\r\0\x0B,;-");if($part!==''&&!in_array(mb_strtolower($part),array_map('mb_strtolower',$out),true))$out[]=$part;}return $out; }
function artist_credit_name_matches(string $artistName,array $creditNames): bool { $needle=mb_strtolower(trim(preg_replace('/\s+/',' ',$artistName)??$artistName));foreach($creditNames as $name){if($needle===mb_strtolower(trim((string)$name)))return true;}return false; }
function track_artist_credit(array $track): string { static $cache=[]; $id=(int)($track['id']??$track['track_id']??0);$fallback=trim((string)($track['artist_name']??''));if($id<=0)return $fallback;if(isset($cache[$id]))return $cache[$id];try{$st=$GLOBALS['pdo']->prepare("SELECT a.name FROM track_artists ta JOIN artists a ON a.id=ta.artist_id WHERE ta.track_id=? ORDER BY ta.sort_order,ta.artist_id");$st->execute([$id]);$names=$st->fetchAll(PDO::FETCH_COLUMN);$cache[$id]=$names?implode(' x ',array_map('strval',$names)):$fallback;}catch(Throwable $ignored){$cache[$id]=$fallback;}return $cache[$id]; }
function artist_credit_from_ids(array $ids): string { $ids=array_values(array_unique(array_filter(array_map('intval',$ids))));if(!$ids)return '';try{$ph=implode(',',array_fill(0,count($ids),'?'));$st=$GLOBALS['pdo']->prepare("SELECT id,name FROM artists WHERE id IN ($ph)");$st->execute($ids);$byId=[];foreach($st->fetchAll() as $row)$byId[(int)$row['id']]=(string)$row['name'];$names=[];foreach($ids as $id)if(isset($byId[$id]))$names[]=$byId[$id];return implode(' x ',$names);}catch(Throwable $ignored){return '';}}
function track_artist_ids(int $trackId): array { if($trackId<=0)return []; try{$st=$GLOBALS['pdo']->prepare('SELECT artist_id FROM track_artists WHERE track_id=? ORDER BY sort_order,artist_id');$st->execute([$trackId]);$ids=array_map('intval',$st->fetchAll(PDO::FETCH_COLUMN));if($ids)return $ids;$st=$GLOBALS['pdo']->prepare('SELECT artist_id FROM tracks WHERE id=?');$st->execute([$trackId]);$primary=(int)($st->fetchColumn()?:0);return $primary?[$primary]:[];}catch(Throwable $ignored){return [];} }

function artwork_url(?string $path): string {
    if(!$path){$fallback=site_theme()['default_artwork']??'';return $fallback!==''?(filter_var($fallback,FILTER_VALIDATE_URL)?$fallback:url(ltrim($fallback,'/'))):asset_url('assets/images/empty-art.svg');}
    return url('media.php?type=artwork&f='.rawurlencode($path));
}
function artist_image_url(?string $path): string { return $path ? artwork_url($path) : url('assets/images/artist-placeholder.svg'); }

function store_uploaded_image(array $file, string $subdir, string $prefix='image'): ?string {
    global $config;
    if(($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
    if(($file['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if(($file['size']??0)>6*1024*1024) throw new RuntimeException('Image must be 6 MB or smaller.');
    $info=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!isset($allowed[$info])) throw new RuntimeException('Artwork must be JPG, PNG or WebP.');
    $dir=rtrim($config['paths']['artwork'],'/').'/'.$subdir;
    if(!is_dir($dir) && !mkdir($dir,0755,true)) throw new RuntimeException('Artwork directory is not writable.');
    $name=slugify($prefix).'-'.bin2hex(random_bytes(7)).'.'.$allowed[$info];
    if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Could not save artwork.');
    return $subdir.'/'.$name;
}

function standardized_track_filename(string $artist, string $title, ?string $mixName, string $extension='mp3'): string {
    $artist=trim(preg_replace('/\s+/',' ',$artist)??'Artist');
    $title=trim(preg_replace('/\s+/',' ',$title)??'Track');
    $mix=trim(preg_replace('/\s+/',' ',$mixName??'')??'');
    $name=$artist.' - '.$title.($mix!==''?' ('.$mix.')':'');
    $name=preg_replace('/[<>:"\/\\|?*\x00-\x1F]+/','',$name)??$name;
    $name=trim(preg_replace('/\s+/',' ',$name)??$name,'. ');
    return ($name!==''?$name:'Track').'.'.strtolower(ltrim($extension,'.'));
}

function audio_metadata_tags(string $path): array {
    global $config;
    $ffprobe=(string)($config['app']['ffprobe_path']??'');
    if(!function_exists('exec'))return [];
    if($ffprobe!==''&&is_file($ffprobe)&&is_executable($ffprobe)){$cmd=escapeshellarg($ffprobe).' -v quiet -print_format json -show_entries format_tags='.escapeshellarg('artist,title,genre,TBPM').' '.escapeshellarg($path);$output=[];$exit=0;exec($cmd,$output,$exit);if($exit===0){$json=json_decode(implode("\n",$output),true);$tags=$json['format']['tags']??[];$normal=[];foreach($tags as $key=>$value)$normal[strtolower((string)$key)]=(string)$value;if($normal)return $normal;}}
    $ffmpeg=(string)($config['app']['ffmpeg_path']??'');if($ffmpeg===''||!is_file($ffmpeg)||!is_executable($ffmpeg))return [];
    $output=[];$exit=0;exec(escapeshellarg($ffmpeg).' -hide_banner -i '.escapeshellarg($path).' -f ffmetadata - 2>&1',$output,$exit);$normal=[];foreach($output as $line){if(preg_match('/^([A-Za-z0-9_]+)=(.*)$/',$line,$m))$normal[strtolower($m[1])]=trim($m[2]);}return $normal;
}

function apply_audio_metadata(string $path,string $artist,string $title,?string $genre,?int $bpm,?string $mixName=null): void {
    global $config;
    if(strtolower(pathinfo($path,PATHINFO_EXTENSION))!=='mp3')return;
    $ffmpeg=(string)($config['app']['ffmpeg_path']??'');if($ffmpeg===''||!is_file($ffmpeg)||!is_executable($ffmpeg)||!function_exists('exec'))throw new RuntimeException('MP3 metadata could not be updated because ffmpeg is unavailable.');
    $temp=$path.'.tagging-'.bin2hex(random_bytes(8)).'.mp3';
    $args=[$ffmpeg,'-hide_banner','-loglevel','error','-y','-i',$path,'-map_metadata','-1','-codec:a','copy','-id3v2_version','3','-metadata','artist='.$artist,'-metadata','album_artist='.$artist,'-metadata','title='.$title,'-metadata','genre='.($genre??''),'-metadata','TBPM='.($bpm?(string)$bpm:''),'-metadata','comment='.($mixName??''),$temp];
    $cmd='';foreach($args as $arg)$cmd.=' '.escapeshellarg((string)$arg);$output=[];$exit=0;exec(trim($cmd),$output,$exit);if($exit!==0||!is_file($temp)){@unlink($temp);throw new RuntimeException('The MP3 metadata could not be updated.');}
    if(!@rename($temp,$path)){@unlink($temp);throw new RuntimeException('The tagged MP3 could not replace the stored master.');}
    $tags=audio_metadata_tags($path);if($tags){$matches=strcasecmp(trim((string)($tags['artist']??'')),trim($artist))===0&&strcasecmp(trim((string)($tags['title']??'')),trim($title))===0;if($genre!==null&&$genre!=='' )$matches=$matches&&strcasecmp(trim((string)($tags['genre']??'')),trim($genre))===0;if($bpm)$matches=$matches&&preg_match('/^'.preg_quote((string)$bpm,'/').'$/',(string)($tags['tbpm']??''));if(!$matches)throw new RuntimeException('The stored MP3 metadata could not be verified.');}
}

function standardize_stored_audio(array $stored,string $artist,string $title,?string $genre,?int $bpm,?string $mixName=null): array {
    $trackId=(int)($_POST['id']??0);if($trackId>0){try{$st=$GLOBALS['pdo']->prepare('SELECT a.name FROM track_artists ta JOIN artists a ON a.id=ta.artist_id WHERE ta.track_id=? ORDER BY ta.sort_order,ta.artist_id');$st->execute([$trackId]);$names=$st->fetchAll(PDO::FETCH_COLUMN);if($names)$artist=implode(' x ',array_map('strval',$names));}catch(Throwable $ignored){}}
    if(!empty($_POST['artist_ids'])&&is_array($_POST['artist_ids'])){try{$ids=array_values(array_unique(array_filter(array_map('intval',$_POST['artist_ids']))));if($ids){$ph=implode(',',array_fill(0,count($ids),'?'));$st=$GLOBALS['pdo']->prepare("SELECT id,name FROM artists WHERE id IN ($ph)");$st->execute($ids);$byId=[];foreach($st->fetchAll() as $row)$byId[(int)$row['id']]=(string)$row['name'];$ordered=[];foreach($ids as $id)if(isset($byId[$id]))$ordered[]=$byId[$id];if($ordered)$artist=implode(' x ',$ordered);}}catch(Throwable $ignored){}}
    if (($GLOBALS['config']['app']['preview_mode'] ?? 'auto') !== 'manual') apply_audio_metadata((string)$stored['master_full_path'],$artist,$title,$genre,$bpm,$mixName);
    $extension=pathinfo((string)($stored['master_path']??$stored['file_name']??''),PATHINFO_EXTENSION)?:'mp3';
    $stored['file_name']=standardized_track_filename($artist,$title,$mixName,$extension);
    return $stored;
}

function site_theme(): array {
    global $config;
    return ['bg'=>store_setting('theme_bg','#07090e')?:'#07090e','panel'=>store_setting('theme_panel','#10141b')?:'#10141b','text'=>store_setting('theme_text','#f7f8fb')?:'#f7f8fb','muted'=>store_setting('theme_muted','#8f98a8')?:'#8f98a8','accent'=>store_setting('theme_accent','#7b3cff')?:'#7b3cff','accent2'=>store_setting('theme_accent2','#a35bff')?:'#a35bff','font'=>store_setting('theme_font','Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif')?:'Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif','heading_font'=>store_setting('theme_heading_font','Inter')?:'Inter','ui_font'=>store_setting('theme_ui_font','Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif','mono_font'=>store_setting('theme_mono_font','ui-monospace,SFMono-Regular,Consolas,monospace')?:'ui-monospace,SFMono-Regular,Consolas,monospace','display_font'=>store_setting('theme_display_font',store_setting('theme_heading_font','Inter')?:'Inter')?:'Inter','section_font'=>store_setting('theme_section_font',store_setting('theme_heading_font','Inter')?:'Inter')?:'Inter','card_font'=>store_setting('theme_card_font',store_setting('theme_heading_font','Inter')?:'Inter')?:'Inter','meta_font'=>store_setting('theme_meta_font',store_setting('theme_font','Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif','label_font'=>store_setting('theme_label_font',store_setting('theme_ui_font','Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif','button_font'=>store_setting('theme_button_font',store_setting('theme_ui_font','Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif')?:'Inter,ui-sans-serif,system-ui,sans-serif','default_artwork'=>store_setting('theme_default_artwork','')?:''];
}
function site_name(): string {
    global $config;
    $fallback=trim((string)($config['app']['name']??'RecordStore')) ?: 'RecordStore';
    return trim((string)store_setting('site_name',$fallback)) ?: $fallback;
}
function store_identifier(string $value, string $fallback='store'): string {
    $value=strtolower((string)preg_replace('/[^a-z0-9]+/i','-',trim($value)));
    $value=trim($value,'-');
    return substr($value!==''?$value:$fallback,0,40);
}
function store_dom_id(string $suffix): string { return store_identifier(site_name()).'-'.trim($suffix,'-'); }function mail_theme_css(): string { $t=site_theme(); return ':root{--mail-bg:'.htmlspecialchars($t['bg'],ENT_QUOTES,'UTF-8').';--mail-panel:'.htmlspecialchars($t['panel'],ENT_QUOTES,'UTF-8').';--mail-text:'.htmlspecialchars($t['text'],ENT_QUOTES,'UTF-8').';--mail-muted:'.htmlspecialchars($t['muted'],ENT_QUOTES,'UTF-8').';--mail-accent:'.htmlspecialchars($t['accent'],ENT_QUOTES,'UTF-8').';--mail-accent2:'.htmlspecialchars($t['accent2'],ENT_QUOTES,'UTF-8').';--mail-font:'.htmlspecialchars($t['font'],ENT_QUOTES,'UTF-8').';}'; }
function request_newsletter_subscription(string $email, ?int $userId=null, string $source='site'): string {
    global $pdo;
    $email=strtolower(trim($email));
    if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    $token=bin2hex(random_bytes(32));$hash=hash('sha256',$token);
    $st=$pdo->prepare('SELECT id,confirmed_at,unsubscribed_at FROM newsletter_subscribers WHERE email=? LIMIT 1');$st->execute([$email]);$existing=$st->fetch();
    if($existing&&$existing['confirmed_at']&&!$existing['unsubscribed_at'])return 'You are already subscribed to release updates.';
    if($existing)$pdo->prepare('UPDATE newsletter_subscribers SET user_id=?,confirmation_hash=?,confirmed_at=NULL,unsubscribed_at=NULL,source=? WHERE id=?')->execute([$userId,$hash,$source,(int)$existing['id']]);
    else $pdo->prepare('INSERT INTO newsletter_subscribers(user_id,email,confirmation_hash,source) VALUES(?,?,?,?)')->execute([$userId,$email,$hash,$source]);
    try{send_store_mail($email,'Confirm '.site_name().' release updates','Please confirm your subscription by visiting: '.url('newsletter.php?confirm='.$token)."\n\nIf you did not request this, you can ignore this email.");}catch(Throwable $mailError){error_log('Newsletter confirmation email failed: '.$mailError->getMessage());}
    return 'Check your inbox to confirm your release updates subscription.';
}
function layout_header(string $title=''): void {
    global $config,$pdo;
    $u=user(); $cartCount=cart_count(); $page=current_path(); $name=site_name(); $shellState=$u ? 'user-'.(int)$u['id'].'-'.(!empty($u['is_admin'])?'admin':'customer').'-'.(!empty($u['is_artist'])?'artist':'shopper') : 'guest'; $metaContext=$GLOBALS['page_meta']??[]; $metaTitle=$title ? $title.' | '. $name : $name; $requestPath=(string)(parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)??'/'); $canonicalUrl=url(ltrim($requestPath,'/')); $metaDescription=(string)($metaContext['description']??($title ? $title.' on '. $name : $name.' digital music store')); $metaImage=(string)($metaContext['image']??url('assets/images/brand-orb.svg')); $metaImageAlt=(string)($metaContext['image_alt']??$metaTitle); $metaImageType=(string)($metaContext['image_type']??'image/svg+xml'); $metaType=(string)($metaContext['type']??'website'); $metaStructured=$metaContext['structured']??null;
    ?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?=e(site_theme()['bg'])?>"><style>:root{--bg:<?=e(site_theme()['bg'])?>;--panel:<?=e(site_theme()['panel'])?>;--text:<?=e(site_theme()['text'])?>;--muted:<?=e(site_theme()['muted'])?>;--purple:<?=e(site_theme()['accent'])?>;--purple2:<?=e(site_theme()['accent2'])?>;font-family:<?=e(site_theme()['font'])?>;--heading-font:<?=e(site_theme()['heading_font'])?>;--ui-font:<?=e(site_theme()['ui_font'])?>;--mono-font:<?=e(site_theme()['mono_font'])?>;--display-font:<?=e(site_theme()['display_font'])?>;--section-font:<?=e(site_theme()['section_font'])?>;--card-font:<?=e(site_theme()['card_font'])?>;--meta-font:<?=e(site_theme()['meta_font'])?>;--label-font:<?=e(site_theme()['label_font'])?>;--button-font:<?=e(site_theme()['button_font'])?>}.hero h1{font-family:var(--display-font)}.section-title h1,.section-title h2{font-family:var(--section-font)}.release-info h3,.track-row h3{font-family:var(--card-font)}.hero p,.release-info p,.track-row small,.player-meta span{font-family:var(--meta-font)}.kicker,.eyebrow,.nav{font-family:var(--label-font)}.button,button{font-family:var(--button-font)}input,textarea,select{font-family:var(--ui-font)}h1,h2,h3{font-family:var(--heading-font)}code{font-family:var(--mono-font)}</style><meta name="description" content="<?=e($metaDescription)?>"><link rel="canonical" href="<?=e($canonicalUrl)?>"><meta property="og:site_name" content="<?=e($name)?>"><meta property="og:locale" content="en_GB"><meta property="og:title" content="<?=e($metaTitle)?>"><meta property="og:description" content="<?=e($metaDescription)?>"><meta property="og:url" content="<?=e($canonicalUrl)?>"><meta property="og:type" content="<?=e($metaType)?>"><meta property="og:image" content="<?=e($metaImage)?>"><meta property="og:image:alt" content="<?=e($metaImageAlt)?>"><meta property="og:image:type" content="<?=e($metaImageType)?>"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?=e($metaTitle)?>"><meta name="twitter:description" content="<?=e($metaDescription)?>"><meta name="twitter:image" content="<?=e($metaImage)?>"><?php if($metaStructured):?><script type="application/ld+json"><?=json_encode($metaStructured,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP)?></script><?php endif;?><script>window.pagespeed=window.pagespeed||{};window.pagespeed.CriticalImages=window.pagespeed.CriticalImages||{checkImageForCriticality:function(){}};</script>
    <title><?=e($metaTitle)?></title>
    <link rel="icon" href="<?=e(asset_url('assets/images/favicon.svg'))?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?=e(asset_url('assets/css/app.css?v=1.13.65'))?>"></head><body data-preview-analytics-url="<?=e(url('preview-analytics.php'))?>" data-app-name="<?=e($name)?>" data-store-key="<?=e(store_identifier($name))?>">
    <div class="shell"><aside class="sidebar">
      <a class="brand" href="<?=e(url(''))?>"><img src="<?=e(asset_url('assets/images/logo.svg'))?>" alt=""><span class="brand-name"><?=e($name)?></span></a>
      <button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="mobileSiteNav"><span class="mobile-menu-icon">☰</span><span>Menu</span></button>
      <?php $navGenres=$pdo->query('SELECT id,name,slug FROM genres ORDER BY name')->fetchAll(); ?>
      <div id="mobileSiteNav" class="mobile-nav-panel"><nav class="side-nav" data-shell-state="<?=e($shellState)?>">
<a class="nav <?=in_array($page,['index.php',''],true)?'active':''?>" href="<?=e(url(''))?>"><span>⌂</span>Home</a>
        <a class="nav <?=in_array($page,['new-tracks.php','new-tracks'],true)?'active':''?>" href="<?=e(url('new-tracks'))?>"><span>✦</span>New Tracks</a>
        <?php if($u&&(!empty($u['is_artist'])||!empty($u['is_admin']))):?><a class="nav <?=in_array($page,['artist-management.php','artist-management'],true)?'active':''?>" href="<?=e(url('artist-management'))?>"><span>♬</span>Management</a><?php endif;?>
<a class="nav <?=in_array($page,['tracks.php','tracks'],true)?'active':''?>" href="<?=e(url('tracks'))?>"><span>♫</span>All Tracks</a>
        <a class="nav <?=in_array($page,['artists.php','artists'],true)?'active':''?>" href="<?=e(url('artists'))?>"><span>◉</span>Artists</a>
        <div class="nav-flyout"><a class="nav <?=in_array($page,['genres.php','genres'],true)?'active':''?>" href="<?=e(url('genres'))?>"><span>◇</span>Genres<b aria-hidden="true">›</b></a><div class="nav-submenu"><a href="<?=e(url('genres'))?>">All genres</a><?php foreach($navGenres as $g):?><a href="<?=e(url('genres/'.rawurlencode($g['slug'])))?>"><?=e($g['name'])?></a><?php endforeach;?></div></div>
        <div class="nav-flyout"><a class="nav <?=in_array($page,['charts.php','charts'],true)?'active':''?>" href="<?=e(url('charts'))?>"><span>▥</span>Charts<b aria-hidden="true">›</b></a><div class="nav-submenu"><a href="<?=e(url('charts'))?>">Overall</a><?php foreach($navGenres as $g):?><a href="<?=e(url('charts/'.rawurlencode($g['slug'])))?>"><?=e($g['name'])?></a><?php endforeach;?></div></div>
      </nav>

      <nav class="mobile-account-nav" aria-label="Account navigation">
        <?php if($u): ?><a href="<?=e(url('account.php'))?>"><span class="mobile-account-glyph">◉</span><span>My Account</span></a><a href="<?=e(url('favourites.php'))?>"><span class="mobile-account-glyph">♥</span><span>Favourites</span></a><?php if(!empty($u['is_admin'])):?><a href="<?=e(url('admin.php'))?>"><span class="mobile-account-glyph">♟</span><span>Admin</span></a><?php endif;?><?php else:?><a href="<?=e(url('login.php'))?>"><span class="mobile-account-glyph">↪</span><span>Login</span></a><a href="<?=e(url('register.php'))?>"><span class="mobile-account-glyph">＋</span><span>Register</span></a><?php endif;?>
        <a class="mobile-cart-link" href="<?=e(url('cart.php'))?>"><span class="mobile-account-glyph">🛒</span><span>Cart</span> <span class="pill"><?=$cartCount?></span></a>
      </nav>
      <div class="sidebar-note newsletter-note"><strong>New music</strong><small>Get release updates and occasional offers.</small><a class="button secondary tiny" href="<?=e(url('newsletter.php'))?>">Subscribe</a></div></div>
    </aside>
    <main class="main"><header class="topbar"><form class="search" action="tracks"><span>⌕</span><input name="q" placeholder="Search artist or track name..." autocomplete="off"></form><nav class="top-actions">
    <?php if($u): ?><a href="<?=e(url('account.php'))?>"><span class="mobile-account-glyph">◉</span><span>My Account</span></a><a href="<?=e(url('favourites.php'))?>"><span class="mobile-account-glyph">♥</span><span>Favourites</span></a><?php if(!empty($u['is_admin'])):?><a href="<?=e(url('admin.php'))?>"><span class="mobile-account-glyph">♟</span><span>Admin</span></a><?php endif;?><?php else:?><a href="<?=e(url('login.php'))?>"><span class="mobile-account-glyph">↪</span><span>Login</span></a><a href="<?=e(url('register.php'))?>"><span class="mobile-account-glyph">＋</span><span>Register</span></a><?php endif;?>
    <a class="cart-link" href="<?=e(url('cart.php'))?>">Cart <span class="pill"><?=$cartCount?></span></a></nav></header><div id="pageContent" class="content">
    <?php
}
function layout_footer(): void { ?>
    </div>
    <footer class="site-footer"><nav class="legal-links" aria-label="Legal"><a href="<?=e(url('support'))?>">Customer support</a><a href="<?=e(url('terms.php'))?>">Terms &amp; licensing</a><a href="<?=e(url('refunds.php'))?>">Refund policy</a><a href="<?=e(url('privacy.php'))?>">Privacy</a><a href="<?=e(url('cookies.php'))?>">Cookies</a></nav></footer>
    </main></div>
    <div id="audioPlayer" class="audio-player" hidden>
      <div class="player-art-wrap"><img id="playerArt" src="<?=e(asset_url('assets/images/empty-art.svg'))?>" alt=""><span class="preview-chip">90 SEC PREVIEW</span></div>
      <div class="player-info">
        <div class="player-heading"><div class="player-meta"><strong id="playerTitle">Preview</strong><span id="playerArtist"></span></div><span id="playerState" class="player-state">READY</span></div>
        <div id="playerSpectrumWrap" class="player-spectrum-wrap" title="Click anywhere to seek through the preview">
          <canvas id="playerSpectrum" class="player-spectrum" aria-label="Audio spectrum visualizer"></canvas>
          <div class="player-spectrum-position" aria-hidden="true"><span id="playerSpectrumProgress"></span></div>
        </div>
        <div class="player-timeline"><span id="playerTime">0:00</span><div id="playerSeekTrack" class="player-seek-track"><div id="playerSeekFill" class="player-seek-fill" aria-hidden="true"></div><input id="playerSeek" class="player-seek" type="range" min="0" max="1000" value="0" step="1" aria-label="Preview position"></div><span id="playerDuration">1:30</span></div>
      </div>
      <div class="player-controls"><button id="playerBack" class="player-icon" aria-label="Restart preview" title="Restart">↶</button><button id="playerToggle" class="player-toggle" aria-label="Play or pause preview">▶</button><button id="playerMute" class="player-icon" aria-label="Mute preview" title="Mute">◕</button></div><button id="playerClose" class="player-close" type="button" aria-label="Close preview player" title="Close preview player">×</button>
    </div>
    <script src="<?=e(asset_url('assets/js/app.js?v=1.13.61'))?>"></script><script src="<?=e(asset_url('assets/js/player.js?v=1.13.61'))?>"></script></body></html><?php }

function track_artwork_path(array $t): ?string {
    return $t['track_artwork_path'] ?? $t['artwork_path'] ?? $t['release_artwork_path'] ?? null;
}
function track_artwork_url(array $t): string { return artwork_url(track_artwork_path($t)); }

// Previews are intentionally public, unlike purchased masters. Serving the generated
// M4A directly lets Apache/nginx handle HTTP byte ranges natively, which makes
// browser seeking much more reliable than proxying the audio through PHP.
function track_preview_url(array $t): string {
    $revision=rawurlencode(basename((string)($t['preview_path']??'')));
    return url('preview.php?id='.(int)($t['id'] ?? 0).'&v='.$revision);
}
function track_card(array $t): string {
    $art=track_artwork_url($t);
    $preview=track_preview_url($t);
    $mix=trim((string)($t['mix_name']??''));
    $credit=track_artist_credit($t);
    $meta=e($credit).($mix!==''?' · '.e($mix):'');
    $trackId=(int)($t['id']??0);
    $csrf=e(csrf_token());
    $info=e(track_public_url($t));
    $add=e(track_public_url($t));
    $u=user();
    $fav=$u && is_track_favourite($trackId);
    $favUrl=e(url('favourites.php'));
    $favMarkup=$u
        ? '<form method="post" action="'.$favUrl.'" class="favourite-form"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="track_id" value="'.$trackId.'"><input type="hidden" name="ajax" value="1"><button type="submit" class="square-link favourite-toggle '.($fav?'is-favourite':''). '" aria-label="'.($fav?'Remove':'Save').' '.e($t['title']).' '.($fav?'from':'to').' favourites" title="'.($fav?'Remove from':'Save to').' favourites">'.($fav?'♥':'♡').'</button></form>'
        : '<a class="square-link favourite-toggle" href="'.e(url('login.php?next='.rawurlencode('tracks/'.track_public_slug($t)).'&favourite='.$trackId)).'" aria-label="Log in to save '.e($t['title']).'" title="Log in to save">♡</a>';    return '<article class="release-card" data-favourite-track-id="'.$trackId.'"><div class="release-art"><img src="'.e($art).'" alt=""><button class="preview-fab play" data-preview="'.e($preview).'" data-track-id="'.$trackId.'" data-title="'.e($t['title']).'" data-artist="'.e($credit).'" data-art="'.e($art).'">▶</button></div><div class="release-info"><div><h3>'.e($t['title']).'</h3><p>'.$meta.'</p></div><div class="release-buy"><strong>'.money((int)$t['price_pence']).'</strong><div class="release-actions">'.$favMarkup.'<form method="post" action="'.$add.'" class="quick-add-form"><input type="hidden" name="csrf" value="'.$csrf.'"><input type="hidden" name="ajax" value="1"><button type="submit" class="square-link quick-add-button" aria-label="Add '.e($t['title']).' to cart" title="Add to cart">🛒</button></form><a class="square-link info-link" href="'.$info.'" aria-label="View '.e($t['title']).'" title="View track information">i</a></div></div></div></article>';
}
function audio_upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The audio file is larger than the PHP/Plesk upload limit.',
        UPLOAD_ERR_PARTIAL => 'The audio upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a WAV or MP3 master file.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is unavailable.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked the audio upload.',
        default => 'The audio upload failed.',
    };
}

function preview_time_to_seconds(string $value): float {
    $value = trim($value);
    if ($value === '') throw new RuntimeException('All three preview start positions are required.');
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,3}):(\d{2})(?:\.(\d{1,3}))?$|^(\d+)(?:\.(\d{1,3}))?$/', $value, $m)) {
        throw new RuntimeException('Preview times must use HH:MM:SS, MM:SS or seconds.');
    }
    if (str_contains($value, ':')) {
        $parts = explode(':', $value);
        if (count($parts) === 2) { $hours = 0; $minutes = (int)$parts[0]; $seconds = (float)$parts[1]; }
        else { $hours = (int)$parts[0]; $minutes = (int)$parts[1]; $seconds = (float)$parts[2]; }
        if ($minutes > 59 || $seconds >= 60) throw new RuntimeException('Preview time contains an invalid minute/second value.');
        $total = ($hours * 3600) + ($minutes * 60) + $seconds;
    } else {
        $total = (float)$value;
    }
    if ($total < 0) throw new RuntimeException('Preview start positions cannot be negative.');
    return $total;
}

function run_ffmpeg(array $args): void {
    global $config;
    $ffmpeg = $config['app']['ffmpeg_path'] ?? '/usr/bin/ffmpeg';
    if (!is_file($ffmpeg) || !is_executable($ffmpeg)) {
        throw new RuntimeException('ffmpeg is not available at '. $ffmpeg .'. Install it or set app.ffmpeg_path in config.php.');
    }
    if (!function_exists('exec')) throw new RuntimeException('PHP exec() is disabled, so previews cannot be generated from the web admin.');
    $cmd = escapeshellarg($ffmpeg);
    foreach ($args as $arg) $cmd .= ' ' . escapeshellarg((string)$arg);
    $output = [];
    $rc = 0;
    exec($cmd . ' 2>&1', $output, $rc);
    if ($rc !== 0) {
        $detail = trim(implode("\n", array_slice($output, -8)));
        error_log('' . site_name() . ': '.$detail);
        throw new RuntimeException('ffmpeg could not generate the preview. Check the selected preview times and the server log.');
    }
}

function recordstore_transition_effects(): array {
    global $config;
    $configured = trim((string)($config['app']['montage_fx_dir'] ?? ''));
    $dir = $configured === '' ? dirname(__DIR__).'/assets/montage' : $configured;
    if ($configured !== '' && !str_starts_with($dir, '/') && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $dir)) {
        $dir = dirname(__DIR__).'/'.ltrim($dir, '/\\');
    }
    $names = [
        'classic-backspin.wav',
        'transformer-stutter.wav',
        'montage-whoosh-scratch.wav',
        'vinyl-brake.wav',
    ];
    $effects = [];
    foreach ($names as $name) {
        $path = rtrim($dir, '/').'/'.$name;
        if (is_file($path) && is_readable($path)) $effects[] = $path;
    }
    return $effects;
}

function generate_preview_from_master(string $masterPath, array $starts): array {
    global $config;
    if (count($starts) !== 3) throw new RuntimeException('Choose exactly three preview start points.');
    if (!is_file($masterPath) || !is_readable($masterPath)) throw new RuntimeException('Stored master file is missing or unreadable.');

    $previewRoot = rtrim($config['paths']['previews'], '/');
    if (!is_dir($previewRoot) && !mkdir($previewRoot, 0755, true)) throw new RuntimeException('Preview directory is unavailable.');
    if (!is_writable($previewRoot)) throw new RuntimeException('Preview directory is not writable by PHP-FPM.');

    $token = bin2hex(random_bytes(16));
    $previewName = $token.'-preview.m4a';
    $previewPath = $previewRoot.'/'.$previewName;
    $tmp = [];

    try {
        $effects = recordstore_transition_effects();
        $useFx = count($effects) > 0;
        $fxLength = 0.65;
        $sectionLength = $useFx ? ((90.0 - ($fxLength * 2)) / 3) : 30.0;

        $segments = [];
        foreach (array_values($starts) as $i => $start) {
            $seconds = preview_time_to_seconds((string)$start);
            $segment = sys_get_temp_dir().'/afd_'.bin2hex(random_bytes(8)).'_'.($i + 1).'.mp3';
            $tmp[] = $segment;
            $segments[] = $segment;
            run_ffmpeg(['-hide_banner','-loglevel','error','-y','-ss',number_format($seconds,3,'.',''),'-i',$masterPath,'-t',number_format($sectionLength,3,'.',''),'-vn','-codec:a','libmp3lame','-b:a','192k','-ar','44100','-ac','2',$segment]);
        }

        $concatParts = $segments;
        if ($useFx) {
            shuffle($effects);
            $chosen = [$effects[0], $effects[count($effects) > 1 ? 1 : 0]];
            $fxFiles = [];
            foreach ($chosen as $i => $fxSource) {
                $fx = sys_get_temp_dir().'/afd_'.bin2hex(random_bytes(8)).'_fx'.($i + 1).'.mp3';
                $tmp[] = $fx;
                $fxFiles[] = $fx;
                run_ffmpeg([
                    '-hide_banner','-loglevel','error','-y','-i',$fxSource,
                    '-t',number_format($fxLength,2,'.',''),'-vn',
                    '-af','afade=t=in:st=0:d=0.015,afade=t=out:st=0.55:d=0.10,loudnorm=I=-14:TP=-1.5:LRA=7',
                    '-codec:a','libmp3lame','-b:a','192k','-ar','44100','-ac','2',$fx
                ]);
            }
            $concatParts = [$segments[0], $fxFiles[0], $segments[1], $fxFiles[1], $segments[2]];
        }

        $concat = sys_get_temp_dir().'/afd_'.bin2hex(random_bytes(8)).'.txt';
        $tmp[] = $concat;
        $lines = array_map(static fn($p) => "file '".str_replace("'", "'\\''", $p)."'", $concatParts);
        file_put_contents($concat, implode("\n", $lines)."\n");
        run_ffmpeg(['-hide_banner','-loglevel','error','-y','-f','concat','-safe','0','-i',$concat,'-vn','-codec:a','aac','-b:a','192k','-ar','44100','-ac','2','-t','90','-af','afade=t=in:st=0:d=5,afade=t=out:st=85:d=5','-movflags','+faststart','-metadata','title='.site_name().' 90 Second Preview',$previewPath]);
        @chmod($previewPath, 0644);
    } catch (Throwable $e) {
        @unlink($previewPath);
        foreach ($tmp as $f) @unlink($f);
        throw $e;
    }
    foreach ($tmp as $f) @unlink($f);

    return [
        'preview_path' => $previewName,
        'preview_full_path' => $previewPath,
    ];
}

function regenerate_preview_from_stored_master(string $masterRelativePath, array $starts): array {
    global $config;
    $masterRoot = realpath(rtrim($config['paths']['masters'], '/'));
    if (!$masterRoot) throw new RuntimeException('Private masters directory is unavailable.');
    $candidate = realpath($masterRoot.'/'.ltrim($masterRelativePath, '/'));
    if (!$candidate || !str_starts_with($candidate, $masterRoot.DIRECTORY_SEPARATOR) || !is_file($candidate)) {
        throw new RuntimeException('The stored master for this track could not be found.');
    }
    return generate_preview_from_master($candidate, $starts);
}

function store_uploaded_manual_preview(array $file): array {
    global $config;
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE); if($error!==UPLOAD_ERR_OK) throw new RuntimeException('A manual preview upload is required when automatic preview generation is disabled.');
    $size=(int)($file['size']??0); $max=(int)($config['app']['preview_upload_max_bytes']??67108864); if($size<1||$size>$max) throw new RuntimeException('Preview must be smaller than '.round($max/1048576).' MB.'); if(!is_uploaded_file($file['tmp_name']??'')) throw new RuntimeException('Invalid preview upload.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $allowed=['audio/mpeg'=>'mp3','audio/mp3'=>'mp3','audio/mp4'=>'m4a','video/mp4'=>'m4a','audio/x-m4a'=>'m4a','audio/aac'=>'aac','audio/wav'=>'wav','audio/x-wav'=>'wav']; if(!isset($allowed[$mime])) throw new RuntimeException('Preview must be an MP3, M4A/AAC or WAV file.');
    $root=rtrim($config['paths']['previews'],'/'); if(!is_dir($root)&&!mkdir($root,0755,true)) throw new RuntimeException('Preview directory is unavailable.'); if(!is_writable($root)) throw new RuntimeException('Preview directory is not writable by PHP-FPM.'); $name=bin2hex(random_bytes(16)).'-preview.'.$allowed[$mime]; $path=$root.'/'.$name; if(!move_uploaded_file($file['tmp_name'],$path)) throw new RuntimeException('Could not move the preview into public storage.'); @chmod($path,0644); return ['preview_path'=>$name,'preview_full_path'=>$path];
}

function store_uploaded_master_and_preview(array $file, array $starts, ?array $manualPreview=null): array {
    global $config;
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException(audio_upload_error_message($error));

    $max = (int)($config['app']['master_upload_max_bytes'] ?? 536870912);
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $max) throw new RuntimeException('Master must be smaller than '.round($max / 1048576).' MB.');
    if (!is_uploaded_file($file['tmp_name'] ?? '')) throw new RuntimeException('Invalid audio upload.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed = [
        'audio/mpeg' => ['mp3', 'audio/mpeg'],
        'audio/mp3' => ['mp3', 'audio/mpeg'],
        'audio/wav' => ['wav', 'audio/wav'],
        'audio/x-wav' => ['wav', 'audio/wav'],
        'audio/vnd.wave' => ['wav', 'audio/wav'],
        'audio/x-pn-wav' => ['wav', 'audio/wav'],
    ];
    if (!isset($allowed[$mime])) throw new RuntimeException('Master must be an MP3 or WAV file. Detected type: '.($mime ?: 'unknown'));
    [$ext, $downloadMime] = $allowed[$mime];

    if (count($starts) !== 3) throw new RuntimeException('Choose exactly three preview start points.');

    $masterRoot = rtrim($config['paths']['masters'], '/');
    if (!is_dir($masterRoot) && !mkdir($masterRoot, 0750, true)) throw new RuntimeException('Private masters directory is unavailable.');
    if (!is_writable($masterRoot)) throw new RuntimeException('Private masters directory is not writable by PHP-FPM.');

    $token = bin2hex(random_bytes(16));
    $masterName = $token.'.'.$ext;
    $masterPath = $masterRoot.'/'.$masterName;
    if (!move_uploaded_file($file['tmp_name'], $masterPath)) throw new RuntimeException('Could not move the master into private storage.');
    @chmod($masterPath, 0640);

    try {
        $preview = (($config['app']['preview_mode'] ?? 'auto') === 'manual') ? store_uploaded_manual_preview($manualPreview ?? []) : generate_preview_from_master($masterPath, $starts);
    } catch (Throwable $e) {
        @unlink($masterPath);
        throw $e;
    }

    $original = basename((string)($file['name'] ?? ('track.'.$ext)));
    $original = preg_replace('/[^A-Za-z0-9 _().\-]+/', '', $original) ?: ('track.'.$ext);
    return [
        'master_path' => $masterName,
        'preview_path' => $preview['preview_path'],
        'file_name' => $original,
        'mime_type' => $downloadMime,
        'file_size' => filesize($masterPath) ?: $size,
        'master_full_path' => $masterPath,
        'preview_full_path' => $preview['preview_full_path'],
    ];
}


function store_setting(string $key, ?string $default=null): ?string {
    global $pdo;
    try {
        $st=$pdo->prepare('SELECT setting_value FROM store_settings WHERE setting_key=?');
        $st->execute([$key]);
        $v=$st->fetchColumn();
        return $v===false ? $default : (string)$v;
    } catch (Throwable $e) {
        return $default;
    }
}
function set_store_setting(string $key, string $value): void {
    global $pdo;
    $pdo->prepare('INSERT INTO store_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute([$key,$value]);
}


function mail_transport(): string {
    $mode = strtolower((string)store_setting('mail_transport','local'));
    return in_array($mode,['local','smtp'],true) ? $mode : 'local';
}
function mail_settings(): array {
    global $config;
    $host=(string)store_setting('smtp_host','');
    $port=(int)(store_setting('smtp_port','587') ?: 587);
    $security=strtolower((string)store_setting('smtp_security','tls'));
    if(!in_array($security,['none','tls','ssl'],true)) $security='tls';
    return [
        'transport'=>mail_transport(),
        'from_name'=>(string)(store_setting('mail_from_name',site_name()) ?: site_name()),
        'from_email'=>(string)(store_setting('mail_from_email','') ?: ''),
        'smtp_host'=>$host,
        'smtp_port'=>$port,
        'smtp_security'=>$security,
        'smtp_username'=>(string)(store_setting('smtp_username','') ?: ''),
        'smtp_password'=>(string)(store_setting('smtp_password','') ?: ''),
    ];
}
function mail_header_value(string $value): string { return trim(str_replace(["\r","\n"],' ',$value)); }
function smtp_read_response($fp): array {
    $lines=[];$code=0;
    while(($line=fgets($fp,4096))!==false){$lines[]=$line;if(preg_match('/^(\d{3})([ -])/',$line,$m)){$code=(int)$m[1];if($m[2]===' ')break;}}
    return [$code,implode('',$lines)];
}
function smtp_command($fp,string $command,array $okCodes): void {
    fwrite($fp,$command."\r\n");[$code,$resp]=smtp_read_response($fp);
    if(!in_array($code,$okCodes,true)) throw new RuntimeException('SMTP error: '.trim($resp));
}
function smtp_send_message(array $settings,string $to,string $subject,string $body,?string $htmlBody=null): void {
    $host=$settings['smtp_host'];$port=(int)$settings['smtp_port'];$security=$settings['smtp_security'];if($host===''||$port<1)throw new RuntimeException('SMTP host and port are not configured.');
    $remote=($security==='ssl'?'ssl://':'').$host.':'.$port;$ctx=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);$errno=0;$errstr='';$fp=@stream_socket_client($remote,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$ctx);if(!$fp)throw new RuntimeException('Could not connect to SMTP server: '.$errstr);stream_set_timeout($fp,20);
    try{[$code,$resp]=smtp_read_response($fp);if($code!==220)throw new RuntimeException('SMTP greeting failed: '.trim($resp));$ehlo=parse_url(url(''),PHP_URL_HOST)?:'recordstore.local';smtp_command($fp,'EHLO '.$ehlo,[250]);if($security==='tls'){smtp_command($fp,'STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('Could not start SMTP TLS encryption.');smtp_command($fp,'EHLO '.$ehlo,[250]);}if($settings['smtp_username']!==''){smtp_command($fp,'AUTH LOGIN',[334]);smtp_command($fp,base64_encode($settings['smtp_username']),[334]);smtp_command($fp,base64_encode($settings['smtp_password']),[235]);}
        $from=mail_header_value($settings['from_email']);smtp_command($fp,'MAIL FROM:<'.$from.'>',[250]);smtp_command($fp,'RCPT TO:<'.mail_header_value($to).'>',[250,251]);smtp_command($fp,'DATA',[354]);$headers=['From: '.mail_header_value($settings['from_name']).' <'.$from.'>','To: <'.mail_header_value($to).'>','Subject: '.mail_header_value($subject),'Date: '.date(DATE_RFC2822),'MIME-Version: 1.0','Message-ID: <'.bin2hex(random_bytes(12)).'@'.$ehlo.'>'];$text=str_replace("\n","\r\n",str_replace(["\r\n","\r"],"\n",$body));
        if($htmlBody!==null&&$htmlBody!==''){$boundary='=_'.bin2hex(random_bytes(12));$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$html=str_replace("\n","\r\n",str_replace(["\r\n","\r"],"\n",$htmlBody));$data=implode("\r\n",$headers)."\r\n\r\n--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n--$boundary--";}else{$headers[]='Content-Type: text/plain; charset=UTF-8';$headers[]='Content-Transfer-Encoding: 8bit';$data=implode("\r\n",$headers)."\r\n\r\n$text";}
        $data=str_replace("\n.","\n..",$data);fwrite($fp,$data."\r\n.\r\n");[$code,$resp]=smtp_read_response($fp);if($code!==250)throw new RuntimeException('SMTP message was rejected: '.trim($resp));@fwrite($fp,"QUIT\r\n");
    }finally{fclose($fp);}
}function send_store_mail(string $to,string $subject,string $body,?string $htmlBody=null): void {
    $settings=mail_settings();if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid recipient email address.');if(!filter_var($settings['from_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Configure a valid From email address in Admin → Mail first.');
    if($settings['transport']==='smtp'){smtp_send_message($settings,$to,$subject,$body,$htmlBody);return;}
    $headers=['From: '.mail_header_value($settings['from_name']).' <'.mail_header_value($settings['from_email']).'>','Reply-To: '.mail_header_value($settings['from_email']),'MIME-Version: 1.0'];
    if($htmlBody!==null&&$htmlBody!==''){$boundary='=_'.bin2hex(random_bytes(12));$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';$message="--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\n","\r\n",str_replace(["\r\n","\r"],"\n",$body))."\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n".str_replace("\n","\r\n",str_replace(["\r\n","\r"],"\n",$htmlBody))."\r\n--$boundary--";}else{$headers[]='Content-Type: text/plain; charset=UTF-8';$message=$body;}
    if(!mail($to,mail_header_value($subject),$message,implode("\r\n",$headers)))throw new RuntimeException('The local mail server did not accept the message.');try{$pdo->prepare('INSERT INTO mail_log(recipient_email,template_key,subject,status) VALUES(?,?,?,?)')->execute([$to,null,$subject,'sent']);}catch(Throwable $ignored){}
}function otp_cleanup(): void { global $pdo; try{$pdo->exec("DELETE FROM auth_otp_challenges WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR used_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");}catch(Throwable $e){} }
function request_email_otp(string $email,string $purpose,?string $displayName=null): bool {
    global $pdo,$config;
    $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');
    if(!in_array($purpose,['login','register'],true))throw new RuntimeException('Invalid verification request.');
    otp_cleanup();$userId=null;
    $st=$pdo->prepare('SELECT id,disabled_at,banned_at FROM users WHERE email=?');$st->execute([$email]);$existingRow=$st->fetch();$existing=$existingRow['id']??null;
    if($purpose==='register' && $existingRow&&$existingRow['banned_at']) throw new RuntimeException('This email address cannot be registered.');if($purpose==='register' && $existing) throw new RuntimeException('That email address is already registered. Log in instead.');
    if($purpose==='login' && (!$existing||$existingRow['disabled_at']||$existingRow['banned_at'])) return false; // deliberately silent to the UI
    if($existing)$userId=(int)$existing;
    $st=$pdo->prepare("SELECT created_at FROM auth_otp_challenges WHERE email=? AND purpose=? ORDER BY id DESC LIMIT 1");$st->execute([$email,$purpose]);$last=$st->fetchColumn();
    if($last && strtotime((string)$last)>time()-60) throw new RuntimeException('A code was sent recently. Please wait a minute before requesting another.');
    $code=(string)random_int(100000,999999);$hash=password_hash($code,PASSWORD_DEFAULT);$expires=date('Y-m-d H:i:s',time()+600);
    $pdo->prepare('INSERT INTO auth_otp_challenges(email,user_id,purpose,display_name,code_hash,expires_at) VALUES(?,?,?,?,?,?)')->execute([$email,$userId,$purpose,$displayName,$hash,$expires]);
    $name=site_name();
    $action=$purpose==='register'?'verify your new account':'sign in';
    $body="Your {$name} verification code is:\n\n{$code}\n\nUse this code to {$action}. It expires in 10 minutes.\n\nIf you did not request this code, you can ignore this email.";
    send_store_mail($email,$name.' verification code',$body);
    return true;
}
function verify_email_otp(string $email,string $purpose,string $code): ?array {
    global $pdo;
    $email=strtolower(trim($email));$code=trim($code);
    if(!preg_match('/^\d{6}$/',$code))return null;
    $st=$pdo->prepare("SELECT * FROM auth_otp_challenges WHERE email=? AND purpose=? AND used_at IS NULL AND expires_at>NOW() ORDER BY id DESC LIMIT 1");$st->execute([$email,$purpose]);$row=$st->fetch();
    if(!$row || (int)$row['attempts']>=5)return null;
    if(!password_verify($code,$row['code_hash'])){$pdo->prepare('UPDATE auth_otp_challenges SET attempts=attempts+1 WHERE id=?')->execute([$row['id']]);return null;}
    $pdo->prepare('UPDATE auth_otp_challenges SET used_at=NOW() WHERE id=?')->execute([$row['id']]);return $row;
}

function payment_gateway(): string { return store_setting('payment_gateway','paypal') ?: 'paypal'; }
function paypal_mode(): string { $m=store_setting('paypal_mode','sandbox'); return $m==='live'?'live':'sandbox'; }
function paypal_config(): array {
    global $config;
    $mode=paypal_mode();
    $pp=$config['payments']['paypal'][$mode] ?? [];
    return [
        'mode'=>$mode,
        'base_url'=>$mode==='live'?'https://api-m.paypal.com':'https://api-m.sandbox.paypal.com',
        'client_id'=>(string)($pp['client_id']??''),
        'client_secret'=>(string)($pp['client_secret']??''),
        'webhook_id'=>(string)($pp['webhook_id']??''),
        'currency'=>(string)($config['payments']['currency']??'GBP'),
    ];
}
function paypal_credentials_ready(): bool {
    $c=paypal_config();
    return $c['client_id']!=='' && !str_starts_with($c['client_id'],'REPLACE_') && $c['client_secret']!=='' && !str_starts_with($c['client_secret'],'REPLACE_');
}
function paypal_access_token(): string {
    $c=paypal_config();
    if(!paypal_credentials_ready()) throw new RuntimeException('PayPal credentials are not configured for '.$c['mode'].' mode.');
    if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required for PayPal.');
    $ch=curl_init($c['base_url'].'/v1/oauth2/token');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_USERPWD=>$c['client_id'].':'.$c['client_secret'],CURLOPT_HTTPHEADER=>['Accept: application/json','Accept-Language: en_GB','Content-Type: application/x-www-form-urlencoded'],CURLOPT_POSTFIELDS=>'grant_type=client_credentials',CURLOPT_TIMEOUT=>30]);
    $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
    if($raw===false||$status<200||$status>=300){error_log('PayPal OAuth error '.$status.' '.$err.' '.$raw);throw new RuntimeException('Could not authenticate with PayPal.');}
    $tok=json_decode((string)$raw,true);$access=(string)($tok['access_token']??'');if($access==='')throw new RuntimeException('PayPal did not return an access token.');return $access;
}
function paypal_verify_webhook_raw(string $rawEvent, array $headers): bool {
    $c=paypal_config();$access=paypal_access_token();
    $fields=[
      'auth_algo'=>(string)($headers['auth_algo']??''),'cert_url'=>(string)($headers['cert_url']??''),'transmission_id'=>(string)($headers['transmission_id']??''),'transmission_sig'=>(string)($headers['transmission_sig']??''),'transmission_time'=>(string)($headers['transmission_time']??''),'webhook_id'=>$c['webhook_id']
    ];
    $parts=[];foreach($fields as $k=>$v)$parts[]=json_encode($k).':'.json_encode($v,JSON_UNESCAPED_SLASHES);
    $payload='{'.implode(',',$parts).',"webhook_event":'.$rawEvent.'}';
    $ch=curl_init($c['base_url'].'/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$access,'Content-Type: application/json','Accept: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_TIMEOUT=>45]);
    $resp=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
    if($resp===false||$status<200||$status>=300){error_log('PayPal webhook verification error '.$status.' '.$err.' '.$resp);return false;}
    $data=json_decode((string)$resp,true);return ($data['verification_status']??'')==='SUCCESS';
}
function paypal_request(string $method, string $path, ?array $body=null, ?string $requestId=null): array {
    $c=paypal_config();
    $access=paypal_access_token();
    $headers=['Authorization: Bearer '.$access,'Content-Type: application/json','Accept: application/json'];
    if($requestId) $headers[]='PayPal-Request-Id: '.$requestId;
    $ch=curl_init($c['base_url'].$path);
    $opts=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>45];
    if($body!==null)$opts[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_SLASHES);
    curl_setopt_array($ch,$opts); $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE); $err=curl_error($ch); curl_close($ch);
    $data=json_decode((string)$raw,true);
    if($raw===false||$status<200||$status>=300){error_log('PayPal API error '.$status.' '.$err.' '.$raw);$msg=is_array($data)&&!empty($data['message'])?$data['message']:'PayPal API request failed.';throw new RuntimeException($msg);}
    return is_array($data)?$data:[];
}
function paypal_money_value(int $pence): string { return number_format($pence/100,2,'.',''); }
function paypal_refund_capture(string $captureId,int $orderId,int $totalPence): array {
    if($captureId===''||$orderId<1||$totalPence<1)throw new RuntimeException('The PayPal capture details are incomplete.');
    $c=paypal_config();
    return paypal_request('POST','/v2/payments/captures/'.rawurlencode($captureId).'/refund',['amount'=>['value'=>paypal_money_value($totalPence),'currency_code'=>$c['currency']],'note_to_payer'=>'Refund from '.site_name().' for order #'.$orderId],store_identifier(site_name()).'-refund-'.$orderId);
}
function payout_host_percent(): float { $value=(float)store_setting('payout_host_percent','10');return max(0,min(100,$value)); }
function payout_automatic_enabled(): bool { return store_setting('payout_automatic_enabled','0')==='1'; }
function payout_paypal_confirmed(): bool { return store_setting('payout_paypal_confirmed','0')==='1'; }
function payout_items_for_artist(int $artistId,string $start,string $end): array {
    global $pdo;
    if($artistId<1||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))return [];
    $st=$pdo->prepare("SELECT oi.id order_item_id,oi.unit_price_pence,t.title,t.mix_name,COUNT(DISTINCT all_ta.artist_id) artist_count,MIN(mine_ta.sort_order) artist_sort_order FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.status='paid' AND DATE(o.paid_at) BETWEEN ? AND ? JOIN tracks t ON t.id=oi.track_id JOIN track_artists mine_ta ON mine_ta.track_id=t.id AND mine_ta.artist_id=? JOIN track_artists all_ta ON all_ta.track_id=t.id WHERE NOT EXISTS (SELECT 1 FROM artist_payout_items api WHERE api.order_item_id=oi.id AND api.artist_id=?) GROUP BY oi.id,oi.unit_price_pence,t.title,t.mix_name,o.paid_at ORDER BY o.paid_at,oi.id");
    $st->execute([$start,$end,$artistId,$artistId]);$percent=payout_host_percent();$items=[];$gross=0;$host=0;$share=0;
    foreach($st->fetchAll() as $row){$itemGross=(int)$row['unit_price_pence'];$itemHost=(int)round($itemGross*$percent/100);$pool=max(0,$itemGross-$itemHost);$count=max(1,(int)$row['artist_count']);$base=intdiv($pool,$count);$remainder=$pool-($base*$count);$itemShare=$base+((int)$row['artist_sort_order']===0?$remainder:0);$items[]=['order_item_id'=>(int)$row['order_item_id'],'title'=>(string)$row['title'],'mix_name'=>(string)($row['mix_name']??''),'gross_pence'=>$itemGross,'host_fee_pence'=>$itemHost,'artist_share_pence'=>$itemShare];$gross+=$itemGross;$host+=$itemHost;$share+=$itemShare;}
    return ['items'=>$items,'gross_pence'=>$gross,'host_fee_pence'=>$host,'payout_pence'=>$share,'host_percent'=>$percent];
}
function payout_items_for_user(int $userId,string $start,string $end): array {
    global $pdo;
    if($userId<1||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end))return [];
    $st=$pdo->prepare("SELECT oi.id order_item_id,oi.unit_price_pence,t.title,t.mix_name,mine_ta.artist_id,mine_ta.sort_order artist_sort_order,mine_a.name artist_name,COUNT(DISTINCT all_ta.artist_id) artist_count FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.status='paid' AND DATE(o.paid_at) BETWEEN ? AND ? JOIN tracks t ON t.id=oi.track_id JOIN track_artists mine_ta ON mine_ta.track_id=t.id JOIN artists mine_a ON mine_a.id=mine_ta.artist_id AND mine_a.owner_user_id=? JOIN track_artists all_ta ON all_ta.track_id=t.id WHERE NOT EXISTS (SELECT 1 FROM artist_payout_items api WHERE api.order_item_id=oi.id AND api.artist_id=mine_ta.artist_id) GROUP BY oi.id,oi.unit_price_pence,t.title,t.mix_name,mine_ta.artist_id,mine_ta.sort_order,mine_a.name,o.paid_at ORDER BY o.paid_at,oi.id,mine_ta.sort_order");
    $st->execute([$start,$end,$userId]);$percent=payout_host_percent();$items=[];$gross=0;$host=0;$share=0;$seen=[];
    foreach($st->fetchAll() as $row){$itemId=(int)$row['order_item_id'];$itemGross=(int)$row['unit_price_pence'];$itemHost=(int)round($itemGross*$percent/100);$pool=max(0,$itemGross-$itemHost);$count=max(1,(int)$row['artist_count']);$base=intdiv($pool,$count);$itemShare=$base+((int)$row['artist_sort_order']===0?$pool-($base*$count):0);$items[]=['order_item_id'=>$itemId,'artist_id'=>(int)$row['artist_id'],'artist_name'=>(string)$row['artist_name'],'title'=>(string)$row['title'],'mix_name'=>(string)($row['mix_name']??''),'gross_pence'=>$itemGross,'host_fee_pence'=>$itemHost,'artist_share_pence'=>$itemShare];if(!isset($seen[$itemId])){$gross+=$itemGross;$host+=$itemHost;$seen[$itemId]=true;}$share+=$itemShare;}
    return ['items'=>$items,'gross_pence'=>$gross,'host_fee_pence'=>$host,'payout_pence'=>$share,'host_percent'=>$percent];
}
function send_artist_payout_reconciliation(string $recipient,string $artistName,string $start,string $end,int $payoutPence,array $items,string $status='paid'): void {
    if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)) return;
    $lines=['Hello '.$artistName.',','',site_name().' has marked your artist payout as '.$status.'.','Period: '.$start.' to '.$end,'','Sales reconciliation:'];
    foreach($items as $sale) $lines[]=(string)$sale['title'].(!empty($sale['mix_name'])?' ('.$sale['mix_name'].')':'').' — '.money((int)$sale['artist_share_pence']);
    $lines[]='';$lines[]='Total: '.money($payoutPence);$lines[]='This reconciliation is based on paid order items, after the host charge of '.number_format(payout_host_percent(),2).'% and the equal split between credited artists.';
    send_store_mail($recipient,site_name().' artist payout reconciliation',implode("\n",$lines));
}
function paypal_create_payout(array $items,string $batchId,string $currency='GBP'): array {
    $payload=['sender_batch_header'=>['sender_batch_id'=>$batchId,'email_subject'=>site_name().' artist payout','email_message'=>'Your artist payout from '.site_name().' has been sent.'],'items'=>[]];
    foreach($items as $item){$email=strtolower(trim((string)($item['paypal_email']??'')));if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Every payout recipient must have a valid PayPal email address.');$payload['items'][]=['recipient_type'=>'EMAIL','receiver'=>$email,'note'=>substr(site_name().' artist payout for '.$item['artist_name'],0,1000),'sender_item_id'=>'artist-'.$item['artist_id'].'-'.$item['payout_id'],'amount'=>['currency'=>$currency,'value'=>paypal_money_value((int)$item['artist_share_pence'])]];}
    try{$response=paypal_request('POST','/v1/payments/payouts',$payload,$batchId);}catch(Throwable $e){global $pdo;foreach($items as $item)if(!empty($item['payout_id']))$pdo->prepare('DELETE FROM artist_payout_items WHERE payout_id=?')->execute([(int)$item['payout_id']]);throw $e;}
    global $pdo;foreach($items as &$item){$pid=(int)($item['payout_id']??0);if($pid){$st=$pdo->prepare('SELECT period_start,period_end FROM artist_payouts WHERE id=?');$st->execute([$pid]);$period=$st->fetch();$item['period_start']=$period['period_start']??'';$item['period_end']=$period['period_end']??'';$st=$pdo->prepare('SELECT t.title,t.mix_name,api.artist_share_pence FROM artist_payout_items api JOIN order_items oi ON oi.id=api.order_item_id JOIN tracks t ON t.id=oi.track_id WHERE api.payout_id=? ORDER BY api.order_item_id');$st->execute([$pid]);$item['reconciliation_items']=$st->fetchAll();$item['host_percent']=payout_host_percent();}}unset($item);
    foreach($items as $item){$recipient=strtolower(trim((string)($item['paypal_email']??'')));if(!filter_var($recipient,FILTER_VALIDATE_EMAIL))continue;$lines=['Hello '.(string)($item['artist_name']??'artist').',','',site_name().' has submitted your payout to PayPal.','Period: '.(string)($item['period_start']??'').' to '.(string)($item['period_end']??''),'','Sales reconciliation:'];foreach((array)($item['reconciliation_items']??[]) as $sale)$lines[]=(string)$sale['title'].(!empty($sale['mix_name'])?' ('.$sale['mix_name'].')':'').' — '.money((int)$sale['artist_share_pence']);$lines[]='';$lines[]='Total paid: '.money((int)($item['artist_share_pence']??0));$lines[]='This reconciliation is based on paid order items, after the host charge of '.number_format((float)($item['host_percent']??0),2).'% and the equal split between credited artists.';try{send_store_mail($recipient,site_name().' artist payout reconciliation',implode("\n",$lines));}catch(Throwable $ignored){error_log('Artist payout reconciliation email failed: '.$ignored->getMessage());}}
    return $response;
}
function vat_rate(): float { $rate=(float)store_setting('vat_rate','0');return max(0,min(100,$rate)); }
function create_pending_order_from_cart(int $userId): array {
    global $pdo,$config;
    $ids=array_map('intval',array_keys(cart())); if(!$ids)throw new RuntimeException('Your cart is empty.');
    $ph=implode(',',array_fill(0,count($ids),'?'));
    $st=$pdo->prepare("SELECT id,price_pence FROM tracks WHERE id IN ($ph) AND active=1");$st->execute($ids);$tracks=$st->fetchAll();
    if(count($tracks)!==count($ids))throw new RuntimeException('One or more tracks in your cart are no longer available.');
    $subtotal=array_sum(array_map(fn($t)=>(int)$t['price_pence'],$tracks));
    $discount=cart_discount_quote($subtotal,$userId);if(!empty($_SESSION['discount_code'])&&!$discount['ok'])throw new RuntimeException($discount['message']);
    $discountCode=$discount['ok']?$discount['code']:null;$discountPence=(int)($discount['discount_pence']??0);$netTotal=max(0,$subtotal-$discountPence);$vatRate=vat_rate();$vatPence=(int)round($netTotal*$vatRate/100);$total=$netTotal+$vatPence;
    $pdo->beginTransaction();
    try{
        if($discountCode){$lock=$pdo->prepare('UPDATE discount_codes SET used_count=used_count+1 WHERE code=? AND active=1 AND (usage_limit IS NULL OR used_count<usage_limit)');$lock->execute([$discountCode]);if($lock->rowCount()!==1)throw new RuntimeException('That discount code is no longer available.');}
        $pdo->prepare("INSERT INTO orders(user_id,status,payment_provider,subtotal_pence,discount_code,discount_pence,vat_rate,vat_pence,total_pence) VALUES(?,'pending','paypal',?,?,?, ?,?,?)")->execute([$userId,$subtotal,$discountCode,$discountPence,$vatRate,$vatPence,$total]);
        $oid=(int)$pdo->lastInsertId();$ins=$pdo->prepare('INSERT INTO order_items(order_id,track_id,unit_price_pence,download_limit) VALUES(?,?,?,?)');
        foreach($tracks as $t)$ins->execute([$oid,(int)$t['id'],(int)$t['price_pence'],(int)$config['app']['download_limit_default']]);
        $pdo->commit();return ['id'=>$oid,'total_pence'=>$total,'tracks'=>$tracks];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function mark_order_paid_from_paypal(int $orderId, string $paypalOrderId, string $captureId, array $payload): void {
    global $pdo;
    $pdo->beginTransaction();
    try{
        $st=$pdo->prepare('SELECT status FROM orders WHERE id=? FOR UPDATE');$st->execute([$orderId]);$status=$st->fetchColumn();
        if($status===false)throw new RuntimeException('Local order not found.');
        if(in_array($status,['pending','failed'],true)){
            $pdo->prepare("UPDATE orders SET status='paid',payment_provider='paypal',payment_reference=?,gateway_capture_id=?,gateway_payload=?,paid_at=COALESCE(paid_at,NOW()) WHERE id=?")->execute([$paypalOrderId,$captureId,json_encode($payload,JSON_UNESCAPED_SLASHES),$orderId]);
        }elseif($status==='paid'){
            $pdo->prepare("UPDATE orders SET payment_provider='paypal',payment_reference=?,gateway_capture_id=?,gateway_payload=?,paid_at=COALESCE(paid_at,NOW()) WHERE id=?")->execute([$paypalOrderId,$captureId,json_encode($payload,JSON_UNESCAPED_SLASHES),$orderId]);
        }else{
            // A manual cancelled/refunded state wins locally. Keep the capture metadata so Admin can see that money was captured without silently restoring downloads.
            $pdo->prepare("UPDATE orders SET payment_provider='paypal',payment_reference=?,gateway_capture_id=?,gateway_payload=?,paid_at=COALESCE(paid_at,NOW()) WHERE id=?")->execute([$paypalOrderId,$captureId,json_encode($payload,JSON_UNESCAPED_SLASHES),$orderId]);
        }
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    notify_order_mail($orderId,'order_paid');
}
function paypal_capture_details(array $captureResponse): array {
    $capture=$captureResponse['purchase_units'][0]['payments']['captures'][0]??[];
    return ['id'=>(string)($capture['id']??''),'status'=>(string)($capture['status']??''),'currency'=>(string)($capture['amount']['currency_code']??''),'value'=>(string)($capture['amount']['value']??'')];
}

function mail_template_defaults(): array {
    global $config;
    $site=site_name();
    return [
        'order_paid'=>['label'=>'Successful order','enabled'=>1,'subject'=>'Order {{order_id}} confirmed','body'=>"Hello {{customer_name}},\n\nThank you for your order with {{site_name}}.\n\nOrder: {{order_id}}\nTotal: {{total}}\nPayment status: Paid\n\nYour downloads are available from your account.\n\n{{site_name}}"],
        'order_status'=>['label'=>'Payment/order status update','enabled'=>1,'subject'=>'Order {{order_id}} status update','body'=>"Hello {{customer_name}},\n\nYour order {{order_id}} status is now: {{status}}.\n\nTotal: {{total}}\n\n{{site_name}}"],
        'order_cancelled'=>['label'=>'Cancelled order','enabled'=>1,'subject'=>'Order {{order_id}} cancelled','body'=>"Hello {{customer_name}},\n\nYour order {{order_id}} has been cancelled. No further download access is available for this order.\n\n{{site_name}}"],
'new_releases'=>['label'=>'New release announcement','enabled'=>1,'subject'=>'New music from {{site_name}}','body'=>"Hello {{customer_name}},\n\nHere are the latest releases from {{site_name}}:\n\n{{release_list}}\n\nBrowse the store: {{store_url}}\n\n{{site_name}}"],
'abandoned_cart'=>['label'=>'Abandoned basket reminder','enabled'=>1,'subject'=>'Your {{site_name}} basket is waiting','body'=>"Hello {{customer_name}},\n\nYou left these tracks in your basket:\n\n{{cart_items}}\n\nReturn to your basket: {{cart_url}}\n\n{{site_name}}"],
    ];
}
function mail_templates(): array {
    global $pdo;
    $defaults=mail_template_defaults();$rows=[];
    try{$st=$pdo->query('SELECT template_key,label,enabled,subject,body,html_body FROM mail_templates');foreach($st->fetchAll() as $row)$rows[$row['template_key']]=$row;}catch(Throwable $e){}
    foreach($defaults as $key=>$default)$rows[$key]=array_merge($default,$rows[$key]??[],['template_key'=>$key]);
    return $rows;
}
function mail_html_sanitize(string $html): string {
    $html=preg_replace('/<\/?(script|style|iframe|object|embed|form)(?:\s[^>]*)?>.*?<\/\1\s*>/is','',$html)??'';
    $html=preg_replace("/\s+on[a-z]+\s*=\s*(\"[^\"]*\"|'[^']*'|[^\s>]+)/i",'',$html)??$html;
    $html=preg_replace("/((?:href|src)\s*=\s*[\"'])\s*(?:javascript:|data:)[^\"']*/i",'$1#',$html)??$html;
    return strip_tags($html,'<p><br><strong><b><em><i><u><ul><ol><li><h1><h2><h3><blockquote><div><span><a>');
}
function mail_html_from_plain(string $body): string { return nl2br(htmlspecialchars($body,ENT_QUOTES,'UTF-8')); }
function render_mail_template(string $key,array $vars): ?array {
    $all=mail_templates();$template=$all[$key]??null;if(!$template||empty($template['enabled']))return null;
    $replace=['{{site_name}}'=>site_name()];foreach($vars as $name=>$value)$replace['{{'.$name.'}}']=(string)$value;
    $plain=strtr((string)$template['body'],$replace);$html=(string)($template['html_body']??'');$html=$html!==''?mail_html_sanitize(strtr($html,$replace)):mail_html_from_plain($plain);
    $subject=strtr((string)$template['subject'],$replace);
    // Replace the old generic package name in templates already saved in the database.
    $plain=str_replace('RecordStore',site_name(),$plain);$html=str_replace('RecordStore',site_name(),$html);$subject=str_replace('RecordStore',site_name(),$subject);
    return ['subject'=>$subject,'body'=>$plain,'html_body'=>'<style>'.mail_theme_css().'</style>'.$html];
}function track_public_slug(array $track): string { return slugify((string)($track['title']??'track')).'-t'.(int)($track['id']??0); }
function track_public_url(array $track): string { return url('tracks/'.rawurlencode(track_public_slug($track))); }
function notify_order_mail(int $orderId,string $templateKey): void {
    global $pdo,$config;
    try{
        $st=$pdo->prepare('SELECT o.id,o.status,o.total_pence,u.display_name,u.email FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?');$st->execute([$orderId]);$order=$st->fetch();if(!$order)return;
        $mail=render_mail_template($templateKey,['customer_name'=>$order['display_name'],'order_id'=>'#'.$order['id'],'status'=>ucfirst((string)$order['status']),'total'=>money((int)$order['total_pence']),'site_name'=>site_name()]);if(!$mail)return;
        send_store_mail((string)$order['email'],$mail['subject'],$mail['body'],$mail['html_body']??null);
    }catch(Throwable $e){error_log('Order email error for #'.$orderId.': '.$e->getMessage());}
}
function security_token(): string { return bin2hex(random_bytes(32)); }
function security_token_hash(string $token): string { return hash('sha256',$token); }
function security_session_hash(): string { return hash('sha256',session_id()); }
function record_user_session(int $userId): void { global $pdo;try{$pdo->prepare('INSERT INTO user_sessions(user_id,session_hash,expires_at,ip_address,user_agent) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 30 DAY),INET6_ATON(?),?) ON DUPLICATE KEY UPDATE last_seen_at=NOW(),revoked_at=NULL')->execute([$userId,security_session_hash(),$_SERVER['REMOTE_ADDR']??null,substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,512)]);}catch(Throwable $ignored){} }
function revoke_user_session(): void { global $pdo;try{$pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE session_hash=?')->execute([security_session_hash()]);}catch(Throwable $ignored){} }
function totp_base32_encode(string $value): string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';foreach(str_split($value) as $char)$bits.=str_pad(decbin(ord($char)),8,'0',STR_PAD_LEFT);$out='';for($i=0;$i<strlen($bits);$i+=5){$chunk=substr($bits,$i,5);if(strlen($chunk)<5)$chunk=str_pad($chunk,5,'0');$out.=$alphabet[bindec($chunk)];}return $out; }
function totp_base32_decode(string $value): string { $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$value=strtoupper(preg_replace('/[^A-Z2-7]/','',$value)??'');$bits='';foreach(str_split($value) as $char){$pos=strpos($alphabet,$char);if($pos===false)continue;$bits.=str_pad(decbin($pos),5,'0',STR_PAD_LEFT);} $out='';for($i=0;$i+8<=strlen($bits);$i+=8)$out.=chr(bindec(substr($bits,$i,8)));return $out; }
function totp_code(string $secret,?int $time=null): string { $counter=intdiv($time??time(),30);$bin=pack('N*',0,$counter);$hash=hash_hmac('sha1',$bin,totp_base32_decode($secret),true);$offset=ord($hash[19])&15;$num=((ord($hash[$offset])&127)<<24)|((ord($hash[$offset+1])&255)<<16)|((ord($hash[$offset+2])&255)<<8)|(ord($hash[$offset+3])&255);return str_pad((string)($num%1000000),6,'0',STR_PAD_LEFT); }
function totp_verify(string $secret,string $code): bool { $code=preg_replace('/\D/','',$code)??'';for($offset=-1;$offset<=1;$offset++)if(hash_equals(totp_code($secret,time()+$offset*30),$code))return true;return false; }
