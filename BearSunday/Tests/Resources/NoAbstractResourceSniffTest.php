<?php

declare(strict_types=1);

namespace BearSunday\Tests\Resources;

use BearSunday\Tests\SniffTestCase;

use function copy;
use function file_exists;
use function getmypid;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class NoAbstractResourceSniffTest extends SniffTestCase
{
    private string $resourceFile = '';
    private string $supportFile  = '';
    private string $tempDir      = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/BearSundayTests-' . getmypid() . '-' . uniqid('', true);

        $resourceDir = $this->tempDir . '/Resource/Page';
        self::assertTrue(is_dir($resourceDir) || mkdir($resourceDir, 0o755, true));

        $supportDir = $this->tempDir . '/Support';
        self::assertTrue(is_dir($supportDir) || mkdir($supportDir, 0o755, true));

        $fixture = __DIR__ . '/Fixtures/NoAbstractResourceUnitTest.inc';

        $this->resourceFile = $resourceDir . '/ArticleTest.php';
        self::assertTrue(copy($fixture, $this->resourceFile));

        $this->supportFile = $supportDir . '/ArticleTest.php';
        self::assertTrue(copy($fixture, $this->supportFile));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->resourceFile)) {
            unlink($this->resourceFile);
        }

        if (file_exists($this->supportFile)) {
            unlink($this->supportFile);
        }

        $resourcePageDir = $this->tempDir . '/Resource/Page';
        if (is_dir($resourcePageDir)) {
            rmdir($resourcePageDir);
        }

        $resourceDir = $this->tempDir . '/Resource';
        if (is_dir($resourceDir)) {
            rmdir($resourceDir);
        }

        $supportDir = $this->tempDir . '/Support';
        if (is_dir($supportDir)) {
            rmdir($supportDir);
        }

        if (! is_dir($this->tempDir)) {
            return;
        }

        rmdir($this->tempDir);
    }

    public function testSniffDetectsAbstractResourceClasses(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'NoAbstractResourceSniff'),
            $this->resourceFile,
        );
        $errors = $result['errors'];

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey(6, $errors, 'Expected error on line 6 (abstract Resource class)');
        $this->assertArrayHasKey(16, $errors, 'Expected error on line 16 (abstract Resource class with comment)');
        $this->assertArrayNotHasKey(8, $errors, 'Should not error on line 8 (abstract method)');
        $this->assertArrayNotHasKey(23, $errors, 'Should not error on line 23 (concrete Resource class)');
    }

    public function testSniffIgnoresFilesOutsideResourcePath(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'NoAbstractResourceSniff'),
            $this->supportFile,
        );

        $this->assertSame([], $result['errors']);
    }
}
