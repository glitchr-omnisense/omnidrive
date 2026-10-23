<?php

namespace Omnidrive\Tests;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Omnidrive\Exception\DriveException;
use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Flysystem\FlysystemDrive;
use Omnidrive\Visibility;
use PHPUnit\Framework\TestCase;

/** A host's operator as a drive: what of() reads from it, how its failures read. */
final class FlysystemDriveTest extends TestCase
{
    public function testALocalOperatorGivesItsRootAndItsFilesAreFilesOfThisMachine(): void
    {
        $root = sys_get_temp_dir().'/omnidrive-of-'.bin2hex(random_bytes(4));
        $drive = FlysystemDrive::of('local.uploads', new Filesystem(new LocalFilesystemAdapter($root), ['public_url' => '/uploads']));
        $drive->write('a/b.txt', 'b');

        self::assertSame('local.uploads', $drive->name());
        self::assertSame(['local' => true, 'publicUrls' => true, 'temporaryUrls' => false], $drive->capabilities()->toArray());
        self::assertSame($root.'/a/b.txt', $drive->localPath('/a/b.txt'));
        self::assertSame('b', file_get_contents((string) $drive->localPath('a/b.txt')));
        self::assertSame('/uploads/a/b.txt', $drive->publicUrl('a/b.txt'));

        $drive->deleteDirectory('');
        @rmdir($root);
    }

    public function testAnOperatorOfAnotherAdapterHasNoLocalFileAndNoAddress(): void
    {
        $drive = FlysystemDrive::of('elsewhere', new Filesystem(new InMemoryFilesystemAdapter()));

        self::assertSame(['local' => false, 'publicUrls' => false, 'temporaryUrls' => false], $drive->capabilities()->toArray());
        self::assertNull($drive->localPath('a.txt'));
        $this->expectException(NotSupportedException::class);
        $drive->publicUrl('a.txt');
    }

    public function testTheEngineFailureIsKeptBehindTheContractsException(): void
    {
        $failing = new class(sys_get_temp_dir()) extends LocalFilesystemAdapter {
            public function write(string $path, string $contents, \League\Flysystem\Config $config): void
            {
                throw UnableToWriteFile::atLocation($path, 'the disk is full');
            }
        };
        $drive = new FlysystemDrive('full', new Filesystem($failing));

        try {
            $drive->write('a.txt', 'a');
            self::fail('a failed write');
        } catch (DriveException $e) {
            self::assertNotInstanceOf(FileNotFoundException::class, $e);
            self::assertSame(['write', 'full', 'a.txt'], [$e->operation, $e->drive, $e->path]);
            self::assertStringContainsString('the disk is full', $e->getMessage());
            self::assertInstanceOf(UnableToWriteFile::class, $e->getPrevious());
        }
    }

    public function testVisibilityOptionsAreTheContractsEnumeration(): void
    {
        $drive = new FlysystemDrive('memory', new Filesystem(new InMemoryFilesystemAdapter()));
        $drive->write('private.txt', 'p', ['visibility' => Visibility::Private]);
        $drive->write('public.txt', 'p', ['visibility' => 'public']);

        self::assertSame(Visibility::Private, $drive->visibility('private.txt'));
        self::assertSame(Visibility::Public, $drive->visibility('public.txt'));
        self::assertSame(Visibility::Private, $drive->metadata('private.txt')->visibility);
    }
}
