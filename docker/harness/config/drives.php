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
    'webdav' => ['factory' => 'webdav', 'live' => null !== $env('OMNIDRIVE_WEBDAV_URI'), 'options' => [
        'base_uri' => $env('OMNIDRIVE_WEBDAV_URI', 'http://webdav.invalid/'),
        'username' => $env('OMNIDRIVE_WEBDAV_USERNAME', 'example'),
        'password' => $env('OMNIDRIVE_WEBDAV_PASSWORD', 'example'),
    ]],
    'azure' => ['factory' => 'azure', 'live' => null !== $env('OMNIDRIVE_AZURE_CONNECTION_STRING'), 'options' => [
        'connection_string' => $env('OMNIDRIVE_AZURE_CONNECTION_STRING', 'DefaultEndpointsProtocol=http;AccountName=example;AccountKey=ZXhhbXBsZQ==;BlobEndpoint=http://azure.invalid:10000/example'),
        'container' => $env('OMNIDRIVE_AZURE_CONTAINER', 'omnidrive'),
        'prefix' => 'bare',
    ]],
    // No server stands in for these two: reached only with an account's tokens (.env).
    'dropbox' => ['factory' => 'dropbox', 'live' => null !== $env('OMNIDRIVE_DROPBOX_REFRESH_TOKEN') || null !== $env('OMNIDRIVE_DROPBOX_ACCESS_TOKEN'), 'options' => [
        'refresh_token' => $env('OMNIDRIVE_DROPBOX_REFRESH_TOKEN'),
        'app_key' => $env('OMNIDRIVE_DROPBOX_APP_KEY'),
        'app_secret' => $env('OMNIDRIVE_DROPBOX_APP_SECRET'),
        'access_token' => $env('OMNIDRIVE_DROPBOX_ACCESS_TOKEN', 'example-token'),
        'prefix' => 'omnidrive-bare',
    ]],
    'gdrive' => ['factory' => 'gdrive', 'live' => null !== $env('OMNIDRIVE_GDRIVE_REFRESH_TOKEN') || null !== $env('OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT'), 'options' => [
        'client_id' => $env('OMNIDRIVE_GDRIVE_CLIENT_ID', 'example.apps.googleusercontent.com'),
        'client_secret' => $env('OMNIDRIVE_GDRIVE_CLIENT_SECRET', 'example'),
        'refresh_token' => $env('OMNIDRIVE_GDRIVE_REFRESH_TOKEN', null === $env('OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT') ? 'example' : null),
        'service_account' => $env('OMNIDRIVE_GDRIVE_SERVICE_ACCOUNT'),
        'shared_drive_id' => $env('OMNIDRIVE_GDRIVE_SHARED_DRIVE_ID'),
        'root' => $env('OMNIDRIVE_GDRIVE_ROOT'),
    ]],
];
