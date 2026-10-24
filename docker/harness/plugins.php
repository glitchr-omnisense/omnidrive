<?php

/**
 * Every provider package: its slug (omnidrive/<slug>, github.com/glitchr-studio/omnidrive-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'local' => ['Omnidrive\\Local\\Tests\\', 'Omnidrive\\Local\\LocalDriveFactory'],
    's3' => ['Omnidrive\\S3\\Tests\\', 'Omnidrive\\S3\\S3DriveFactory'],
    'sftp' => ['Omnidrive\\Sftp\\Tests\\', 'Omnidrive\\Sftp\\SftpDriveFactory'],
    'ftp' => ['Omnidrive\\Ftp\\Tests\\', 'Omnidrive\\Ftp\\FtpDriveFactory'],
    'webdav' => ['Omnidrive\\Webdav\\Tests\\', 'Omnidrive\\Webdav\\WebdavDriveFactory'],
    'azure' => ['Omnidrive\\Azure\\Tests\\', 'Omnidrive\\Azure\\AzureDriveFactory'],
    'dropbox' => ['Omnidrive\\Dropbox\\Tests\\', 'Omnidrive\\Dropbox\\DropboxDriveFactory'],
    'gdrive' => ['Omnidrive\\Gdrive\\Tests\\', 'Omnidrive\\Gdrive\\GdriveDriveFactory'],
];
