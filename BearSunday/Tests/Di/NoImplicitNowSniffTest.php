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

final class NoImplicitNowSniffTest extends SniffTestCase
{
    private string $tempFile = '';

    protected function setUp(): void
    {
        // Path must contain /Domain/ to trigger the sniff
        $dir = sys_get_temp_dir() . '/BearSundayTests/Domain';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/EnableTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoImplicitNowUnitTest.inc', $this->tempFile);
    }

    protected function tearDown(): void
    {
        if (! file_exists($this->tempFile)) {
            return;
        }

        unlink($this->tempFile);
    }

    public function testSniffDetectsImplicitNowViaDateTimeImmutable(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(14, $errors, 'Expected error on line 14 (implicit now via DateTimeImmutable)');
        $this->assertArrayHasKey(15, $errors, 'Expected error on line 15 (implicit now via DateTimeImmutable)');
    }

    public function testSniffDetectsImplicitNowViaDateTime(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(28, $errors, 'Expected error on line 28 (implicit now via DateTime)');
    }

    public function testSniffAllowsExplicitTimestampArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(25, $errors, 'Should not error on new DateTime($string)');
        $this->assertArrayNotHasKey(26, $errors, 'Should not error on new DateTime($string)');
    }

    public function testSniffDetectsFullyQualifiedZeroArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(37, $errors, 'Expected error on line 37 (new \DateTimeImmutable(), FQCN)');
    }

    public function testSniffDetectsNowLiteralArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(43, $errors, "Expected error on line 43 (new \DateTimeImmutable('now'))");
    }

    public function testSniffAllowsFullyQualifiedExplicitTimestamp(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(49, $errors, 'Should not error on new \DateTime($string)');
    }

    /**
     * NoNewService explicitly allows DateTime, DateInterval, and DateTimeZone
     * construction regardless of arguments (it only cares about DI bypass).
     * NoImplicitNow flags the same construct when it reads "now". The two
     * sniffs must not contradict each other on the same code: pin that
     * NoNewService stays silent on every DateTime construct in this fixture,
     * including the ones NoImplicitNow flags.
     */
    public function testNoNewServiceAllowsSameDateTimeConstructs(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        foreach ([14, 15, 25, 26, 28, 37, 43, 49] as $line) {
            $this->assertArrayNotHasKey(
                $line,
                $errors,
                'NoNewService should never flag DateTime construction (line ' . $line . ')',
            );
        }
    }
}
