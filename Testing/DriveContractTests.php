<?php

namespace Omnidrive\Testing;

use Omnidrive\Drive;
use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Model\Entry;
use Omnidrive\Visibility;
use PHPUnit\Framework\Attributes\After;

/**
 * What every Drive does, as PHPUnit tests: a provider's test case (or an
 * application's, for a drive of its own) uses this trait and gives the drive.
 * Each test works under a directory of its own, removed afterwards, so a shared
 * bucket or server is left as it was.
 *
 *   final class LocalDriveTest extends TestCase
 *   {
 *       use DriveContractTests;
 *
 *       protected function drive(): Drive { return (new LocalDriveFactory())->create(['root' => sys_get_temp_dir().'/drive']); }
 *   }
 *
 * Requires phpunit/phpunit (the application's, in require-dev).
 */
trait DriveContractTests
{
    private ?Drive $contractDrive = null;
    private string $contractDir = '';

    /** The drive under test; called once per test. */
    abstract protected function drive(): Drive;

    /** Whether the drive keeps a visibility of its own per file (false: a bucket that ignores ACLs). */
    protected function keepsVisibility(): bool
    {
        return true;
    }

    private function contract(): Drive
    {
        if (null === $this->contractDrive) {
            $this->contractDrive = $this->drive();
            $this->contractDir = 'omnidrive-contract-'.bin2hex(random_bytes(4));
        }

        return $this->contractDrive;
    }

    private function under(string $path): string
    {
        $this->contract();

        return $this->contractDir.'/'.$path;
    }

    #[After]
    protected function removeTheContractDirectory(): void
    {
        if (null !== $this->contractDrive) {
            $this->contractDrive->deleteDirectory($this->contractDir);
            $this->contractDrive = null;
        }
    }

    public function testAFileIsWrittenReadAndDeleted(): void
    {
        $drive = $this->contract();
        $path = $this->under('notes/first.txt');

        self::assertFalse($drive->exists($path));
        $drive->write($path, 'Hello, drive.');
        self::assertTrue($drive->exists($path));
        self::assertSame('Hello, drive.', $drive->read($path));
        self::assertSame(13, $drive->size($path));
        self::assertSame('text/plain', $drive->mimeType($path));

        $drive->write($path, 'Replaced.');
        self::assertSame('Replaced.', $drive->read($path), 'a write replaces the file');

        $drive->delete($path);
        self::assertFalse($drive->exists($path));
        $drive->delete($path); // not there: no error
    }

    public function testStreamsAreWrittenAndRead(): void
    {
        $drive = $this->contract();
        $path = $this->under('streams/bytes.bin');
        $bytes = random_bytes(300_000);
        $stream = fopen('php://temp', 'w+b');
        self::assertIsResource($stream);
        fwrite($stream, $bytes);
        rewind($stream);

        $drive->writeStream($path, $stream);
        if (\is_resource($stream)) { // an engine may close it once written (Azure's, Dropbox's: their HTTP clients)
            fclose($stream);
        }
        $read = $drive->readStream($path);
        self::assertSame($bytes, stream_get_contents($read));
        fclose($read);
        self::assertSame(\strlen($bytes), $drive->size($path));
    }

    public function testAFileThatIsNotThereIsNotFound(): void
    {
        $drive = $this->contract();
        $path = $this->under('missing.txt');

        try {
            $drive->read($path);
            self::fail('read() of a missing file');
        } catch (FileNotFoundException $e) {
            self::assertSame('read', $e->operation);
            self::assertSame($drive->name(), $e->drive);
            self::assertSame($path, $e->path);
        }
        $this->expectException(FileNotFoundException::class);
        $drive->size($path);
    }

    public function testADirectoryIsListedShallowAndDeep(): void
    {
        $drive = $this->contract();
        $drive->write($this->under('tree/a.txt'), 'a');
        $drive->write($this->under('tree/sub/b.txt'), 'bb');
        $drive->write($this->under('tree/sub/deeper/c.txt'), 'ccc');

        $shallow = self::paths($drive->list($this->under('tree')));
        self::assertSame([$this->under('tree/a.txt') => 'file', $this->under('tree/sub') => 'directory'], $shallow);

        $deep = self::paths($drive->list($this->under('tree'), true));
        self::assertSame('file', $deep[$this->under('tree/sub/deeper/c.txt')] ?? null);
        self::assertSame('file', $deep[$this->under('tree/sub/b.txt')] ?? null);
        self::assertSame('file', $deep[$this->under('tree/a.txt')] ?? null);

        foreach ($drive->list($this->under('tree')) as $entry) {
            if ($entry->isFile()) {
                self::assertSame(1, $entry->size, 'a listed file has its size');
                self::assertSame('a.txt', $entry->basename());
            }
        }
    }

    public function testAFileIsMovedAndCopied(): void
    {
        $drive = $this->contract();
        $drive->write($this->under('from.txt'), 'moving');

        $drive->copy($this->under('from.txt'), $this->under('copies/copy.txt'));
        self::assertSame('moving', $drive->read($this->under('copies/copy.txt')));
        self::assertTrue($drive->exists($this->under('from.txt')));

        $drive->move($this->under('from.txt'), $this->under('moved/to.txt'));
        self::assertFalse($drive->exists($this->under('from.txt')));
        self::assertSame('moving', $drive->read($this->under('moved/to.txt')));

        $this->expectException(FileNotFoundException::class);
        $drive->move($this->under('from.txt'), $this->under('again.txt'));
    }

    public function testDirectoriesAreMadeAndDeleted(): void
    {
        $drive = $this->contract();
        $drive->createDirectory($this->under('made/inside'));
        self::assertTrue($drive->directoryExists($this->under('made/inside')));
        self::assertFalse($drive->exists($this->under('made/inside')), 'a directory is not a file');

        $drive->write($this->under('made/inside/file.txt'), 'x');
        $drive->deleteDirectory($this->under('made'));
        self::assertFalse($drive->exists($this->under('made/inside/file.txt')));
        self::assertFalse($drive->directoryExists($this->under('made')));
    }

    public function testMetadataAndChecksumAreTheFile(): void
    {
        $drive = $this->contract();
        $path = $this->under('meta/page.html');
        $before = time();
        $drive->write($path, '<!doctype html><title>t</title>');

        $entry = $drive->metadata($path);
        self::assertInstanceOf(Entry::class, $entry);
        self::assertTrue($entry->isFile());
        self::assertSame(31, $entry->size);
        self::assertSame('text/html', $entry->mimeType);
        self::assertNotNull($entry->lastModified);
        self::assertEqualsWithDelta($before, $entry->lastModified->getTimestamp(), 120, 'the server\'s clock, give or take');
        self::assertSame(md5('<!doctype html><title>t</title>'), $drive->checksum($path));
    }

    public function testVisibilityIsWrittenAndChanged(): void
    {
        $drive = $this->contract();
        $path = $this->under('visibility/secret.txt');
        $drive->write($path, 'secret', ['visibility' => Visibility::Private]);
        if (!$this->keepsVisibility()) {
            self::markTestSkipped(\sprintf('The "%s" drive keeps no visibility of its own.', $drive->name()));
        }

        self::assertSame(Visibility::Private, $drive->visibility($path));
        $drive->setVisibility($path, Visibility::Public);
        self::assertSame(Visibility::Public, $drive->visibility($path));
    }

    public function testAddressesAndLocalPathsAreWhatTheCapabilitiesSay(): void
    {
        $drive = $this->contract();
        $path = $this->under('served/picture.txt');
        $drive->write($path, 'served');
        $can = $drive->capabilities();

        if ($can->publicUrls) {
            self::assertStringContainsString('picture.txt', $drive->publicUrl($path));
        } else {
            try {
                $drive->publicUrl($path);
                self::fail('a public address from a drive that says it has none');
            } catch (NotSupportedException) {
            }
        }

        if ($can->temporaryUrls) {
            self::assertStringContainsString('picture.txt', $drive->temporaryUrl($path, new \DateTimeImmutable('+10 minutes')));
        } else {
            try {
                $drive->temporaryUrl($path, new \DateTimeImmutable('+10 minutes'));
                self::fail('a temporary address from a drive that says it signs none');
            } catch (NotSupportedException) {
            }
        }

        $local = $drive->localPath($path);
        if ($can->local) {
            self::assertNotNull($local);
            self::assertFileExists($local);
            self::assertSame('served', file_get_contents($local));
        } else {
            self::assertNull($local);
        }
    }

    /**
     * @param iterable<Entry> $entries
     *
     * @return array<string, string> path => file or directory, sorted
     */
    private static function paths(iterable $entries): array
    {
        $paths = [];
        foreach ($entries as $entry) {
            $paths[$entry->path] = $entry->directory ? 'directory' : 'file';
        }
        ksort($paths);

        return $paths;
    }
}
