<?php

declare(strict_types=1);

namespace BearSunday\Sniffs\Resources;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

use function preg_match;
use function str_contains;
use function str_replace;

use const T_COLON;
use const T_FUNCTION;

/**
 * Ensures that Resource on* handler methods declare `static` as their return type.
 *
 * BEAR.Sunday ResourceObject methods must return `static` (not `self`, `$this`,
 * `ResourceObject`, or missing) so that the fluent interface type is preserved
 * across inheritance chains.
 *
 * Sniff: BearSunday.Resources.ReturnStatic
 */
final class ReturnStaticSniff implements Sniff
{
    /** @return list<int> */
    public function register(): array
    {
        return [T_FUNCTION];
    }

    public function process(File $phpcsFile, int $stackPtr): void
    {
        $filePath = str_replace('\\', '/', $phpcsFile->getFilename());
        if (! str_contains($filePath, '/Resource/')) {
            return;
        }

        $methodName = $phpcsFile->getDeclarationName($stackPtr);
        if ($methodName === null) {
            return;
        }

        if (! preg_match('/^on(Get|Post|Put|Patch|Delete|Head|Options)$/', $methodName)) {
            return;
        }

        // Find return type
        $returnType = $this->getReturnType($phpcsFile, $stackPtr);

        if ($returnType === 'static') {
            return;
        }

        $tokens  = $phpcsFile->getTokens();
        $hasBody = isset($tokens[$stackPtr]['scope_opener']);

        if ($returnType === null) {
            if (! $hasBody) {
                $phpcsFile->addError(
                    'Resource handler method "%s" must declare return type "static" (no return type found).',
                    $stackPtr,
                    'MissingReturnType',
                    [$methodName],
                );

                return;
            }

            $fix = $phpcsFile->addFixableError(
                'Resource handler method "%s" must declare return type "static" (no return type found).',
                $stackPtr,
                'MissingReturnType',
                [$methodName],
            );

            if ($fix) {
                $phpcsFile->fixer->addContent($tokens[$stackPtr]['parenthesis_closer'], ': static');
            }

            return;
        }

        if (! $hasBody) {
            $phpcsFile->addError(
                'Resource handler method "%s" must declare return type "static", found "%s".',
                $stackPtr,
                'WrongReturnType',
                [$methodName, $returnType],
            );

            return;
        }

        $fix = $phpcsFile->addFixableError(
            'Resource handler method "%s" must declare return type "static", found "%s".',
            $stackPtr,
            'WrongReturnType',
            [$methodName, $returnType],
        );

        if (! $fix) {
            return;
        }

        $this->replaceReturnType($phpcsFile, $stackPtr);
    }

    /**
     * Replaces the declared return type (everything between the `:` and the
     * method body's opening `{`) with `static`, leaving the whitespace/brace
     * placement on either side of it untouched.
     */
    private function replaceReturnType(File $phpcsFile, int $stackPtr): void
    {
        $tokens      = $phpcsFile->getTokens();
        $closeParen  = $tokens[$stackPtr]['parenthesis_closer'];
        $scopeOpener = $tokens[$stackPtr]['scope_opener'];
        $colon       = $phpcsFile->findNext(T_COLON, $closeParen + 1, $scopeOpener);

        if ($colon === false) {
            return;
        }

        $typeStart = $phpcsFile->findNext(Tokens::$emptyTokens, $colon + 1, $scopeOpener, true);
        $typeEnd   = $phpcsFile->findPrevious(Tokens::$emptyTokens, $scopeOpener - 1, $colon, true);

        if ($typeStart === false || $typeEnd === false) {
            return;
        }

        $phpcsFile->fixer->beginChangeset();
        $phpcsFile->fixer->replaceToken($typeStart, 'static');
        for ($i = $typeStart + 1; $i <= $typeEnd; $i++) {
            $phpcsFile->fixer->replaceToken($i, '');
        }

        $phpcsFile->fixer->endChangeset();
    }

    private function getReturnType(File $phpcsFile, int $stackPtr): string|null
    {
        $returnType = $phpcsFile->getMethodProperties($stackPtr)['return_type'];

        if ($returnType === '') {
            return null;
        }

        return $returnType;
    }
}
