<?php
require __DIR__.'/app-bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT preview_path FROM tracks WHERE id=? AND active=1');
$st->execute([$id]);
$row = $st->fetch();
if (!$row || !$row['preview_path']) { http_response_code(404); exit; }

$root = realpath($config['paths']['previews']);
$relative = basename((string)$row['preview_path']);
$path = realpath($config['paths']['previews'].'/'.$relative);
if ((!$path || !is_file($path)) && preg_match('/\.(?:mp3|m4a|aac|mp4)$/i',$relative)) {
    $stem = preg_replace('/\.[^.]+$/','',$relative);
    foreach (['m4a','mp3','aac'] as $fallbackExt) {
        $candidate = realpath($config['paths']['previews'].'/'.$stem.'.'.$fallbackExt);
        if ($candidate && is_file($candidate)) { $path=$candidate; break; }
    }
}
if (!$path || !$root || !str_starts_with($path, $root.DIRECTORY_SEPARATOR) || !is_file($path)) {
    http_response_code(404); exit;
}
// Ensure PHP/Plesk output buffering or compression cannot invalidate byte offsets.
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { @ob_end_clean(); }
set_time_limit(0);

$size = filesize($path);
$start = 0;
$end = $size - 1;
$status = 200;


$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$contentType = in_array($ext, ['m4a','mp4','aac'], true) ? 'audio/mp4' : 'audio/mpeg';
header('Content-Type: '.$contentType);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Content-Encoding: identity');

$range = $_SERVER['HTTP_RANGE'] ?? '';
if ($range !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $m)) {
        header('Content-Range: bytes */'.$size);
        http_response_code(416);
        exit;
    }
    if ($m[1] === '' && $m[2] !== '') {
        $suffix = min((int)$m[2], $size);
        $start = $size - $suffix;
    } else {
        $start = ($m[1] === '') ? 0 : (int)$m[1];
        if ($m[2] !== '') $end = min((int)$m[2], $size - 1);
    }
    if ($start < 0 || $start > $end || $start >= $size) {
        header('Content-Range: bytes */'.$size);
        http_response_code(416);
        exit;
    }
    $status = 206;
}

$length = $end - $start + 1;
http_response_code($status);
if ($status === 206) header("Content-Range: bytes $start-$end/$size");
header('Content-Length: '.$length);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') exit;

$fp = fopen($path, 'rb');
if (!$fp) { http_response_code(500); exit; }
fseek($fp, $start);
$remaining = $length;
while ($remaining > 0 && !feof($fp)) {
    $chunk = fread($fp, min(262144, $remaining));
    if ($chunk === false || $chunk === '') break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
    if (connection_aborted()) break;
}
fclose($fp);
exit;
