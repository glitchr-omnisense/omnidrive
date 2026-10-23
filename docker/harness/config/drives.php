<?php

/**
 * The drives bare builds, one per factory installed: the options each takes from
 * the environment (live, against the harness's servers or yours), and those it
 * is built with otherwise - built, never reached.
 *
 * @return array<string, array{factory: string, live: bool, options: array<string, mixed>}>
 */
$env = static fn (string $name, ?string $default = null): ?string => false === getenv($name) || '' === getenv($name) ? $default : (string) getenv($name);
$root = sys_get_temp_dir().'/omnidrive-bare-'.getmypid();

return [
    'memory' => ['factory' => 'memory', 'live' => false, 'options' => ['public_url' => 'https://cdn.example.org/memory']],
    'local' => ['factory' => 'local', 'live' => false, 'options' => ['root' => $root, 'public_url' => '/uploads']],
    's3' => ['factory' => 's3', 'live' => null !== $env('OMNIDRIVE_S3_ENDPOINT') || null !== $env('OMNIDRIVE_S3_KEY'), 'options' => [
        'bucket' => $env('OMNIDRIVE_S3_BUCKET', 'omnidrive'),
        'region' => $env('OMNIDRIVE_S3_REGION', 'us-east-1'),
        'endpoint' => $env('OMNIDRIVE_S3_ENDPOINT', 'http://s3.invalid:9000'),
        'path_style' => $env('OMNIDRIVE_S3_PATH_STYLE', '1'),
        'key' => $env('OMNIDRIVE_S3_KEY', 'example-key'),
        'secret' => $env('OMNIDRIVE_S3_SECRET', 'example-secret'),
        'prefix' => 'bare',
    ]],
    'sftp' => ['factory' => 'sftp', 'live' => null !== $env('OMNIDRIVE_SFTP_HOST'), 'options' => [
        'host' => $env('OMNIDRIVE_SFTP_HOST', 'sftp.invalid'),
        'port' => $env('OMNIDRIVE_SFTP_PORT', '22'),
        'username' => $env('OMNIDRIVE_SFTP_USERNAME', 'example'),
        'password' => $env('OMNIDRIVE_SFTP_PASSWORD', 'example'),
        'root' => $env('OMNIDRIVE_SFTP_ROOT', '/'),
    ]],
    'ftp' => ['factory' => 'ftp', 'live' => null !== $env('OMNIDRIVE_FTP_HOST'), 'options' => [
        'host' => $env('OMNIDRIVE_FTP_HOST', 'ftp.invalid'),
        'port' => $env('OMNIDRIVE_FTP_PORT', '21'),
        'username' => $env('OMNIDRIVE_FTP_USERNAME', 'example'),
        'password' => $env('OMNIDRIVE_FTP_PASSWORD', 'example'),
        'root' => $env('OMNIDRIVE_FTP_ROOT', '/'),
    ]],
];
