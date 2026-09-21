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
    private string $tempFile           = '';
    private string $aliasFile          = '';
    private string $noImportFile       = '';
    private string $multiNamespaceFile = '';

    protected function setUp(): void
    {
        // Path must contain /Domain/ to trigger the sniff.
        $dir = sys_get_temp_dir() . '/BearSundayTests/Domain';
        if (! is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        $this->tempFile = $dir . '/EnableTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoImplicitNowUnitTest.inc', $this->tempFile);

        $this->aliasFile = $dir . '/AliasTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoImplicitNowAliasUnitTest.inc', $this->aliasFile);

        $this->noImportFile = $dir . '/NoImportTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoImplicitNowNoImportUnitTest.inc', $this->noImportFile);

        $this->multiNamespaceFile = $dir . '/MultiNamespaceTest.php';
        copy(dirname(__DIR__) . '/Di/Fixtures/NoImplicitNowMultiNamespaceUnitTest.inc', $this->multiNamespaceFile);
    }

    protected function tearDown(): void
    {
        foreach ([$this->tempFile, $this->aliasFile, $this->noImportFile, $this->multiNamespaceFile] as $file) {
            if (! file_exists($file)) {
                continue;
            }

            unlink($file);
        }
    }

    public function testSniffDetectsImplicitNowViaDateTimeImmutable(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(17, $errors, 'Expected error on line 17 (implicit now via DateTimeImmutable)');
        $this->assertArrayHasKey(18, $errors, 'Expected error on line 18 (implicit now via DateTimeImmutable)');
    }

    public function testSniffDetectsImplicitNowViaDateTime(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(31, $errors, 'Expected error on line 31 (implicit now via DateTime)');
    }

    public function testSniffAllowsExplicitTimestampArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(28, $errors, 'Should not error on new DateTime($string)');
        $this->assertArrayNotHasKey(29, $errors, 'Should not error on new DateTime($string)');
    }

    public function testSniffDetectsFullyQualifiedZeroArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(40, $errors, 'Expected error on line 40 (new \DateTimeImmutable(), FQCN)');
    }

    public function testSniffDetectsNowLiteralArgument(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(46, $errors, "Expected error on line 46 (new \DateTimeImmutable('now'))");
    }

    public function testSniffAllowsFullyQualifiedExplicitTimestamp(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(52, $errors, 'Should not error on new \DateTime($string)');
    }

    public function testSniffMatchesClassNameAndNowLiteralCaseInsensitively(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(58, $errors, "Expected error on line 58 (new \DATETIMEIMMUTABLE('NOW'))");
    }

    public function testSniffSkipsCommentInEmptyArgumentList(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(64, $errors, 'Expected error on line 64 (comment-only argument list)');
    }

    public function testSniffSkipsCommentAfterNowLiteral(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(70, $errors, "Expected error on line 70 (comment after 'now' literal)");
    }

    public function testSniffDetectsAliasOfGlobalDateTime(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->aliasFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(13, $errors, 'Expected error on line 13 (use DateTime as Clock; new Clock())');
    }

    public function testSniffAllowsBareNameAliasedAwayFromGlobal(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->aliasFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(
            22,
            $errors,
            'Should not error: DateTime is imported from Fixtures\Other, not global',
        );
    }

    public function testSniffAllowsUnqualifiedNameWithoutMatchingUseImport(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->noImportFile,
        );
        $errors = $result['errors'];

        // Class names never fall back to the global namespace: with no
        // `use DateTime;` import, the bare reference resolves to
        // Fixtures\Domain\NoImport\DateTime, not the global class.
        $this->assertArrayNotHasKey(
            13,
            $errors,
            'Should not error: no use import redirects the bare name to global DateTime',
        );
    }

    public function testSniffSkipsCommentBeforeOpenParen(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(
            76,
            $errors,
            'Expected error on line 76 (comment between class name and opening paren)',
        );
    }

    /**
     * NoNewService explicitly allows DateTime, DateTimeImmutable, and other
     * date/time classes regardless of arguments (it only cares about DI
     * bypass). NoImplicitNow flags a subset of the same constructs when they
     * read "now". The two sniffs must not contradict each other on the same
     * code: pin that NoNewService stays silent on every DateTime construct in
     * the main fixture, including every line NoImplicitNow flags -- including
     * the case-insensitive line 58, now that NoNewService's own date-class
     * allow-list check is also case-insensitive.
     */
    public function testNoNewServiceAllowsSameDateTimeConstructs(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoNewServiceSniff'),
            $this->tempFile,
        );
        $errors = $result['errors'];

        foreach ([17, 18, 28, 29, 31, 40, 46, 52, 58, 64, 70, 76] as $line) {
            $this->assertArrayNotHasKey(
                $line,
                $errors,
                'NoNewService should never flag DateTime construction (line ' . $line . ')',
            );
        }
    }

    public function testSniffDetectsImplicitNowInFirstNamespaceBlock(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->multiNamespaceFile,
        );
        $errors = $result['errors'];

        $this->assertArrayHasKey(12, $errors, 'Expected error on line 12 (DateTime imported in this namespace block)');
    }

    public function testSniffDoesNotLeakUseImportAcrossNamespaceBlocks(): void
    {
        $result = $this->processSniff(
            $this->sniffPath('Di', 'NoImplicitNowSniff'),
            $this->multiNamespaceFile,
        );
        $errors = $result['errors'];

        $this->assertArrayNotHasKey(
            25,
            $errors,
            'Should not error: the second namespace block has no use import of its own',
        );
    }
}
