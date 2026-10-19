<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    $dir=getenv('DATA_DIR')?:__DIR__.'/data';
    if (!extension_loaded('pdo_sqlite') || !is_dir($dir) || !is_writable($dir)) throw new RuntimeException('Storage unavailable');
    $db=new PDO('sqlite:'.$dir.'/clinic.sqlite');
    $db->query('SELECT 1');
    echo json_encode(['status'=>'ok']);
} catch (Throwable $error) {
    http_response_code(503);
    echo json_encode(['status'=>'unavailable']);
}
