<?php
require __DIR__.'/app-bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
$base=rtrim($config['app']['base_url'],'/');
$urls=[['loc'=>$base.'/','changefreq'=>'daily'],['loc'=>$base.'/tracks','changefreq'=>'daily'],['loc'=>$base.'/artists','changefreq'=>'weekly'],['loc'=>$base.'/genres','changefreq'=>'weekly'],['loc'=>$base.'/charts','changefreq'=>'daily']];
foreach($pdo->query("SELECT slug FROM artists WHERE slug IS NOT NULL AND slug<>'' ORDER BY id")->fetchAll() as $row)$urls[]=['loc'=>$base.'/artists/'.rawurlencode((string)$row['slug']),'changefreq'=>'weekly'];
foreach($pdo->query("SELECT slug FROM genres WHERE slug IS NOT NULL AND slug<>'' ORDER BY id")->fetchAll() as $row)$urls[]=['loc'=>$base.'/genres/'.rawurlencode((string)$row['slug']),'changefreq'=>'weekly'];
foreach($pdo->query("SELECT id,title,created_at FROM tracks WHERE active=1 ORDER BY id")->fetchAll() as $row)$urls[]=['loc'=>$base.'/tracks/'.rawurlencode(track_public_slug($row)),'changefreq'=>'weekly','lastmod'=>$row['created_at']??null];
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach($urls as $item){echo '<url><loc>'.htmlspecialchars($item['loc'],ENT_XML1|ENT_QUOTES,'UTF-8').'</loc><changefreq>'.$item['changefreq'].'</changefreq>'.(!empty($item['lastmod'])?'<lastmod>'.htmlspecialchars(substr((string)$item['lastmod'],0,10),ENT_XML1|ENT_QUOTES,'UTF-8').'</lastmod>':'').'</url>';}
echo '</urlset>';
