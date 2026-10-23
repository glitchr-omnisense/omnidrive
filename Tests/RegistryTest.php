<?php

namespace Omnidrive\Tests;

use Omnidrive\Config;
use Omnidrive\Exception\InvalidConfigException;
use Omnidrive\Registry;
use Omnidrive\Testing\InMemoryDriveFactory;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testDrivesAreBuiltOnceByNameFromTheirFactory(): void
    {
        $registry = new Registry([new InMemoryDriveFactory()], [
            'uploads' => ['factory' => 'memory', 'options' => ['public_url' => '/uploads']],
            'cache' => ['factory' => 'memory'],
        ]);

        self::assertSame(['uploads', 'cache'], $registry->names());
        self::assertSame(['memory'], $registry->factories());
        self::assertTrue($registry->has('cache'));
        self::assertFalse($registry->has('backup'));
        self::assertSame($registry->get('uploads'), $registry->get('uploads'));
        self::assertSame('uploads', $registry->get('uploads')->name());
        self::assertSame('/uploads/a.png', $registry->get('uploads')->publicUrl('a.png'));
        self::assertNotSame($registry->get('uploads'), $registry->create('uploads', ['public_url' => '/other']));
        self::assertSame('/other/a.png', $registry->create('uploads', ['public_url' => '/other'])->publicUrl('a.png'));
        self::assertSame(['uploads', 'cache'], array_keys($registry->all()));
    }

    public function testAnUnknownDriveOrFactoryIsSaidByName(): void
    {
        $registry = new Registry([new InMemoryDriveFactory()], ['archive' => ['factory' => 's3']]);

        try {
            $registry->get('backup');
            self::fail('an unknown drive');
        } catch (InvalidConfigException $e) {
            self::assertSame('No "backup" drive; configured: archive.', $e->getMessage());
        }
        $this->expectExceptionMessage('No "s3" factory for the "archive" drive; installed: memory.');
        $registry->get('archive');
    }

    public function testOptionsReadAsEnvironmentVariablesGiveThem(): void
    {
        $c = new Config(['lock' => 'false', 'port' => '2222', 'mode' => '0640', 'urls' => 'https://a.example, https://b.example', 'empty' => '']);

        self::assertFalse($c->bool('lock', true));
        self::assertTrue($c->bool('empty', true), 'empty: the default');
        self::assertSame(2222, $c->int('port'));
        self::assertSame(0640, $c->octal('mode', 0644));
        self::assertSame(0644, $c->octal('missing', 0644));
        self::assertSame(['https://a.example', 'https://b.example'], $c->list('urls'));
        self::assertNull($c->string('empty'));

        $this->expectExceptionMessage('The "?" drive needs: empty, absent.');
        $c->validateNotEmpty(['port', 'empty', 'absent']);
    }
}
