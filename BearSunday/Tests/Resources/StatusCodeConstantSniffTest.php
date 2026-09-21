<?php

declare(strict_types=1);

namespace BearSunday\Tests\Resources;

use BearSunday\Tests\SniffTestCase;

use function copy;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

final class StatusCodeConstantSniffTest extends SniffTestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/BearSundayTests/Resource';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/ArticleResourceStatusCodeTest.php';
        copy(dirname(__DIR__) . '/Resources/Fixtures/StatusCodeConstantUnitTest.inc', $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (! file_exists($this->tempFile)) {
            return;
        }

        unlink($this->tempFile);
    }

    public function testSniffDetectsMagicStatusCode(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        // $this->code = 404 on line 12
        $this->assertArrayHasKey(12, $result['errors'], 'Expected error on line 12 ($this->code = 404)');
    }

    public function testSniffAllowsExistingConstant(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        // $this->code = StatusCode::CREATED on line 20 → no error
        $this->assertArrayNotHasKey(20, $result['errors'], 'Should not error when a constant is already used');
    }

    public function testSniffIgnoresCodesWithoutAConstant(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        // $this->code = 422 on line 28 → StatusCode has no UNPROCESSABLE_ENTITY constant
        $this->assertArrayNotHasKey(28, $result['errors'], 'Should not suggest an undefined constant');
    }

    public function testSniffMarksViolationFixable(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        $error = $this->firstErrorOnLine($result['errors'], 12);
        $this->assertNotNull($error, 'Expected an error on line 12');
        $this->assertTrue($error['fixable'], 'Should always be fixable — the fixer emits an absolute reference');
    }

    public function testSniffFixesTheLiteral(): void
    {
        $fixed = $this->fixFile(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        $this->assertStringContainsString(
            '$this->code = \Koriym\HttpConstants\StatusCode::NOT_FOUND;',
            $fixed,
            'Fixer must rewrite the literal in place using an absolute class reference',
        );
        $this->assertStringNotContainsString('$this->code = 404;', $fixed);
    }
}
