<?php

declare(strict_types=1);

namespace BearSunday\Tests\Resources;

use BearSunday\Tests\SniffTestCase;

use function copy;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

final class StatusCodeConstantSniffTest extends SniffTestCase
{
    private string $tempFile         = '';
    private string $noImportTempFile = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/BearSundayTests/Resource';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/ArticleResourceStatusCodeTest.php';
        copy(dirname(__DIR__) . '/Resources/Fixtures/StatusCodeConstantUnitTest.inc', $this->tempFile);

        $this->noImportTempFile = $dir . '/ArticleResourceStatusCodeNoImportTest.php';
        copy(dirname(__DIR__) . '/Resources/Fixtures/StatusCodeConstantNoImportUnitTest.inc', $this->noImportTempFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        if (! file_exists($this->noImportTempFile)) {
            return;
        }

        unlink($this->noImportTempFile);
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

    public function testSniffMarksViolationFixableWhenClassIsImported(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        $error = $this->firstErrorOnLine($result['errors'], 12);
        $this->assertNotNull($error, 'Expected an error on line 12');
        $this->assertTrue($error['fixable'], 'Should be fixable when StatusCode is already imported');
    }

    public function testSniffLeavesViolationNotFixableWhenClassIsNotImported(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->noImportTempFile,
        );

        // $this->code = 404 on line 10, no `use Koriym\HttpConstants\StatusCode;` in this file
        $error = $this->firstErrorOnLine($result['errors'], 10);
        $this->assertNotNull($error, 'Expected an error on line 10');
        $this->assertFalse($error['fixable'], 'Must not offer an auto-fix that references an unimported class');
    }

    public function testSniffFixesTheLiteralWhenClassIsImported(): void
    {
        $fixed = $this->fixFile(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->tempFile,
        );

        $this->assertStringContainsString(
            '$this->code = StatusCode::NOT_FOUND;',
            $fixed,
            'Fixer must rewrite the literal in place using the configured class',
        );
        $this->assertStringNotContainsString('$this->code = 404;', $fixed);
    }

    public function testSniffLeavesFileUnchangedWhenClassIsNotImported(): void
    {
        $original = file_get_contents($this->noImportTempFile);

        $fixed = $this->fixFile(
            $this->sniffPath('Resources', 'StatusCodeConstantSniff'),
            $this->noImportTempFile,
        );

        $this->assertSame(
            $original,
            $fixed,
            'Fixer must not touch a file where StatusCode is not imported',
        );
    }
}
