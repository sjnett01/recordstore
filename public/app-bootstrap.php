<?php
declare(strict_types=1);

$privateBootstrap = dirname(__DIR__).'/private/src/bootstrap.php';
if (!is_readable($privateBootstrap)) {
    $privateBootstrap = __DIR__.'/private/src/bootstrap.php';
}
if (!is_readable($privateBootstrap)) {
    http_response_code(500);
    exit('Private application files are unavailable.');
}
$privateRootPath=dirname(dirname($privateBootstrap));
require $privateBootstrap;
