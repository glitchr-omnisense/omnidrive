# Changelog

Every version of omnisense/omnidrive, newest first ([Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
[Semantic Versioning](https://semver.org/)). A release's notes are its section here: the release
workflow refuses a tag without one.

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `omnisense/contracts` `^1.0` with no stability flag, so `composer require omnisense/omnidrive` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

Omnidrive: one contract for reading and writing files wherever they are kept - a directory of this machine, an S3 bucket, an SFTP (SSH) or FTP server - by the name the application gives each storage; Flysystem is the engine behind it, never in its signatures.
