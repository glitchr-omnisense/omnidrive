<?php

namespace Omnidrive\Tests;

use Omnidrive\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Omnidrive outside any framework: the harness's bare script
 * (docker/harness/bin/bare) run in a PHP process of its own builds the registry
 * by hand and uses each drive it can reach whole, then reports every class and
 * file PHP loaded on the way. None may be a framework's; of Symfony, only the
 * HTTP client AsyncAws (omnidrive/s3) calls S3 through.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Form|Validator|Routing|Console)|Symfony\\\\Bundle|Symfony\\\\Bridge|Illuminate|Laravel|Doctrine|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|form|validator|routing|console|[a-z-]*bundle|[a-z-]*bridge)|laravel|illuminate|doctrine|twig)/~';
    private const STEPS = ['write', 'stream', 'copy_move', 'checksum', 'delete'];

    /** @var array<string, array<string, mixed>> */
    private static array $reports = [];

    /** @return array<string, mixed> */
    private static function report(bool $live = false): array
    {
        // The harness's script and its providers: run where this package is the root (its harness, its
        // workspace), not from an application's vendor/, where neither the providers nor the memory adapter need be.
        $root = \Composer\InstalledVersions::getRootPackage()['name'];
        if (!\in_array($root, ['omnisense/omnidrive', 'omnisense/omnidrive-harness', 'omnidrive/workspace'], true)) {
            self::markTestSkipped(\sprintf('Run from %s\'s vendor/: the bare script belongs to omnidrive\'s harness.', $root));
        }
        $key = $live ? 'live' : 'bare';
        if (!isset(self::$reports[$key])) {
            [$status, self::$reports[$key]] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json', ...($live ? ['--live'] : [])]);
            self::assertSame(0, $status, 'no class of a framework');
        }

        return self::$reports[$key];
    }

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        $report = self::report();

        self::assertFalse($report['live']);
        self::assertContains(Registry::class, $report['symbols']);
        self::assertSame([], self::framework($report), 'no class nor file of a framework');
        self::assertSame([], array_diff($report['symfony'], ['Symfony\\Component\\HttpClient', 'Symfony\\Contracts\\HttpClient', 'Symfony\\Contracts\\Service']), 'of Symfony, the HTTP client alone');
        $installed = array_values(array_filter(array_column(require __DIR__.'/../docker/harness/plugins.php', 1), 'class_exists'));
        if (!self::alone()) {
            self::assertNotEmpty($installed, 'at least one provider package beside this one');
        }
        foreach ($installed as $factory) {
            self::assertContains($factory, $report['symbols'], 'every provider installed, its factory built');
        }
        self::assertCount(\count($installed) + 1, $report['drives'], 'and the memory drive');
    }

    public function testTheDrivesAtHandAreUsedWholeAndTheOthersOnlyBuilt(): void
    {
        $report = self::report();

        foreach ($report['drives'] as $name => $drive) {
            if (!\in_array($drive['factory'], ['memory', 'local'], true)) {
                self::assertArrayNotHasKey('used', $drive, "$name: built, not reached");
                continue;
            }
            self::assertArrayHasKey('used', $drive, $name);
            foreach (self::STEPS as $step) {
                self::assertTrue($drive['used'][$step], "$name: $step");
            }
            self::assertSame('text/plain', $drive['used']['size_type'][1] ?? null, $name);
        }
        self::assertSame(['big.bin', 'hello.txt'], self::files($report['drives']['memory']['used']['list']));
        if (self::alone() && !class_exists('Omnidrive\\Local\\LocalDriveFactory')) {
            return; // omnidrive/local is not installed beside the package alone: the memory drive was the one at hand.
        }
        self::assertTrue($report['drives']['local']['used']['local_path'] ?? null, 'the local drive\'s file is a file of this machine');
        self::assertSame('/uploads/', substr((string) $report['drives']['local']['used']['public_url'], 0, 9));
    }

    /** The remote drives against their servers: the harness's (S3, SFTP, FTP, WebDAV, Azure), or those .env names. */
    public function testEachRemoteDriveIsUsedWholeAgainstItsServer(): void
    {
        if (false === getenv('OMNIDRIVE_S3_ENDPOINT') && false === getenv('OMNIDRIVE_SFTP_HOST') && false === getenv('OMNIDRIVE_FTP_HOST')) {
            self::markTestSkipped('No server named: docker compose run --rm omnidrive test runs this against the harness\'s.');
        }
        $report = self::report(true);

        self::assertSame([], self::framework($report), 'still no framework, every engine connected');
        $reached = 0;
        foreach ($report['drives'] as $name => $drive) {
            if (!isset($drive['used']) || \in_array($drive['factory'], ['memory', 'local'], true)) {
                continue;
            }
            ++$reached;
            foreach (self::STEPS as $step) {
                self::assertTrue($drive['used'][$step], "$name: $step - ".json_encode($drive['used'][$step]));
            }
            self::assertSame(['big.bin', 'hello.txt'], self::sorted($drive['used']['list'] ?? []), $name);
        }
        self::assertGreaterThan(0, $reached);
        if (isset($report['drives']['s3']['used'])) {
            $temporary = $report['drives']['s3']['used']['temporary_url'];
            self::assertTrue($temporary['signed'] && $temporary['served'], 'a signed address that serves the file');
            self::assertStringEndsWith('?...', $temporary['url'], 'shown without its signature');
        }
    }

    /**
     * The package installed alone (its CI's package job): no provider beside it
     * unless one is installed by hand. Its harness and its workspace install them all.
     */
    private static function alone(): bool
    {
        return 'omnisense/omnidrive' === \Composer\InstalledVersions::getRootPackage()['name'];
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private static function files(array $paths): array
    {
        return array_values(array_filter(self::sorted($paths), static fn (string $p) => !str_ends_with($p, '/') && !str_contains($p, '/')));
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private static function sorted(array $paths): array
    {
        $paths = array_values(array_filter($paths, static fn (string $p) => 'copies/' !== $p));
        sort($paths);

        return $paths;
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string>
     */
    private static function framework(array $report): array
    {
        // Composer includes every installed package's "files" before anything runs: loaded by the autoloader, not by Omnidrive.
        $eager = require \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName()).'/autoload_files.php';
        $files = array_diff(array_map('realpath', $report['files']), array_map('realpath', array_values($eager)));

        return [...array_values(preg_grep(self::FRAMEWORK, $report['symbols'])), ...array_values(preg_grep(self::FRAMEWORK_FILES, $files))];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>}
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIDRIVE_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);

        return [$status, $report];
    }
}
