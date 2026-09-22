<?php
header('Content-Type: text/plain');
$logFile = __DIR__ . '/../iclock/adms_debug.log';
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "No ADMS traffic logged yet.";
}
