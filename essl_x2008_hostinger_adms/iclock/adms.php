<?php
require_once __DIR__ . '/../config.php';

$pdo = db();
$route = $_GET['route'] ?? '';
$sn = trim($_GET['SN'] ?? $_GET['sn'] ?? '');
$table = strtoupper(trim($_GET['table'] ?? ''));

$body = file_get_contents('php://input');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$query = $_SERVER['QUERY_STRING'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

function respond_text(string $text = "OK", int $code = 200): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text;
    exit;
}

function upsert_device(PDO $pdo, string $sn, string $stamp = ''): void {
    if ($sn === '') return;
    $stmt = $pdo->prepare("
        INSERT INTO essl_devices (serial_number,last_seen,last_stamp,last_ip)
        VALUES (:sn,NOW(),:stamp,:ip)
        ON DUPLICATE KEY UPDATE
          last_seen=NOW(), last_stamp=VALUES(last_stamp), last_ip=VALUES(last_ip)
    ");
    $stmt->execute([
        ':sn'=>$sn, ':stamp'=>$stamp, ':ip'=>($_SERVER['REMOTE_ADDR'] ?? '')
    ]);
}

function parse_datetime_value(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/* Keep a raw request for debugging/integration verification. */
try {
    $raw = $pdo->prepare("
        INSERT INTO essl_raw_requests
        (device_serial,request_method,request_uri,query_string,request_body)
        VALUES (?,?,?,?,?)
    ");
    $raw->execute([$sn ?: null, $method, $uri, $query, $body]);
} catch (Throwable $e) {
    // Raw logging must never stop the biometric device response.
}

if ($route === 'getrequest') {
    upsert_device($pdo, $sn);

    $stmt = $pdo->prepare("
        SELECT id, command_text
        FROM essl_commands
        WHERE device_serial=? AND status='pending'
        ORDER BY id ASC LIMIT 1
    ");
    $stmt->execute([$sn]);
    $cmd = $stmt->fetch();

    if ($cmd) {
        $upd = $pdo->prepare("UPDATE essl_commands SET status='sent', sent_at=NOW() WHERE id=?");
        $upd->execute([$cmd['id']]);
        respond_text("C:".$cmd['id'].":".$cmd['command_text']."\n");
    }

    // Standard ADMS option response. Realtime=1 asks the device to push logs.
    $response =
        "GET OPTION FROM: ".$sn."\n".
        "Stamp=0\n".
        "ATTLOGStamp=0\n".
        "OPERLOGStamp=0\n".
        "ATTPHOTOSTamp=0\n".
        "ErrorDelay=30\n".
        "Delay=10\n".
        "TransTimes=00:00;23:59\n".
        "TransInterval=1\n".
        "TransFlag=TransData AttLog\tOpLog\tEnrollUser\tChgUser\n".
        "TimeZone=5.5\n".
        "Realtime=1\n".
        "Encrypt=None\n".
        "OK\n";
    respond_text($response);
}

if ($route === 'cdata') {
    upsert_device($pdo, $sn, $_GET['Stamp'] ?? '');

    // Device registration/options packet
    if ($table === 'OPTIONS' || stripos($body, '~Device=') !== false || stripos($body, 'Device=') !== false) {
        respond_text("OK");
    }

    $lines = preg_split('/\r\n|\r|\n/', trim($body));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // ATTLOG format is normally tab-separated:
        // PIN  DateTime  Status  VerifyMode  WorkCode  ...
        if ($table === 'ATTLOG' || $table === 'ATTLOGS') {
            $parts = preg_split("/\t+/", $line);
            $pin = trim($parts[0] ?? '');
            $dt = parse_datetime_value($parts[1] ?? '');
            if ($pin !== '' && $dt !== null) {
                $status = trim($parts[2] ?? '');
                $verify = trim($parts[3] ?? '');
                $work = trim($parts[4] ?? '');
                $ins = $pdo->prepare("
                    INSERT INTO essl_attendance
                    (device_serial,employee_pin,punch_time,status,verify_mode,work_code,raw_line)
                    VALUES (?,?,?,?,?,?,?)
                ");
                $ins->execute([$sn,$pin,$dt,$status,$verify,$work,$line]);
            }
        }

        // Basic USER packet support: PIN, Name, Card...
        if ($table === 'USER' || $table === 'USERS') {
            $fields = [];
            foreach (preg_split("/\t+/", $line) as $piece) {
                if (strpos($piece, '=') !== false) {
                    [$k,$v] = explode('=', $piece, 2);
                    $fields[strtolower(trim($k))] = trim($v);
                }
            }
            $pin = $fields['pin'] ?? $fields['userid'] ?? '';
            $name = $fields['name'] ?? '';
            $card = $fields['card'] ?? '';
            if ($pin !== '') {
                $ins = $pdo->prepare("
                    INSERT INTO essl_employees
                    (device_pin,employee_name,card_no,device_serial,raw_data)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                      employee_name=VALUES(employee_name),
                      card_no=VALUES(card_no),
                      raw_data=VALUES(raw_data)
                ");
                $ins->execute([$pin,$name,$card,$sn,$line]);
            }
        }
    }

    respond_text("OK");
}

respond_text("OK");
