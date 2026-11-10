<?php

namespace Omnidrive\Tests;

use Omnidrive\Config;
use Omnidrive\Exception\DriveException;
use Omnidrive\Exception\FileNotFoundException;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Exception\NotSupportedException;
use Omnidrive\Exception\OmnidriveException;
use Omnisense\Contracts\Exception\ExceptionInterface;
use PHPUnit\Framework\TestCase;

/** What omnidrive takes from omnisense/contracts: its Config, its exceptions, the redaction of a failure. */
final class KernelTest extends TestCase
{
    public function testTheConfigIsTheKernelsUnderTheFamilysPrefix(): void
    {
        $config = new Config(['root' => '/srv', 'public' => '', 'mode' => '0640', 'flag' => 'yes']);
        $config->defaults(['root' => '/elsewhere', 'omnidrive.factory_name' => 'local']);

        self::assertInstanceOf(\Omnisense\Contracts\Provider\Config::class, $config);
        self::assertSame('omnidrive.factory_name', $config->key('factory_name'));
        self::assertSame(['/srv', 0640, true, true], [$config->string('root'), $config->octal('mode', 0644), $config->bool('flag'), $config->bool('public', true)]);

        try {
            $config->validateNotEmpty(['root', 'bucket']);
            self::fail('A missing option is refused.');
        } catch (InvalidConfigException $e) {
            self::assertSame('The "local" drive needs: bucket.', $e->getMessage(), 'the drive\'s wording, as before');
        }
        $this->expectException(InvalidConfigException::class);
        (new Config(['policy' => 'loose']))->map('policy');
    }

    public function testOneCatchHoldsTheFamilysFailuresAndTheKernelsNames(): void
    {
        foreach ([new InvalidConfigException('x'), NotSupportedException::operation('local', 'a public address'), DriveException::of('read', 'local', 'a.txt'), FileNotFoundException::of('read', 'local', 'a.txt')] as $failure) {
            self::assertInstanceOf(OmnidriveException::class, $failure);
            self::assertInstanceOf(ExceptionInterface::class, $failure);
        }
        self::assertInstanceOf(\Omnisense\Contracts\Exception\InvalidConfigException::class, new InvalidConfigException('x'));
        self::assertInstanceOf(\InvalidArgumentException::class, new InvalidConfigException('x'));
        self::assertInstanceOf(\LogicException::class, NotSupportedException::operation('local', 'a public address'));
    }

    /**
     * An engine's failure that quotes its credentials - a server address with
     * its user and password, a pre-signed URL - becomes a DriveException whose
     * message masks them, and no trace of the chain keeps the calls' arguments
     * (the contents written, the client's keys).
     */
    public function testAFailureKeepsNoCredential(): void
    {
        $engine = self::thrown('the-contents-written', 'https://omnidrive:s3cr3t-password@dav.example/files/a.txt?X-Amz-Signature=abcdef0123&X-Amz-Credential=AKIAEXAMPLE%2F20261110 refused');

        $failure = DriveException::of('write', 'archive', 'a.txt', $engine);

        self::assertSame('write', $failure->operation);
        self::assertSame($engine, $failure->getPrevious(), 'the engine\'s own exception stays under it');
        self::assertStringContainsString('could not write "a.txt": https://***@dav.example/files/a.txt?X-Amz-Signature=***&X-Amz-Credential=*** refused', $failure->getMessage());
        foreach (['s3cr3t-password', 'abcdef0123', 'AKIAEXAMPLE'] as $secret) {
            self::assertStringNotContainsString($secret, $failure->getMessage());
        }
        for ($one = $failure; null !== $one; $one = $one->getPrevious()) {
            self::assertStringNotContainsString('the-contents-written', $one->getTraceAsString());
            self::assertSame([], array_filter($one->getTrace(), static fn (array $frame): bool => isset($frame['args'])));
        }
    }

    private static function thrown(string $contents, string $message): \RuntimeException
    {
        $write = static function (string $contents) use ($message): never {
            throw new \RuntimeException($message);
        };
        try {
            $write($contents);
        } catch (\RuntimeException $e) {
            return $e;
        }
    }
}
