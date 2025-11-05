<?php
// Cliente de demonstração para gerar tráfego previsível no Wireshark
// Uso: php client-demo.php --host=127.0.0.1 --port=5000

$host = '127.0.0.1';
$port = 5000;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--host=')) { $host = substr($arg, 7); }
    elseif (str_starts_with($arg, '--port=')) { $port = (int)substr($arg, 7); }
}

$address = "tcp://{$host}:{$port}";
$fp = @stream_socket_client($address, $errno, $errstr, 5);
if (!$fp) { fwrite(STDERR, "Falha ao conectar em {$address}: {$errstr} ({$errno})\n"); exit(1);} 

function sendLine($fp, string $line): void { @fwrite($fp, $line . "\r\n"); }
function recvLine($fp): ?string { $r = @stream_get_line($fp, 1<<20, "\r\n"); return $r === false ? null : $r; }

function step($fp, string $desc, callable $fn): void {
    echo "\n== " . $desc . " ==\n";
    $fn();
}

// Leia saudação inicial, depois envie HELLO e leia resposta do HELLO
$welcome = recvLine($fp); if ($welcome !== null) echo '< ' . $welcome . "\n";
sendLine($fp, 'HELLO demo-client');
$hello = recvLine($fp); if ($hello !== null) echo '< ' . $hello . "\n";

step($fp, 'SET foo = bar', function() use ($fp) {
    $val = 'bar';
    sendLine($fp, 'SET foo ' . strlen($val));
    echo '< ' . recvLine($fp) . "\n";
    @fwrite($fp, $val . "\r\n");
    echo '< ' . recvLine($fp) . "\n";
});

step($fp, 'GET foo', function() use ($fp) {
    sendLine($fp, 'GET foo');
    $h = recvLine($fp); echo '< ' . $h . "\n";
    if (str_starts_with($h, 'VALUE ')) {
        $len = (int)substr($h, 6);
        $value = $len ? fread($fp, $len) : '';
        fread($fp, 2); // CRLF
        echo '< ' . $value . "\n";
    }
});

step($fp, 'KEYS', function() use ($fp) {
    sendLine($fp, 'KEYS');
    $h = recvLine($fp); echo '< ' . $h . "\n";
    if (str_starts_with($h, 'VALUE ')) {
        $len = (int)substr($h, 6);
        $value = $len ? fread($fp, $len) : '';
        fread($fp, 2);
        echo '< ' . $value . "\n";
    }
});

step($fp, 'DEL foo', function() use ($fp) {
    sendLine($fp, 'DEL foo');
    echo '< ' . recvLine($fp) . "\n";
});

step($fp, 'QUIT', function() use ($fp) {
    sendLine($fp, 'QUIT');
    echo '< ' . recvLine($fp) . "\n";
});

fclose($fp);
