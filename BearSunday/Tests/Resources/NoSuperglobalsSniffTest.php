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

final class NoSuperglobalsSniffTest extends SniffTestCase
{
    private string $tempFile = '';
    private string $tempDir  = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/BearSundayTests-' . getmypid() . '-' . uniqid('', true);
        $dir           = $this->tempDir . '/Resource';
        self::assertTrue(is_dir($dir) || mkdir($dir, 0o755, true));

        $this->tempFile = $dir . '/AuthTest.php';
        self::assertTrue(copy(__DIR__ . '/Fixtures/NoSuperglobalsUnitTest.inc', $this->tempFile));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        $resourceDir = $this->tempDir . '/Resource';
        if (is_dir($resourceDir)) {
            rmdir($resourceDir);
        }

        if (! is_dir($this->tempDir)) {
            return;
        }

        rmdir($this->tempDir);
    }

    public function testSniffDetectsSuperglobals(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'NoSuperglobalsSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(10, $errors, 'Expected error on line 10 ($_GET)');
        $this->assertArrayHasKey(18, $errors, 'Expected error on line 18 ($_POST)');
        $this->assertArrayHasKey(26, $errors, 'Expected error on line 26 ($_SERVER)');
        $this->assertArrayHasKey(34, $errors, 'Expected error on line 34 ($GLOBALS)');
    }

    public function testSniffAllowsRegularVariables(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'NoSuperglobalsSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(42, $errors, 'Should not error on regular variable');
    }
}
