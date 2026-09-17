<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\AppMeta;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function str_starts_with;
use function strlen;
use function substr;
use function trim;
use function var_export;

use const T_CONSTANT_ENCAPSED_STRING;
use const T_OBJECT_OPERATOR;
use const T_STRING;
use const T_STRING_CONCAT;
use const T_WHITESPACE;

/**
 * Forbids deriving `/var/tmp` paths from `$appMeta->appDir`.
 *
 * BEAR.Sunday's read-only deployment (Vercel, AWS Lambda, `docker run
 * --read-only`, `readOnlyRootFilesystem: true`) keeps `appDir` read-only and
 * routes anything written at runtime through `$appMeta->tmpDir`, which
 * resolves under the writable system temp directory (or an explicit path
 * passed to `ReadOnlyAppModule`).
 *
 * Building a tmp path by concatenating onto `appDir` (e.g.
 * `$appMeta->appDir . '/var/tmp/cache'`) hard-codes a path under the
 * read-only tree and breaks that deployment model, even though it works
 * fine on a writable filesystem. Other `appDir` concatenation (assets,
 * config, `/var/build` compiled artifacts shipped with the release) is
 * unaffected — only the literal `/var/tmp` prefix is the anti-pattern.
 *
 * @see https://bearsunday.github.io/manuals/1.0/ja/production.html#writable-paths
 *
 * Sniff: BearSunday.AppMeta.NoAppDirWritablePath
 */
final class NoAppDirWritablePathSniff implements Sniff
{
    private const string TMP_PREFIX = '/var/tmp';

    /** @return list<int> */
    public function register(): array
    {
        return [T_STRING];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$stackPtr]['content'] !== 'appDir') {
            return;
        }

        $objectOperator = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr - 1, null, true);
        if ($objectOperator === false || $tokens[$objectOperator]['code'] !== T_OBJECT_OPERATOR) {
            return;
        }

        $concat = $phpcsFile->findNext(T_WHITESPACE, $stackPtr + 1, null, true);
        if ($concat === false || $tokens[$concat]['code'] !== T_STRING_CONCAT) {
            return;
        }

        $string = $phpcsFile->findNext(T_WHITESPACE, $concat + 1, null, true);
        if ($string === false || $tokens[$string]['code'] !== T_CONSTANT_ENCAPSED_STRING) {
            return;
        }

        $literal = trim($tokens[$string]['content'], "'\"");
        if ($literal !== self::TMP_PREFIX && ! str_starts_with($literal, self::TMP_PREFIX . '/')) {
            return;
        }

        $suffix      = substr($literal, strlen(self::TMP_PREFIX));
        $replacement = $suffix === '' ? '$appMeta->tmpDir' : '$appMeta->tmpDir . ' . var_export($suffix, true);

        $phpcsFile->addError(
            'Writable path "%s" must not be derived from $appMeta->appDir; use %s instead so '
            . 'read-only deployments keep appDir untouched at runtime.',
            $stackPtr,
            'WritablePathFromAppDir',
            [$literal, $replacement],
        );
    }
}
