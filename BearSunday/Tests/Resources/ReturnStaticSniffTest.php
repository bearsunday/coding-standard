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

final class ReturnStaticSniffTest extends SniffTestCase
{
    private string $tempFile = '';
    private string $tempDir  = '';

    protected function setUp(): void
    {
        // Path must contain /Resource/ to trigger the sniff
        $this->tempDir = sys_get_temp_dir() . '/BearSundayTests-' . getmypid() . '-' . uniqid('', true);
        $dir           = $this->tempDir . '/Resource';
        self::assertTrue(is_dir($dir) || mkdir($dir, 0o755, true));

        $this->tempFile = $dir . '/ArticleTest.php';
        self::assertTrue(copy(__DIR__ . '/Fixtures/ReturnStaticUnitTest.inc', $this->tempFile));
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

    public function testSniffDetectsWrongReturnTypes(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'ReturnStaticSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(16, $errors, 'Expected error on line 16 (self return)');
        $this->assertArrayHasKey(22, $errors, 'Expected error on line 22 (missing return type)');
        $this->assertArrayHasKey(28, $errors, 'Expected error on line 28 (ResourceObject return)');
    }

    public function testSniffAllowsStaticReturnType(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'ReturnStaticSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(10, $errors, 'Should not error on line 10 (static return)');
        $this->assertArrayNotHasKey(39, $errors, 'Should not error on line 39 (static return)');
    }
}
