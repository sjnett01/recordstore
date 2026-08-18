<?php
require __DIR__.'/app-bootstrap.php';
$genres=$pdo->query("SELECT g.id,g.name,g.slug,COUNT(t.id) track_count FROM genres g LEFT JOIN tracks t ON t.genre_id=g.id AND t.active=1 GROUP BY g.id ORDER BY g.name")->fetchAll();
layout_header('Genres');?><div class="section-title"><div><span class="kicker">BROWSE</span><h1>Genres</h1></div></div><div class="genre-grid"><?php foreach($genres as $g):?><a class="panel genre-card" href="<?=e(url('genres/'.rawurlencode($g['slug'])))?>"><span class="genre-icon">♫</span><div><h2><?=e($g['name'])?></h2><p><?=e((string)$g['track_count'])?> tracks</p></div><span>View tracks →</span></a><?php endforeach;?></div><?php layout_footer();
