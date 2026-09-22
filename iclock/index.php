<?php
/**
 * eSSL ADMS Catch-all Dispatcher
 */
$uri = $_SERVER['REQUEST_URI'] ?? '';

if (stripos($uri, 'getrequest') !== false) {
    require __DIR__ . '/getrequest.php';
} elseif (stripos($uri, 'devicecmd') !== false) {
    require __DIR__ . '/devicecmd.php';
} else {
    require __DIR__ . '/cdata.php';
}
