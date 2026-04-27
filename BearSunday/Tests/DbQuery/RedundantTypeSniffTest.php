<?php

declare(strict_types=1);

namespace BearSunday\Tests\DbQuery;

use BearSunday\Tests\SniffTestCase;

use function copy;
use function dirname;
use function file_exists;
use function is_dir;
use function mkdir;
use function sys_get_temp_dir;
use function unlink;

final class RedundantTypeSniffTest extends SniffTestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/BearSundayTests';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/RedundantTypeTest.php';
        copy(dirname(__DIR__) . '/DbQuery/Fixtures/RedundantTypeUnitTest.inc', $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (! file_exists($this->tempFile)) {
            return;
        }

        unlink($this->tempFile);
    }

    public function testSniffDetectsRedundantTypeRow(): void
    {
        $result   = $this->processSniff(
            $this->sniffPath('DbQuery', 'RedundantTypeSniff'),
            $this->tempFile,
        );
        $warnings = $result['warnings'];

        // Line 8: type:'row' redundant with ?Article return
        $this->assertArrayHasKey(8, $warnings, 'Expected warning on line 8 (type: row redundant)');
    }

    public function testSniffDetectsRedundantTypeRowList(): void
    {
        $result   = $this->processSniff(
            $this->sniffPath('DbQuery', 'RedundantTypeSniff'),
            $this->tempFile,
        );
        $warnings = $result['warnings'];

        // Line 12: type:'row_list' redundant with array return
        $this->assertArrayHasKey(12, $warnings, 'Expected warning on line 12 (type: row_list redundant)');
    }

    public function testSniffAllowsNullableBuiltinTypes(): void
    {
        $result   = $this->processSniff(
            $this->sniffPath('DbQuery', 'RedundantTypeSniff'),
            $this->tempFile,
        );
        $warnings = $result['warnings'];

        $this->assertArrayNotHasKey(16, $warnings, 'Should not warn on line 16 (?array)');
        $this->assertArrayNotHasKey(20, $warnings, 'Should not warn on line 20 (int|null)');
    }

    public function testSniffAllowsNoTypeArgument(): void
    {
        $result   = $this->processSniff(
            $this->sniffPath('DbQuery', 'RedundantTypeSniff'),
            $this->tempFile,
        );
        $warnings = $result['warnings'];

        $this->assertArrayNotHasKey(24, $warnings, 'Should not warn on line 24 (no type: arg)');
        $this->assertArrayNotHasKey(28, $warnings, 'Should not warn on line 28 (no type: arg)');
    }
}
