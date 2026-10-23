<?php

/**
 * Waits, up to 30 seconds each, for the servers the OMNIDRIVE_* variables name to
 * take connections: the containers start with the harness, their services a
 * moment later. A server that never answers is reported, not waited for again.
 */
foreach (['S3' => [null, 80], 'SFTP' => ['PORT', 22], 'FTP' => ['PORT', 21]] as $kind => [$portVariable, $default]) {
    if ('S3' === $kind) {
        $endpoint = getenv('OMNIDRIVE_S3_ENDPOINT');
        if (!$endpoint) {
            continue;
        }
        $parts = parse_url($endpoint);
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? ('https' === ($parts['scheme'] ?? '') ? 443 : $default);
    } else {
        $host = (string) getenv("OMNIDRIVE_{$kind}_HOST");
        $port = (int) (getenv("OMNIDRIVE_{$kind}_{$portVariable}") ?: $default);
    }
    if ('' === $host) {
        continue;
    }
    $until = microtime(true) + 30;
    while (!$socket = @fsockopen($host, $port, $code, $message, 1)) {
        if (microtime(true) > $until) {
            fwrite(\STDERR, "$kind server $host:$port does not answer: $message\n");
            continue 2;
        }
        usleep(250_000);
    }
    fclose($socket);
}
