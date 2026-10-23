<?php

namespace Omnidrive\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Flysystem\FlysystemDrive;
use Omnidrive\Flysystem\OperatorDrives;
use Omnidrive\Tests\Fixtures\Operators;
use PHPUnit\Framework\TestCase;

/** The storages a host already has, as drives. */
final class OperatorDrivesTest extends TestCase
{
    public function testAHostsOperatorsAreDrivesByTheirNames(): void
    {
        $uploads = new Filesystem(new InMemoryFilesystemAdapter());
        $drives = new OperatorDrives(new Operators(['local.uploads' => $uploads, 'not.storage' => new \stdClass()]));

        self::assertSame(['local.uploads', 'not.storage'], $drives->names(), 'a service locator lists its own');
        self::assertTrue($drives->has('local.uploads'));
        $drive = $drives->get('local.uploads');
        self::assertInstanceOf(FlysystemDrive::class, $drive);
        self::assertSame($uploads, $drive->operator());
        self::assertSame($drive, $drives->get('local.uploads'));

        $drive->write('a.txt', 'a');
        self::assertSame('a', $uploads->read('a.txt'), 'the host\'s own storage, written through the contract');

        $this->expectException(InvalidConfigException::class);
        $drives->get('not.storage');
    }

    public function testNamesGivenAreTheOnlyOnes(): void
    {
        $drives = new OperatorDrives(new Operators(['a' => new Filesystem(new InMemoryFilesystemAdapter())]), ['a']);

        self::assertSame(['a'], $drives->names());
        self::assertFalse($drives->has('b'));
        $this->expectExceptionMessage('No "b" drive; the host has: a.');
        $drives->get('b');
    }
}
