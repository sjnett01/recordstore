<?php
require __DIR__.'/app-bootstrap.php';
require_admin();
header('Content-Type: application/json; charset=utf-8');
$id=(int)($_GET['id']??0);
echo json_encode($id>0?track_artist_ids($id):[],JSON_UNESCAPED_UNICODE);
