<?php

namespace Omnidrive\Tests;

use Omnidrive\Drive;
use Omnidrive\Testing\DriveContractTests;
use Omnidrive\Testing\InMemoryDriveFactory;
use PHPUnit\Framework\TestCase;

/** The contract on FlysystemDrive over Flysystem's memory adapter: no disk, no network. */
final class InMemoryDriveTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\League\Flysystem\InMemory\InMemoryFilesystemAdapter::class)) {
            self::markTestSkipped('league/flysystem-memory is not installed (this package\'s require-dev).');
        }
    }

    use DriveContractTests;

    protected function drive(): Drive
    {
        return (new InMemoryDriveFactory())->create(['public_url' => 'https://cdn.example.org/files'], 'memory');
    }

    public function testThePublicAddressIsThePrefixOptionAndNoneIsSigned(): void
    {
        $drive = $this->drive();

        self::assertSame(['local' => false, 'publicUrls' => true, 'temporaryUrls' => false], $drive->capabilities()->toArray());
        self::assertSame('https://cdn.example.org/files/avatars/1.png', $drive->publicUrl('/avatars/1.png'));
        self::assertFalse((new InMemoryDriveFactory())->create()->capabilities()->publicUrls);
    }
}
