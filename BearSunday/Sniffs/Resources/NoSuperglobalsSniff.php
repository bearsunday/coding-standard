<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

use function in_array;
use function str_contains;
use function str_replace;

use const T_VARIABLE;

/**
 * Forbids superglobal access inside Resource classes.
 *
 * In BEAR.Sunday, request data is injected via parameter attributes such as
 * #[QueryParam], #[CookieParam], and #[UploadFiles]. Accessing $_GET, $_POST,
 * etc. directly bypasses the framework's request lifecycle and breaks AOP
 * interceptor chains.
 *
 * Sniff: BearSunday.Resources.NoSuperglobals
 */
final class NoSuperglobalsSniff implements Sniff
{
    private const array SUPERGLOBALS = [
        '$_GET',
        '$_POST',
        '$_FILES',
        '$_COOKIE',
        '$_SESSION',
        '$_REQUEST',
        '$_SERVER',
        '$_ENV',
        '$GLOBALS',
    ];

    /** @return list<int> */
    public function register(): array
    {
        return [T_VARIABLE];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! str_contains($filePath, '/Resource/')) {
            return;
        }

        $tokens  = $phpcsFile->getTokens();
        $content = $tokens[$stackPtr]['content'];

        if (! in_array($content, self::SUPERGLOBALS, true)) {
            return;
        }

        $phpcsFile->addError(
            'Superglobal %s must not be used in Resource classes. '
            . 'Use BEAR.Sunday parameter attributes instead: #[QueryParam], #[CookieParam], #[UploadFiles].',
            $stackPtr,
            'SuperglobalFound',
            [$content],
        );
    }
}
