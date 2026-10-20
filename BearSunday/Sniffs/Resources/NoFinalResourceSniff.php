<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function str_contains;
use function str_replace;

use const T_CLASS;
use const T_FINAL;
use const T_READONLY;

/**
 * Forbids final Resource class declarations.
 *
 * Ray.Aop applies interceptors by generating a subclass. Ray.Di silently skips
 * a final class, so attributes such as #[SameOrigin] and #[CsrfToken] would run
 * unprotected.
 *
 * Sniff: BearSunday.Resources.NoFinalResource
 */
final class NoFinalResourceSniff implements Sniff
{
    /** @return list<int> */
    public function register(): array
    {
        return [T_FINAL];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! str_contains($filePath, '/Resource/')) {
            return;
        }

        $tokens    = $phpcsFile->getTokens();
        $nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);

        if ($nextToken !== false && $tokens[$nextToken]['code'] === T_READONLY) {
            $nextToken = $phpcsFile->findNext(Tokens::$emptyTokens, $nextToken + 1, null, true);
        }

        if ($nextToken === false || $tokens[$nextToken]['code'] !== T_CLASS) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($nextToken) ?? 'unknown';

        $phpcsFile->addError(
            'Resource class "%s" must not be final. '
            . 'Ray.Aop cannot apply interceptors to a final class, so its attributes are silently ignored.',
            $stackPtr,
            'FinalResourceFound',
            [$className],
        );
    }
}
