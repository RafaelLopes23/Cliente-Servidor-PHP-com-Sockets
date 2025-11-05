<?php
// Projeto 1 - Servidor TCP em PHP com protocolo próprio (KV Store)
// Uso: php server.php [--host=0.0.0.0] [--port=5000]

$host = '0.0.0.0';
$port = 5000;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    } elseif (str_starts_with($arg, '--port=')) {
        $port = (int)substr($arg, 7);
    }
}

$address = "tcp://{$host}:{$port}";
$errno = 0; $errstr = '';
$server = @stream_socket_server($address, $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "[FATAL] Falha ao iniciar servidor em {$address}: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($server, false);

$kv = []; // Armazenamento em memória
$clients = []; // id => resource
$meta = [];    // id => ['buffer'=>string,'state'=>'line'|'data','expect'=>int,'pending'=>array,'addr'=>string,'name'=>string|null]

function logmsg(string $level, string $msg): void {
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$level}: {$msg}\n";
}

function idOf($conn): int { return (int)$conn; }

function writeLine($conn, string $line): void {
    @fwrite($conn, $line . "\r\n");
}

function closeClient($id, &$clients, &$meta): void {
    if (isset($clients[$id])) {
        @fclose($clients[$id]);
        unset($clients[$id], $meta[$id]);
    }
}

logmsg('INFO', "Servidor escutando em {$address}");

while (true) {
    $read = [$server];
    foreach ($clients as $c) { $read[] = $c; }
    $write = null; $except = null;
    // Timeout curto para permitir Ctrl+C
    $num = @stream_select($read, $write, $except, 1);
    if ($num === false) { continue; }

    foreach ($read as $sock) {
        if ($sock === $server) {
            // Nova conexão
            $conn = @stream_socket_accept($server, 0, $peer);
            if ($conn) {
                stream_set_blocking($conn, false);
                $id = idOf($conn);
                $clients[$id] = $conn;
                $meta[$id] = [
                    'buffer' => '',
                    'state' => 'line',
                    'expect' => 0,
                    'pending' => [],
                    'addr' => $peer ?: 'unknown',
                    'name' => null,
                ];
                logmsg('CONN', "Cliente #{$id} conectado de {$meta[$id]['addr']}");
                writeLine($conn, 'OK WELCOME KV/1.0');
            }
        } else {
            // Dados de um cliente existente
            $id = idOf($sock);
            $data = @fread($sock, 8192);
            if ($data === '' || $data === false) {
                logmsg('DISC', "Cliente #{$id} desconectado");
                closeClient($id, $clients, $meta);
                continue;
            }
            $meta[$id]['buffer'] .= $data;

            processBuffer($sock, $id, $meta, $kv);
        }
    }
}

function processBuffer($conn, int $id, array &$meta, array &$kv): void {
    $buf =& $meta[$id]['buffer'];
    while (true) {
        if ($meta[$id]['state'] === 'line') {
            $pos = strpos($buf, "\r\n");
            if ($pos === false) { return; }
            $line = substr($buf, 0, $pos);
            $buf = substr($buf, $pos + 2);
            if ($line === '') { continue; }
            handleLine($conn, $id, $line, $meta, $kv);
        } elseif ($meta[$id]['state'] === 'data') {
            $need = $meta[$id]['expect'];
            if (strlen($buf) < $need + 2) { return; }
            $value = substr($buf, 0, $need);
            $trail = substr($buf, $need, 2);
            if ($trail !== "\r\n") {
                writeLine($conn, 'ERROR 400 malformed-payload');
                // reseta estado descartando até próximo CRLF
                $meta[$id]['state'] = 'line';
                // tenta encontrar próxima linha
                $p = strpos($buf, "\r\n");
                $buf = ($p === false) ? '' : substr($buf, $p + 2);
                continue;
            }
            // consome payload + CRLF
            $buf = substr($buf, $need + 2);
            // completar operação SET
            $key = $meta[$id]['pending']['key'] ?? null;
            if ($key === null) {
                writeLine($conn, 'ERROR 500 internal');
            } else {
                $kv[$key] = $value;
                writeLine($conn, 'OK STORED');
            }
            // reset
            $meta[$id]['pending'] = [];
            $meta[$id]['state'] = 'line';
        } else {
            writeLine($conn, 'ERROR 500 invalid-state');
            $meta[$id]['state'] = 'line';
        }
    }
}

function handleLine($conn, int $id, string $line, array &$meta, array &$kv): void {
    $parts = preg_split('/\s+/', trim($line));
    $cmd = strtoupper($parts[0] ?? '');

    switch ($cmd) {
        case 'HELLO': {
            $name = $parts[1] ?? 'anonymous';
            $meta[$id]['name'] = $name;
            writeLine($conn, 'OK HELLO ' . $name);
            break;
        }
        case 'SET': {
            if (count($parts) < 3 || !is_numeric($parts[2])) {
                writeLine($conn, 'ERROR 400 usage: SET <key> <length>');
                break;
            }
            $key = $parts[1];
            $len = (int)$parts[2];
            if ($len < 0 || $len > 1024*1024) { // 1MB limite
                writeLine($conn, 'ERROR 413 payload-too-large');
                break;
            }
            $meta[$id]['pending'] = ['key' => $key];
            $meta[$id]['expect'] = $len;
            $meta[$id]['state'] = 'data';
            writeLine($conn, 'OK SEND-DATA');
            break;
        }
        case 'GET': {
            if (count($parts) < 2) { writeLine($conn, 'ERROR 400 usage: GET <key>'); break; }
            $key = $parts[1];
            if (!array_key_exists($key, $kv)) {
                writeLine($conn, 'NOT_FOUND');
            } else {
                $val = $kv[$key];
                writeLine($conn, 'VALUE ' . strlen($val));
                @fwrite($conn, $val . "\r\n");
            }
            break;
        }
        case 'DEL': {
            if (count($parts) < 2) { writeLine($conn, 'ERROR 400 usage: DEL <key>'); break; }
            $key = $parts[1];
            if (array_key_exists($key, $kv)) { unset($kv[$key]); writeLine($conn, 'OK DELETED'); }
            else { writeLine($conn, 'NOT_FOUND'); }
            break;
        }
        case 'KEYS': {
            $keys = array_keys($kv);
            $payload = implode(',', $keys);
            writeLine($conn, 'VALUE ' . strlen($payload));
            @fwrite($conn, $payload . "\r\n");
            break;
        }
        case 'QUIT': {
            writeLine($conn, 'BYE');
            // fechar após envio
            // marcar para fechar (será fechado ao detectar EOF)
            @stream_socket_shutdown($conn, STREAM_SHUT_WR);
            break;
        }
        default: {
            writeLine($conn, 'ERROR 400 unknown-command');
            break;
        }
    }
}
