<?php
// Cliente CLI para o protocolo KV/1.0
// Uso: php kv-client.php --host=127.0.0.1 --port=5000

$host = '127.0.0.1';
$port = 5000;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--host=')) { $host = substr($arg, 7); }
    elseif (str_starts_with($arg, '--port=')) { $port = (int)substr($arg, 7); }
}

$address = "tcp://{$host}:{$port}";
$fp = @stream_socket_client($address, $errno, $errstr, 5);
if (!$fp) {
    fwrite(STDERR, "Falha ao conectar em {$address}: {$errstr} ({$errno})\n");
    exit(1);
}
stream_set_blocking($fp, true);

function recvLine($fp): ?string { $r = @stream_get_line($fp, 1<<20, "\r\n"); return $r === false ? null : $r; }
function sendLine($fp, string $line): void { @fwrite($fp, $line . "\r\n"); }

// Recebe saudação inicial do servidor e então envia HELLO
$welcome = recvLine($fp);
if ($welcome !== null) {
    echo "< " . $welcome . PHP_EOL;
}
sendLine($fp, 'HELLO client-cli');
$hello = recvLine($fp);
if ($hello !== null) {
    echo "< " . $hello . PHP_EOL;
}

$help = "Comandos:\n  GET <key>\n  SET <key> (valor será solicitado)\n  DEL <key>\n  KEYS\n  QUIT\n  HELP\n";

echo $help;

$stdin = fopen('php://stdin', 'r');
while (true) {
    echo PHP_EOL . 'kv> ';
    $line = fgets($stdin);
    if ($line === false) { break; }
    $line = trim($line);
    if ($line === '') { continue; }
    $parts = preg_split('/\s+/', $line);
    $cmd = strtoupper($parts[0]);

    if ($cmd === 'HELP') { echo $help; continue; }

    if ($cmd === 'SET') {
        if (count($parts) < 2) { echo "Uso: SET <key>\n"; continue; }
        $key = $parts[1];
        echo "valor> ";
        $value = rtrim(fgets($stdin), "\r\n");
    sendLine($fp, 'SET ' . $key . ' ' . strlen($value));
    $resp = recvLine($fp);
        echo '< ' . $resp . PHP_EOL;
        if (str_starts_with($resp ?? '', 'OK SEND-DATA')) {
            @fwrite($fp, $value . "\r\n");
            $resp2 = recvLine($fp);
            echo '< ' . $resp2 . PHP_EOL;
        }
        continue;
    }

    if (in_array($cmd, ['GET','DEL','KEYS','QUIT'])) {
    sendLine($fp, $line);
    $resp = recvLine($fp);
        if ($resp === null) { echo "(conexão encerrada)\n"; break; }
        echo '< ' . $resp . PHP_EOL;
        if (str_starts_with($resp, 'VALUE ')) {
            $len = (int)substr($resp, 6);
            $value = '';
            if ($len > 0) {
                $value = fread($fp, $len);
            }
            // consumir CRLF
            fread($fp, 2);
            echo '< ' . $value . PHP_EOL;
        }
        if ($cmd === 'QUIT') { break; }
        continue;
    }

    echo "Comando inválido. Digite HELP para ajuda.\n";
}

fclose($fp);
