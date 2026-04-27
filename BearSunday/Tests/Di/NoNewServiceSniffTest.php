<?php

declare(strict_types=1);

namespace BearSunday\Tests\Di;

use BearSunday\Tests\SniffTestCase;

use function copy;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

final class NoNewServiceSniffTest extends SniffTestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        // Path must contain /Service/ to trigger the sniff
        $dir = sys_get_temp_dir() . '/BearSundayTests/Service';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/ArticleServiceTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoNewServiceUnitTest.inc', $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (! file_exists($this->tempFile)) {
            return;
        }

        unlink($this->tempFile);
    }

    public function testSniffDetectsNewService(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(32, $errors, 'Expected error on line 32 (new OtherService)');
        $this->assertArrayHasKey(38, $errors, 'Expected error on line 38 (new ArticleRepository)');
    }

    public function testSniffAllowsValueObjects(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(20, $errors, 'Should not error on line 20 (ArticleInput)');
    }

    public function testSniffAllowsDateClasses(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(26, $errors, 'Should not error on line 26 (DateTimeImmutable)');
    }

    public function testSniffAllowsThrowNew(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(13, $errors, 'Should not error on line 13 (throw new)');
        $this->assertArrayNotHasKey(44, $errors, 'Should not error on line 44 (throw expression)');
        $this->assertArrayNotHasKey(52, $errors, 'Should not error on line 52 (match throw expression)');
        $this->assertArrayNotHasKey(59, $errors, 'Should not error on line 59 (arrow function throw expression)');
    }
}
