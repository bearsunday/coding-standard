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

final class HeaderConstantSniffTest extends SniffTestCase
{
    private string $tempFile         = '';
    private string $noImportTempFile = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/BearSundayTests/Resource';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/ArticleResourceHeaderTest.php';
        copy(dirname(__DIR__) . '/Resources/Fixtures/HeaderConstantUnitTest.inc', $this->tempFile);

        $this->noImportTempFile = $dir . '/ArticleResourceHeaderNoImportTest.php';
        copy(dirname(__DIR__) . '/Resources/Fixtures/HeaderConstantNoImportUnitTest.inc', $this->noImportTempFile);
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

    public function testSniffDetectsStringHeaderName(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
        );

        // $this->headers['Location'] on line 12
        $this->assertArrayHasKey(12, $result['errors'], "Expected error on line 12 (headers['Location'])");
    }

    public function testSniffAllowsExistingConstant(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
        );

        // $this->headers[ResponseHeader::LOCATION] on line 20 → no error
        $this->assertArrayNotHasKey(20, $result['errors'], 'Should not error when a constant is already used');
    }

    public function testSniffIgnoresUnknownHeaders(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
        );

        // $this->headers['X-Request-Id'] on line 28 → no ResponseHeader constant exists
        $this->assertArrayNotHasKey(28, $result['errors'], 'Should not suggest an undefined constant');
    }

    public function testSniffRespectsAllowedHeadersProperty(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
            ['allowedHeaders' => ['Location']],
        );

        // $this->headers['Location'] on line 12 is suppressed by allowedHeaders
        $this->assertArrayNotHasKey(12, $result['errors'], 'allowedHeaders should suppress the Location error');
    }

    public function testSniffMarksViolationFixable(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
        );

        $error = $this->firstErrorOnLine($result['errors'], 12);
        $this->assertNotNull($error, 'Expected an error on line 12');
        $this->assertTrue($error['fixable'], 'Should always be fixable — the fixer emits an absolute reference');
    }

    public function testSniffFixesTheLiteral(): void
    {
        $fixed = $this->fixFile(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->tempFile,
        );

        $this->assertStringContainsString(
            "\$this->headers[\Koriym\HttpConstants\ResponseHeader::LOCATION] = '/articles/1';",
            $fixed,
            'Fixer must rewrite the literal in place using an absolute class reference',
        );
        $this->assertStringNotContainsString("\$this->headers['Location'] = '/articles/1';", $fixed);
    }

    public function testSniffFixesTheLiteralWithoutAUseImport(): void
    {
        $fixed = $this->fixFile(
            $this->sniffPath('Resources', 'HeaderConstantSniff'),
            $this->noImportTempFile,
        );

        $this->assertStringContainsString(
            "\$this->headers[\Koriym\HttpConstants\ResponseHeader::LOCATION] = '/articles/1';",
            $fixed,
            'Fixer must emit an absolute reference even when the class is not use-imported',
        );
        $this->assertStringNotContainsString("\$this->headers['Location'] = '/articles/1';", $fixed);
    }
}
