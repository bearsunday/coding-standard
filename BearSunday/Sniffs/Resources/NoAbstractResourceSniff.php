<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function str_contains;
use function str_replace;

use const T_ABSTRACT;
use const T_CLASS;

/**
 * Forbids abstract class declarations inside Resource files.
 *
 * Resource classes should be concrete entry points. Shared abstract base
 * classes belong outside the Resource directory, for example Support\Resource.
 *
 * Sniff: BearSunday.Resources.NoAbstractResource
 */
final class NoAbstractResourceSniff implements Sniff
{
    /** @return list<int> */
    public function register(): array
    {
        return [T_ABSTRACT];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! str_contains($filePath, '/Resource/')) {
            return;
        }

        $tokens       = $phpcsFile->getTokens();
        $nextToken    = $phpcsFile->findNext(Tokens::$emptyTokens, $stackPtr + 1, null, true);
        $isClassToken = $nextToken !== false && $tokens[$nextToken]['code'] === T_CLASS;

        if (! $isClassToken) {
            return;
        }

        $className = $phpcsFile->getDeclarationName($nextToken) ?? 'unknown';

        $phpcsFile->addError(
            'Resource class "%s" must not be abstract. '
            . 'Move shared abstract base classes outside the Resource directory, for example Support\\Resource.',
            $stackPtr,
            'AbstractResourceFound',
            [$className],
        );
    }
}
